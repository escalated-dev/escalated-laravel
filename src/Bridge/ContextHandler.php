<?php

namespace Escalated\Laravel\Bridge;

use Escalated\Laravel\Bridge\Events\PluginBroadcastEvent;
use Escalated\Laravel\Enums\TicketChannel;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\PluginStoreRecord;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\SlackInbox;
use Escalated\Laravel\Support\PluginEvents;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Handles ctx.* callbacks coming from the plugin runtime.
 *
 * The plugin runtime sends JSON-RPC requests back to the host when plugin code
 * calls ctx.tickets.find(), ctx.config.all(), ctx.store.query(), etc.
 * This class translates those calls into native Laravel/Eloquent operations
 * and returns the result.
 *
 * ctx.* callbacks are synchronous from the plugin's perspective: the plugin
 * awaits the promise and the host blocks the JSON-RPC read loop until the
 * Eloquent operation completes, then sends back the JSON-RPC response.
 */
class ContextHandler
{
    /**
     * The fields ctx.tickets.update takes (plugin contract). title and body
     * are the SDK's names for subject and description.
     */
    private const TICKET_UPDATE_FIELDS = [
        'subject', 'title', 'body', 'description', 'priority', 'status',
        'department_id', 'ticket_type', 'metadata',
    ];

    /**
     * ctx.tickets.create takes the update fields plus the channel and the
     * requester, which is a contact: never a user id the plugin names.
     */
    private const TICKET_CREATE_FIELDS = [
        ...self::TICKET_UPDATE_FIELDS, 'channel', 'contact_id', 'requester_email', 'requester_name',
    ];

    /**
     * ctx.replies.create: the author is the acting user, never a field.
     */
    private const REPLY_CREATE_FIELDS = ['ticket_id', 'body', 'is_internal'];

    private const TAG_CREATE_FIELDS = ['name', 'slug', 'color'];

    private const PLUGIN_CONTACT_FILLABLE = ['name', 'email'];

    /**
     * ctx.tickets.query filter keys (plugin contract) and the column each
     * reads. Any other key is an error, never a column name.
     */
    private const TICKET_FILTERS = [
        'id' => 'id',
        'reference' => 'reference',
        'status' => 'status',
        'priority' => 'priority',
        'channel' => 'channel',
        'ticket_type' => 'ticket_type',
        'department_id' => 'department_id',
        'requester_id' => 'requester_id',
        'contact_id' => 'contact_id',
        'assigned_to' => 'assigned_to',
    ];

    /**
     * ctx.replies.query filter keys (plugin contract) and their columns.
     */
    private const REPLY_FILTERS = [
        'ticket_id' => 'ticket_id',
        'is_internal' => 'is_internal_note',
        'type' => 'type',
    ];

    /**
     * The most rows a ticket or reply query hands back.
     */
    private const QUERY_LIMIT = 500;

    /**
     * ctx.store.query comparison operators and the SQL operator for each.
     */
    private const STORE_COMPARISONS = [
        '$gt' => '>',
        '$gte' => '>=',
        '$lt' => '<',
        '$lte' => '<=',
        '$ne' => '!=',
    ];

    /**
     * The plugin name that is currently executing (set before each dispatch).
     */
    private string $currentPlugin = '';

    /**
     * The plugins the host call being served was sent to: one for an
     * endpoint, webhook, cron, badge or lifecycle call, the active ones for
     * an action or filter. A ctx call acts for one of these, never for a
     * plugin the runtime merely names.
     *
     * @var list<string>
     */
    private array $serving = [];

    /**
     * Whether the call being served is a hook (action or filter), sent to
     * several plugins at once.
     */
    private bool $servingHook = false;

    /**
     * Reference back to the bridge for ctx.emit support.
     */
    private ?PluginBridge $bridge = null;

    public function setBridge(PluginBridge $bridge): void
    {
        $this->bridge = $bridge;
    }

    public function setCurrentPlugin(string $plugin): void
    {
        $this->currentPlugin = $plugin;
        $this->serving = $plugin === '' ? [] : [$plugin];
        $this->servingHook = false;
    }

    /**
     * Run a host call that is sent to $plugins, so the ctx calls the runtime
     * makes while serving it act only for those plugins. $hook marks an
     * action or filter, sent to every plugin in $plugins at once. Restores
     * what was being served before, since a ctx.emit nests one call inside
     * another.
     *
     * @param  list<string>  $plugins
     */
    public function serve(array $plugins, callable $call, bool $hook = false): mixed
    {
        $before = [$this->serving, $this->currentPlugin, $this->servingHook];
        $this->serving = array_values($plugins);
        $this->servingHook = $hook;
        $this->currentPlugin = count($this->serving) === 1 ? $this->serving[0] : '';

        try {
            return $call();
        } finally {
            [$this->serving, $this->currentPlugin, $this->servingHook] = $before;
        }
    }

    /**
     * The plugin a ctx call acts for (plugin contract, The plugin context).
     * For a call sent to one plugin (endpoint, webhook, cron, badge,
     * lifecycle) that plugin, whatever the runtime names. For a hook, the
     * plugin the runtime names if the hook was sent to it, and nothing
     * otherwise: an older runtime runs every plugin's handlers.
     */
    private function servedPlugin(array $params): ?string
    {
        $claimed = $params['plugin'] ?? null;

        if (count($this->serving) === 1 && (! $this->servingHook || $claimed === null)) {
            return $this->serving[0];
        }

        return is_string($claimed) && in_array($claimed, $this->serving, true) ? $claimed : null;
    }

    /**
     * Dispatch a ctx.* method call from the runtime to the appropriate handler.
     *
     * @param  string  $method  e.g. "ctx.tickets.find"
     */
    public function handle(string $method, array $params): mixed
    {
        app(TenantContext::class)->assertPluginRuntimeSupported();

        // Never trust the runtime's `plugin` param: the plugin is the one the
        // served call went to. A call for any other plugin, or made outside a
        // call, gets no host data and makes no writes. It may still log.
        $plugin = $this->servedPlugin($params);
        if ($method !== 'ctx.log') {
            if ($plugin === null) {
                throw new \RuntimeException('ctx call does not belong to a plugin this call was sent to.');
            }

            // An older runtime may still hand an inactive plugin a hook.
            if ($this->bridge !== null && array_key_exists($plugin, $this->bridge->getManifests())
                && ! SdkPluginActivation::isActive($plugin)) {
                throw new \RuntimeException("Plugin {$plugin} is not active.");
            }
        }

        if ($plugin !== null) {
            $params['plugin'] = $plugin;
        }

        return match (true) {
            // Config
            $method === 'ctx.config.all' => $this->configAll($params),
            $method === 'ctx.config.get' => $this->configGet($params),
            $method === 'ctx.config.set' => $this->configSet($params),

            // Store
            $method === 'ctx.store.get' => $this->storeGet($params),
            $method === 'ctx.store.set' => $this->storeSet($params),
            $method === 'ctx.store.query' => $this->storeQuery($params),
            $method === 'ctx.store.insert' => $this->storeInsert($params),
            $method === 'ctx.store.update' => $this->storeUpdate($params),
            $method === 'ctx.store.delete' => $this->storeDelete($params),

            // Tickets
            $method === 'ctx.tickets.find' => $this->ticketsFind($params),
            $method === 'ctx.tickets.query' => $this->ticketsQuery($params),
            $method === 'ctx.tickets.create' => $this->ticketsCreate($params),
            $method === 'ctx.tickets.update' => $this->ticketsUpdate($params),

            // Replies
            $method === 'ctx.replies.find' => $this->repliesFind($params),
            $method === 'ctx.replies.query' => $this->repliesQuery($params),
            $method === 'ctx.replies.create' => $this->repliesCreate($params),

            // Contacts (users)
            $method === 'ctx.contacts.find' => $this->contactsFind($params),
            $method === 'ctx.contacts.findByEmail' => $this->contactsFindByEmail($params),
            $method === 'ctx.contacts.create' => $this->contactsCreate($params),

            // Tags
            $method === 'ctx.tags.all' => $this->tagsAll(),
            $method === 'ctx.tags.create' => $this->tagsCreate($params),

            // Departments
            $method === 'ctx.departments.all' => $this->departmentsAll(),
            $method === 'ctx.departments.find' => $this->departmentsFind($params),

            // Agents
            $method === 'ctx.agents.all' => $this->agentsAll(),
            $method === 'ctx.agents.find' => $this->agentsFind($params),

            // Broadcast
            $method === 'ctx.broadcast.toChannel' => $this->broadcastToChannel($params),
            $method === 'ctx.broadcast.toUser' => $this->broadcastToUser($params),
            $method === 'ctx.broadcast.toTicket' => $this->broadcastToTicket($params),

            // Misc
            $method === 'ctx.emit' => $this->emit($params),
            $method === 'ctx.log' => $this->ctxLog($params),
            $method === 'ctx.currentUser' => $this->currentUser(),

            default => throw new \RuntimeException("Unknown ctx method: {$method}"),
        };
    }

    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    private function configAll(array $params): array
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;

        return $this->getPluginConfig($plugin);
    }

    private function configGet(array $params): mixed
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $key = $params['key'] ?? throw new \InvalidArgumentException('ctx.config.get requires key');

        return $this->getPluginConfig($plugin)[$key] ?? null;
    }

    private function configSet(array $params): null
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $data = $params['data'] ?? throw new \InvalidArgumentException('ctx.config.set requires data');

        $this->setPluginConfig($plugin, $data);

        return null;
    }

    private function getPluginConfig(string $plugin): array
    {
        $record = PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', '__config__')
            ->where('key', '__config__')
            ->first();

        if (! $record) {
            return [];
        }

        return is_array($record->data) ? $record->data : [];
    }

    private function setPluginConfig(string $plugin, array $data): void
    {
        $existing = $this->getPluginConfig($plugin);
        $merged = array_merge($existing, $data);

        PluginStoreRecord::updateOrCreate(
            [
                'plugin' => $plugin,
                'collection' => '__config__',
                'key' => '__config__',
            ],
            ['data' => $merged]
        );
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    private function storeGet(array $params): mixed
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.get requires collection');
        $key = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.get requires key');

        $record = PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', $collection)
            ->where('key', $key)
            ->first();

        return $record?->data;
    }

    private function storeSet(array $params): null
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.set requires collection');
        $key = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.set requires key');
        $value = $params['value'] ?? null;

        PluginStoreRecord::updateOrCreate(
            ['plugin' => $plugin, 'collection' => $collection, 'key' => $key],
            ['data' => $value]
        );

        return null;
    }

    private function storeQuery(array $params): array
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.query requires collection');
        $filter = $params['filter'] ?? [];
        $options = $params['options'] ?? [];

        $query = PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', $collection);

        foreach ($filter as $field => $condition) {
            $field = (string) $field;
            $this->validateFieldName($field);

            if (is_array($condition)) {
                // Operator conditions: { $gt: 10 }, { $in: [1,2,3] }, etc.
                foreach ($condition as $op => $val) {
                    $this->applyJsonOperator($query, $field, $op, $val);
                }
            } else {
                // Simple equality. A dotted field is a nested path, as it is
                // for the operators.
                $query->whereJsonContains('data->'.str_replace('.', '->', $field), $condition);
            }
        }

        if (isset($options['orderBy'])) {
            $field = (string) $options['orderBy'];
            $this->validateFieldName($field);
            $direction = strtolower((string) ($options['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

            // Numbers by value, then strings as text, so 10 sorts after 9.
            $query->orderByRaw($this->jsonValue($query, $field, 'number').' '.$direction)
                ->orderByRaw($this->jsonValue($query, $field, 'string').' '.$direction);
        }

        if (isset($options['limit'])) {
            $query->limit((int) $options['limit']);
        }

        return $query->get()->map(fn ($r) => array_merge(['_id' => $r->id], is_array($r->data) ? $r->data : []))->values()->all();
    }

    private function storeInsert(array $params): array
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.insert requires collection');
        $data = $params['data'] ?? throw new \InvalidArgumentException('ctx.store.insert requires data');

        $record = PluginStoreRecord::create([
            'plugin' => $plugin,
            'collection' => $collection,
            'key' => $data['key'] ?? null,
            'data' => $data,
        ]);

        return array_merge(['_id' => $record->id], is_array($record->data) ? $record->data : []);
    }

    private function storeUpdate(array $params): array
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.update requires collection');
        $key = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.update requires key');
        $data = $params['data'] ?? throw new \InvalidArgumentException('ctx.store.update requires data');

        $record = PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', $collection)
            ->where('key', $key)
            ->firstOrFail();

        $existing = is_array($record->data) ? $record->data : [];
        $record->update(['data' => array_merge($existing, $data)]);

        return array_merge(['_id' => $record->id], is_array($record->data) ? $record->data : []);
    }

    private function storeDelete(array $params): null
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.delete requires collection');
        $key = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.delete requires key');

        PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', $collection)
            ->where('key', $key)
            ->delete();

        return null;
    }

    /**
     * A store field name is a dotted path of letters, digits and underscores.
     * It is written into SQL, so nothing else is allowed.
     */
    private function validateFieldName(string $field): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)*$/', $field)) {
            throw new \InvalidArgumentException("Invalid store field name: {$field}");
        }
    }

    /**
     * Apply a MongoDB-style query operator to a JSON column query.
     */
    private function applyJsonOperator(Builder $query, string $field, string $op, mixed $value): void
    {
        $this->validateFieldName($field);

        if (isset(self::STORE_COMPARISONS[$op])) {
            $type = $this->jsonTypeOf($value);

            $query->whereRaw(
                $this->jsonValue($query, $field, $type).' '.self::STORE_COMPARISONS[$op].' '.$this->jsonPlaceholder($query, $type),
                [$this->jsonBinding($value)],
            );

            return;
        }

        match ($op) {
            '$in' => $this->whereJsonIn($query, $field, (array) $value, false),
            '$nin' => $this->whereJsonIn($query, $field, (array) $value, true),
            default => throw new \InvalidArgumentException("Unsupported store query operator: {$op}"),
        };
    }

    /**
     * $in and $nin. The values are grouped by JSON type so each group compares
     * like with like. A document without the field is in no list, so $nin
     * matches it, as it does in MongoDB.
     */
    private function whereJsonIn(Builder $query, string $field, array $values, bool $negate): void
    {
        $clauses = [];
        $bindings = [];

        foreach (collect($values)->groupBy(fn ($value) => $this->jsonTypeOf($value)) as $type => $group) {
            $placeholders = implode(', ', array_fill(0, $group->count(), $this->jsonPlaceholder($query, $type)));
            $clauses[] = 'coalesce('.$this->jsonValue($query, $field, $type).' in ('.$placeholders.'), false)';
            array_push($bindings, ...$group->map(fn ($value) => $this->jsonBinding($value))->all());
        }

        if ($clauses === []) {
            if (! $negate) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $sql = '('.implode(' or ', $clauses).')';
        $query->whereRaw($negate ? 'not '.$sql : $sql, $bindings);
    }

    /**
     * SQL for the value at $field in the data column, read as the given JSON
     * type: NULL where the value is missing or is another type. Reading it as
     * a type is what makes a comparison mean the same on every driver, so that
     * 10 > 9 compares numbers and "b" > "a" compares text.
     *
     * $field has passed validateFieldName(), so its segments are safe to write
     * into a JSON path.
     *
     * @param  'number'|'string'|'boolean'  $type
     */
    private function jsonValue(Builder $query, string $field, string $type): string
    {
        $column = $query->getQuery()->getGrammar()->wrap('data');
        $segments = explode('.', $field);
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $path = "'{".implode(',', $segments)."}'";
            $jsonType = "json_typeof({$column}#>{$path})";
            $text = "({$column}#>>{$path})";

            return match ($type) {
                'number' => "(case when {$jsonType} = 'number' then {$text}::numeric end)",
                'string' => "(case when {$jsonType} = 'string' then {$text} end)",
                'boolean' => "(case when {$jsonType} = 'boolean' then {$text} end)",
            };
        }

        $path = "'\$".implode('', array_map(fn (string $segment) => '."'.$segment.'"', $segments))."'";

        if ($driver === 'sqlite') {
            $jsonType = "json_type({$column}, {$path})";
            $value = "json_extract({$column}, {$path})";

            return match ($type) {
                'number' => "(case when {$jsonType} in ('integer', 'real') then {$value} end)",
                'string' => "(case when {$jsonType} = 'text' then {$value} end)",
                'boolean' => "(case when {$jsonType} in ('true', 'false') then {$jsonType} end)",
            };
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $extract = "json_extract({$column}, {$path})";
            $jsonType = "json_type({$extract})";
            $text = "json_unquote({$extract})";

            return match ($type) {
                'number' => "(case when {$jsonType} in ('INTEGER', 'UNSIGNED INTEGER', 'DOUBLE', 'DECIMAL') then cast({$text} as decimal(65, 30)) end)",
                'string' => "(case when {$jsonType} = 'STRING' then {$text} end)",
                'boolean' => "(case when {$jsonType} = 'BOOLEAN' then {$text} end)",
            };
        }

        throw new \RuntimeException("ctx.store.query does not support the [{$driver}] database driver.");
    }

    /**
     * The placeholder for a bound value of the given type. A number is cast in
     * SQL because PDO binds a float as text, and SQLite never finds a number
     * equal to text.
     */
    private function jsonPlaceholder(Builder $query, string $type): string
    {
        if ($type !== 'number') {
            return '?';
        }

        return match ($query->getConnection()->getDriverName()) {
            'sqlite' => 'cast(? as real)',
            'pgsql' => 'cast(? as numeric)',
            default => 'cast(? as decimal(65, 30))',
        };
    }

    /**
     * @return 'number'|'string'|'boolean'
     */
    private function jsonTypeOf(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            default => 'string',
        };
    }

    private function jsonBinding(mixed $value): int|float|string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => $value,
            is_scalar($value), $value === null => (string) $value,
            default => (string) json_encode($value),
        };
    }

    // -------------------------------------------------------------------------
    // Tickets
    // -------------------------------------------------------------------------

    private function ticketsFind(array $params): ?array
    {
        $id = $params['id'] ?? throw new \InvalidArgumentException('ctx.tickets.find requires id');
        $ticket = Ticket::find($id);

        return $ticket?->toArray();
    }

    private function ticketsQuery(array $params): array
    {
        $query = $this->whereFilters(Ticket::query(), $params['filter'] ?? [], self::TICKET_FILTERS, 'ctx.tickets.query');

        return $query->orderBy('id')->limit(self::QUERY_LIMIT)->get()->toArray();
    }

    /**
     * Equality filters on whitelisted keys only.
     */
    private function whereFilters(Builder $query, mixed $filter, array $columns, string $method): Builder
    {
        if (! is_array($filter)) {
            throw new \InvalidArgumentException("{$method} requires an object filter");
        }

        foreach ($filter as $key => $value) {
            $column = $columns[$key] ?? null;
            if ($column === null) {
                throw new \InvalidArgumentException("{$method} cannot filter by \"{$key}\"");
            }
            if ($value !== null && ! is_scalar($value)) {
                throw new \InvalidArgumentException("{$method} filter \"{$key}\" must be a single value");
            }
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return $query;
    }

    private function ticketsCreate(array $params): array
    {
        $data = $this->contractFields($params['data'] ?? null, self::TICKET_CREATE_FIELDS, 'ctx.tickets.create');
        $attributes = $this->ticketAttributes($data, 'ctx.tickets.create');
        if (! isset($attributes['subject'])) {
            throw new \InvalidArgumentException('ctx.tickets.create requires subject');
        }
        $attributes['description'] ??= '';
        $channel = $this->optionalText($data, 'channel', 'ctx.tickets.create') ?? TicketChannel::Api->value;
        $attributes['channel'] = TicketChannel::tryFrom($channel)
            ?? throw new \InvalidArgumentException("ctx.tickets.create: unknown channel \"{$channel}\"");

        if (isset($data['contact_id'])) {
            $attributes['contact_id'] = (is_scalar($data['contact_id']) ? Contact::find($data['contact_id'])?->getKey() : null)
                ?? throw new \InvalidArgumentException('ctx.tickets.create: no such contact');
        } elseif (($email = $this->optionalText($data, 'requester_email', 'ctx.tickets.create')) !== null) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('ctx.tickets.create: requester_email is not an email address');
            }
            $attributes['contact_id'] = Contact::findOrCreateByEmail(
                $email,
                $this->optionalText($data, 'requester_name', 'ctx.tickets.create'),
            )->getKey();
        }

        return Ticket::create($attributes)->toArray();
    }

    private function ticketsUpdate(array $params): array
    {
        $id = $params['id'] ?? throw new \InvalidArgumentException('ctx.tickets.update requires id');
        $data = $this->contractFields($params['data'] ?? null, self::TICKET_UPDATE_FIELDS, 'ctx.tickets.update');

        $ticket = Ticket::findOrFail($id);
        $ticket->update($this->ticketAttributes($data, 'ctx.tickets.update'));

        return $ticket->fresh()->toArray();
    }

    /**
     * Ticket columns from the contract fields a plugin sent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function ticketAttributes(array $data, string $method): array
    {
        $attributes = [];
        if (($subject = $this->optionalText($data, 'subject', $method) ?? $this->optionalText($data, 'title', $method)) !== null) {
            $attributes['subject'] = $subject;
        }
        if (array_key_exists('description', $data) || array_key_exists('body', $data)) {
            $description = $data['description'] ?? $data['body'] ?? null;
            if ($description !== null && ! is_string($description)) {
                throw new \InvalidArgumentException("{$method}: description must be a string");
            }
            $attributes['description'] = (string) $description;
        }
        if (($priority = $this->optionalText($data, 'priority', $method)) !== null) {
            $attributes['priority'] = TicketPriority::tryFrom($priority)
                ?? throw new \InvalidArgumentException("{$method}: unknown priority \"{$priority}\"");
        }
        if (($status = $this->optionalText($data, 'status', $method)) !== null) {
            $attributes['status'] = TicketStatus::tryFrom($status)
                ?? throw new \InvalidArgumentException("{$method}: unknown status \"{$status}\"");
        }
        if (array_key_exists('department_id', $data)) {
            $attributes['department_id'] = $data['department_id'] === null ? null
                : ((is_scalar($data['department_id']) ? Department::find($data['department_id'])?->getKey() : null)
                    ?? throw new \InvalidArgumentException("{$method}: no such department"));
        }
        if (array_key_exists('ticket_type', $data)) {
            $attributes['ticket_type'] = $this->optionalText($data, 'ticket_type', $method);
        }
        if (array_key_exists('metadata', $data)) {
            if ($data['metadata'] !== null && ! is_array($data['metadata'])) {
                throw new \InvalidArgumentException("{$method}: metadata must be an object");
            }
            $attributes['metadata'] = $data['metadata'];
        }

        return $attributes;
    }

    /**
     * The plugin's data, refused when it holds a key outside the contract,
     * so a plugin never sets a column the contract does not give it.
     *
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function contractFields(mixed $data, array $allowed, string $method): array
    {
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new \InvalidArgumentException("{$method} requires a data object");
        }
        $unknown = array_diff(array_map('strval', array_keys($data)), $allowed);
        if ($unknown !== []) {
            throw new \InvalidArgumentException("{$method} does not accept: ".implode(', ', $unknown));
        }

        return $data;
    }

    /**
     * A trimmed, non-empty string field, or null when absent or blank.
     *
     * @param  array<string, mixed>  $data
     */
    private function optionalText(array $data, string $key, string $method): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            throw new \InvalidArgumentException("{$method}: {$key} must be a string");
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    // -------------------------------------------------------------------------
    // Replies
    // -------------------------------------------------------------------------

    private function repliesFind(array $params): ?array
    {
        $id = $params['id'] ?? throw new \InvalidArgumentException('ctx.replies.find requires id');
        $reply = Reply::find($id);

        return $reply?->toArray();
    }

    private function repliesQuery(array $params): array
    {
        $filter = $params['filter'] ?? [];
        if (is_array($filter) && array_key_exists('is_internal', $filter) && is_scalar($filter['is_internal'])) {
            $filter['is_internal'] = (bool) $filter['is_internal'];
        }
        $query = $this->whereFilters(Reply::query(), $filter, self::REPLY_FILTERS, 'ctx.replies.query');

        return $query->orderBy('id')->limit(self::QUERY_LIMIT)->get()->toArray();
    }

    private function repliesCreate(array $params): array
    {
        $data = $this->contractFields($params['data'] ?? null, self::REPLY_CREATE_FIELDS, 'ctx.replies.create');
        $ticket = isset($data['ticket_id']) && is_scalar($data['ticket_id']) ? Ticket::find($data['ticket_id']) : null;
        if ($ticket === null) {
            throw new \InvalidArgumentException('ctx.replies.create requires an existing ticket_id');
        }
        $body = $this->optionalText($data, 'body', 'ctx.replies.create')
            ?? throw new \InvalidArgumentException('ctx.replies.create requires body');
        $internal = filter_var($data['is_internal'] ?? false, FILTER_VALIDATE_BOOL);

        // The author is the acting user (the signed-in user of the request
        // the plugin is serving), or nobody: a plugin never names it.
        $reply = new Reply([
            'ticket_id' => $ticket->getKey(),
            'body' => $body,
            'is_internal_note' => $internal,
            'type' => $internal ? 'note' : 'reply',
        ]);
        if (($user = Auth::user()) instanceof Model) {
            $reply->author()->associate($user);
        }
        $reply->save();

        return $reply->toArray();
    }

    // -------------------------------------------------------------------------
    // Contacts (users)
    // -------------------------------------------------------------------------

    private function contactsFind(array $params): ?array
    {
        $id = $params['id'] ?? throw new \InvalidArgumentException('ctx.contacts.find requires id');
        $model = Escalated::userModel();
        $user = Escalated::findUser($id);

        return $user?->toArray();
    }

    private function contactsFindByEmail(array $params): ?array
    {
        $email = $params['email'] ?? throw new \InvalidArgumentException('ctx.contacts.findByEmail requires email');
        $model = Escalated::userModel();
        $user = Escalated::userQuery()->where('email', $email)->first();

        return $user?->toArray();
    }

    private function contactsCreate(array $params): array
    {
        abort_if(app(TenantContext::class)->enabled(), 403, 'Provision host identities through the host application.');
        $data = $params['data'] ?? throw new \InvalidArgumentException('ctx.contacts.create requires data');
        $data = array_intersect_key($data, array_flip(self::PLUGIN_CONTACT_FILLABLE));
        $model = Escalated::userModel();
        $user = $model::create($data);

        return $user->toArray();
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    private function tagsAll(): array
    {
        return Tag::all()->toArray();
    }

    private function tagsCreate(array $params): array
    {
        $data = $this->contractFields($params['data'] ?? null, self::TAG_CREATE_FIELDS, 'ctx.tags.create');
        $name = $this->optionalText($data, 'name', 'ctx.tags.create')
            ?? throw new \InvalidArgumentException('ctx.tags.create requires name');
        $tag = Tag::create(array_filter([
            'name' => $name,
            'slug' => $this->optionalText($data, 'slug', 'ctx.tags.create'),
            'color' => $this->optionalText($data, 'color', 'ctx.tags.create'),
        ], fn ($value) => $value !== null));

        return $tag->toArray();
    }

    // -------------------------------------------------------------------------
    // Departments
    // -------------------------------------------------------------------------

    private function departmentsAll(): array
    {
        return Department::all()->toArray();
    }

    private function departmentsFind(array $params): ?array
    {
        $id = $params['id'] ?? throw new \InvalidArgumentException('ctx.departments.find requires id');
        $department = Department::find($id);

        return $department?->toArray();
    }

    // -------------------------------------------------------------------------
    // Agents
    // -------------------------------------------------------------------------

    private function agentsAll(): array
    {
        $model = Escalated::userModel();

        // Return users that have agent or admin gate access — we rely on the
        // application's user model structure. The simplest approach is to
        // return all users; the host application can filter via gates if needed.
        return Escalated::userQuery()->select('id', 'name', 'email')->get()->toArray();
    }

    private function agentsFind(array $params): ?array
    {
        $id = $params['id'] ?? throw new \InvalidArgumentException('ctx.agents.find requires id');
        $model = Escalated::userModel();
        $user = Escalated::findUser($id);

        return $user?->toArray();
    }

    // -------------------------------------------------------------------------
    // Broadcast
    // -------------------------------------------------------------------------

    private function broadcastToChannel(array $params): null
    {
        $channel = $params['channel'] ?? throw new \InvalidArgumentException('ctx.broadcast.toChannel requires channel');
        $event = $params['event'] ?? throw new \InvalidArgumentException('ctx.broadcast.toChannel requires event');
        $data = $params['data'] ?? [];

        $channel = "plugin.{$channel}";

        Broadcast::channel($channel, fn () => true);
        broadcast(new PluginBroadcastEvent($channel, $event, $data));

        return null;
    }

    private function broadcastToUser(array $params): null
    {
        $userId = $params['userId'] ?? throw new \InvalidArgumentException('ctx.broadcast.toUser requires userId');
        $event = $params['event'] ?? throw new \InvalidArgumentException('ctx.broadcast.toUser requires event');
        $data = $params['data'] ?? [];
        $channel = "private-user.{$userId}";

        broadcast(new PluginBroadcastEvent($channel, $event, $data));

        return null;
    }

    private function broadcastToTicket(array $params): null
    {
        $ticketId = $params['ticketId'] ?? throw new \InvalidArgumentException('ctx.broadcast.toTicket requires ticketId');
        $event = $params['event'] ?? throw new \InvalidArgumentException('ctx.broadcast.toTicket requires event');
        $data = $params['data'] ?? [];
        $channel = "private-ticket.{$ticketId}";

        broadcast(new PluginBroadcastEvent($channel, $event, $data));

        return null;
    }

    // -------------------------------------------------------------------------
    // Misc
    // -------------------------------------------------------------------------

    private function emit(array $params): mixed
    {
        $hook = $params['hook'] ?? throw new \InvalidArgumentException('ctx.emit requires hook');
        $data = $params['data'] ?? [];

        if ($hook === 'slack.message.received') {
            // Re-authenticate the original bytes at the host; no plugin's
            // asserted tenant, user or verification flag is authoritative.
            return app(SlackInbox::class)->receivePlugin($data);
        }

        // A plugin may not impersonate a host event or lifecycle action.
        if (PluginEvents::isReserved($hook)) {
            Log::warning("Escalated PluginBridge: dropped ctx.emit of reserved hook '{$hook}'", [
                'plugin' => $this->currentPlugin,
            ]);

            return null;
        }

        if ($this->bridge !== null) {
            $this->bridge->dispatchAction($hook, $data);
        }

        return null;
    }

    private function ctxLog(array $params): null
    {
        $level = $params['level'] ?? 'info';
        $message = $params['message'] ?? '';
        $context = $params['data'] ?? [];

        $context['plugin'] = $params['plugin'] ?? $this->currentPlugin;

        match ($level) {
            'debug' => Log::debug($message, $context),
            'warn', 'warning' => Log::warning($message, $context),
            'error' => Log::error($message, $context),
            default => Log::info($message, $context),
        };

        return null;
    }

    private function currentUser(): ?array
    {
        $user = Auth::user();

        return $user?->toArray();
    }
}
