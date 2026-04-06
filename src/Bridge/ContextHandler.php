<?php

namespace Escalated\Laravel\Bridge;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\PluginStoreRecord;
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
    private const PLUGIN_TICKET_FILLABLE = [
        'subject', 'body', 'priority', 'status', 'department_id', 'ticket_type',
    ];

    private const PLUGIN_CONTACT_FILLABLE = ['name', 'email'];

    /**
     * The plugin name that is currently executing (set before each dispatch).
     */
    private string $currentPlugin = '';

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
    }

    /**
     * Dispatch a ctx.* method call from the runtime to the appropriate handler.
     *
     * @param  string  $method  e.g. "ctx.tickets.find"
     * @param  array   $params
     * @return mixed
     */
    public function handle(string $method, array $params): mixed
    {
        return match (true) {
            // Config
            $method === 'ctx.config.all'    => $this->configAll($params),
            $method === 'ctx.config.get'    => $this->configGet($params),
            $method === 'ctx.config.set'    => $this->configSet($params),

            // Store
            $method === 'ctx.store.get'     => $this->storeGet($params),
            $method === 'ctx.store.set'     => $this->storeSet($params),
            $method === 'ctx.store.query'   => $this->storeQuery($params),
            $method === 'ctx.store.insert'  => $this->storeInsert($params),
            $method === 'ctx.store.update'  => $this->storeUpdate($params),
            $method === 'ctx.store.delete'  => $this->storeDelete($params),

            // Tickets
            $method === 'ctx.tickets.find'   => $this->ticketsFind($params),
            $method === 'ctx.tickets.query'  => $this->ticketsQuery($params),
            $method === 'ctx.tickets.create' => $this->ticketsCreate($params),
            $method === 'ctx.tickets.update' => $this->ticketsUpdate($params),

            // Replies
            $method === 'ctx.replies.find'   => $this->repliesFind($params),
            $method === 'ctx.replies.query'  => $this->repliesQuery($params),
            $method === 'ctx.replies.create' => $this->repliesCreate($params),

            // Contacts (users)
            $method === 'ctx.contacts.find'        => $this->contactsFind($params),
            $method === 'ctx.contacts.findByEmail' => $this->contactsFindByEmail($params),
            $method === 'ctx.contacts.create'      => $this->contactsCreate($params),

            // Tags
            $method === 'ctx.tags.all'    => $this->tagsAll(),
            $method === 'ctx.tags.create' => $this->tagsCreate($params),

            // Departments
            $method === 'ctx.departments.all'  => $this->departmentsAll(),
            $method === 'ctx.departments.find' => $this->departmentsFind($params),

            // Agents
            $method === 'ctx.agents.all'  => $this->agentsAll(),
            $method === 'ctx.agents.find' => $this->agentsFind($params),

            // Broadcast
            $method === 'ctx.broadcast.toChannel' => $this->broadcastToChannel($params),
            $method === 'ctx.broadcast.toUser'    => $this->broadcastToUser($params),
            $method === 'ctx.broadcast.toTicket'  => $this->broadcastToTicket($params),

            // Misc
            $method === 'ctx.emit'        => $this->emit($params),
            $method === 'ctx.log'         => $this->ctxLog($params),
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
        $key    = $params['key'] ?? throw new \InvalidArgumentException('ctx.config.get requires key');

        return $this->getPluginConfig($plugin)[$key] ?? null;
    }

    private function configSet(array $params): null
    {
        $plugin = $params['plugin'] ?? $this->currentPlugin;
        $data   = $params['data'] ?? throw new \InvalidArgumentException('ctx.config.set requires data');

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
        $merged   = array_merge($existing, $data);

        PluginStoreRecord::updateOrCreate(
            [
                'plugin'     => $plugin,
                'collection' => '__config__',
                'key'        => '__config__',
            ],
            ['data' => $merged]
        );
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    private function storeGet(array $params): mixed
    {
        $plugin     = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.get requires collection');
        $key        = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.get requires key');

        $record = PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', $collection)
            ->where('key', $key)
            ->first();

        return $record?->data;
    }

    private function storeSet(array $params): null
    {
        $plugin     = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.set requires collection');
        $key        = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.set requires key');
        $value      = $params['value'] ?? null;

        PluginStoreRecord::updateOrCreate(
            ['plugin' => $plugin, 'collection' => $collection, 'key' => $key],
            ['data'   => $value]
        );

        return null;
    }

    private function storeQuery(array $params): array
    {
        $plugin     = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.query requires collection');
        $filter     = $params['filter'] ?? [];
        $options    = $params['options'] ?? [];

        $query = PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', $collection);

        foreach ($filter as $field => $condition) {
            if (is_array($condition)) {
                // Operator conditions: { $gt: 10 }, { $in: [1,2,3] }, etc.
                foreach ($condition as $op => $val) {
                    $this->applyJsonOperator($query, $field, $op, $val);
                }
            } else {
                // Simple equality
                $query->whereJsonContains("data->{$field}", $condition);
            }
        }

        if (isset($options['orderBy'])) {
            $this->validateFieldName($options['orderBy']);
            $direction = in_array(strtolower($options['order'] ?? 'asc'), ['asc', 'desc']) ? strtolower($options['order']) : 'asc';
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$options['orderBy']}')) {$direction}");
        }

        if (isset($options['limit'])) {
            $query->limit((int) $options['limit']);
        }

        return $query->get()->map(fn ($r) => array_merge(['_id' => $r->id], is_array($r->data) ? $r->data : []))->values()->all();
    }

    private function storeInsert(array $params): array
    {
        $plugin     = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.insert requires collection');
        $data       = $params['data'] ?? throw new \InvalidArgumentException('ctx.store.insert requires data');

        $record = PluginStoreRecord::create([
            'plugin'     => $plugin,
            'collection' => $collection,
            'key'        => $data['key'] ?? null,
            'data'       => $data,
        ]);

        return array_merge(['_id' => $record->id], is_array($record->data) ? $record->data : []);
    }

    private function storeUpdate(array $params): array
    {
        $plugin     = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.update requires collection');
        $key        = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.update requires key');
        $data       = $params['data'] ?? throw new \InvalidArgumentException('ctx.store.update requires data');

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
        $plugin     = $params['plugin'] ?? $this->currentPlugin;
        $collection = $params['collection'] ?? throw new \InvalidArgumentException('ctx.store.delete requires collection');
        $key        = $params['key'] ?? throw new \InvalidArgumentException('ctx.store.delete requires key');

        PluginStoreRecord::where('plugin', $plugin)
            ->where('collection', $collection)
            ->where('key', $key)
            ->delete();

        return null;
    }

    /**
     * Apply a MongoDB-style query operator to a JSON column query.
     */
    private function validateFieldName(string $field): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $field)) {
            throw new \InvalidArgumentException("Invalid store field name: {$field}");
        }
    }

    private function applyJsonOperator(\Illuminate\Database\Eloquent\Builder $query, string $field, string $op, mixed $value): void
    {
        $this->validateFieldName($field);
        $extract = "JSON_UNQUOTE(JSON_EXTRACT(data, '$.{$field}'))";

        match ($op) {
            '$gt'  => $query->whereRaw("{$extract} > ?", [$value]),
            '$gte' => $query->whereRaw("{$extract} >= ?", [$value]),
            '$lt'  => $query->whereRaw("{$extract} < ?", [$value]),
            '$lte' => $query->whereRaw("{$extract} <= ?", [$value]),
            '$ne'  => $query->whereRaw("{$extract} != ?", [$value]),
            '$in'  => $query->whereIn(\Illuminate\Support\Facades\DB::raw($extract), (array) $value),
            '$nin' => $query->whereNotIn(\Illuminate\Support\Facades\DB::raw($extract), (array) $value),
            default => throw new \InvalidArgumentException("Unsupported store query operator: {$op}"),
        };
    }

    // -------------------------------------------------------------------------
    // Tickets
    // -------------------------------------------------------------------------

    private function ticketsFind(array $params): ?array
    {
        $id     = $params['id'] ?? throw new \InvalidArgumentException('ctx.tickets.find requires id');
        $ticket = \Escalated\Laravel\Models\Ticket::find($id);

        return $ticket?->toArray();
    }

    private function ticketsQuery(array $params): array
    {
        $filter = $params['filter'] ?? [];
        $query  = \Escalated\Laravel\Models\Ticket::query();

        foreach ($filter as $column => $value) {
            $query->where($column, $value);
        }

        return $query->get()->toArray();
    }

    private function ticketsCreate(array $params): array
    {
        $data   = $params['data'] ?? throw new \InvalidArgumentException('ctx.tickets.create requires data');
        $data   = array_intersect_key($data, array_flip(self::PLUGIN_TICKET_FILLABLE));
        $ticket = \Escalated\Laravel\Models\Ticket::create($data);

        return $ticket->toArray();
    }

    private function ticketsUpdate(array $params): array
    {
        $id   = $params['id'] ?? throw new \InvalidArgumentException('ctx.tickets.update requires id');
        $data = $params['data'] ?? throw new \InvalidArgumentException('ctx.tickets.update requires data');

        $ticket = \Escalated\Laravel\Models\Ticket::findOrFail($id);
        $data   = array_intersect_key($data, array_flip(self::PLUGIN_TICKET_FILLABLE));
        $ticket->update($data);

        return $ticket->fresh()->toArray();
    }

    // -------------------------------------------------------------------------
    // Replies
    // -------------------------------------------------------------------------

    private function repliesFind(array $params): ?array
    {
        $id    = $params['id'] ?? throw new \InvalidArgumentException('ctx.replies.find requires id');
        $reply = \Escalated\Laravel\Models\Reply::find($id);

        return $reply?->toArray();
    }

    private function repliesQuery(array $params): array
    {
        $filter = $params['filter'] ?? [];
        $query  = \Escalated\Laravel\Models\Reply::query();

        foreach ($filter as $column => $value) {
            $query->where($column, $value);
        }

        return $query->get()->toArray();
    }

    private function repliesCreate(array $params): array
    {
        $data  = $params['data'] ?? throw new \InvalidArgumentException('ctx.replies.create requires data');
        $reply = \Escalated\Laravel\Models\Reply::create($data);

        return $reply->toArray();
    }

    // -------------------------------------------------------------------------
    // Contacts (users)
    // -------------------------------------------------------------------------

    private function contactsFind(array $params): ?array
    {
        $id    = $params['id'] ?? throw new \InvalidArgumentException('ctx.contacts.find requires id');
        $model = Escalated::userModel();
        $user  = $model::find($id);

        return $user?->toArray();
    }

    private function contactsFindByEmail(array $params): ?array
    {
        $email = $params['email'] ?? throw new \InvalidArgumentException('ctx.contacts.findByEmail requires email');
        $model = Escalated::userModel();
        $user  = $model::where('email', $email)->first();

        return $user?->toArray();
    }

    private function contactsCreate(array $params): array
    {
        $data  = $params['data'] ?? throw new \InvalidArgumentException('ctx.contacts.create requires data');
        $data  = array_intersect_key($data, array_flip(self::PLUGIN_CONTACT_FILLABLE));
        $model = Escalated::userModel();
        $user  = $model::create($data);

        return $user->toArray();
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    private function tagsAll(): array
    {
        return \Escalated\Laravel\Models\Tag::all()->toArray();
    }

    private function tagsCreate(array $params): array
    {
        $data = $params['data'] ?? throw new \InvalidArgumentException('ctx.tags.create requires data');
        $tag  = \Escalated\Laravel\Models\Tag::create($data);

        return $tag->toArray();
    }

    // -------------------------------------------------------------------------
    // Departments
    // -------------------------------------------------------------------------

    private function departmentsAll(): array
    {
        return \Escalated\Laravel\Models\Department::all()->toArray();
    }

    private function departmentsFind(array $params): ?array
    {
        $id         = $params['id'] ?? throw new \InvalidArgumentException('ctx.departments.find requires id');
        $department = \Escalated\Laravel\Models\Department::find($id);

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
        return $model::select('id', 'name', 'email')->get()->toArray();
    }

    private function agentsFind(array $params): ?array
    {
        $id    = $params['id'] ?? throw new \InvalidArgumentException('ctx.agents.find requires id');
        $model = Escalated::userModel();
        $user  = $model::find($id);

        return $user?->toArray();
    }

    // -------------------------------------------------------------------------
    // Broadcast
    // -------------------------------------------------------------------------

    private function broadcastToChannel(array $params): null
    {
        $channel = $params['channel'] ?? throw new \InvalidArgumentException('ctx.broadcast.toChannel requires channel');
        $event   = $params['event'] ?? throw new \InvalidArgumentException('ctx.broadcast.toChannel requires event');
        $data    = $params['data'] ?? [];

        $channel = "plugin.{$channel}";

        Broadcast::channel($channel, fn () => true);
        broadcast(new \Escalated\Laravel\Bridge\Events\PluginBroadcastEvent($channel, $event, $data));

        return null;
    }

    private function broadcastToUser(array $params): null
    {
        $userId  = $params['userId'] ?? throw new \InvalidArgumentException('ctx.broadcast.toUser requires userId');
        $event   = $params['event'] ?? throw new \InvalidArgumentException('ctx.broadcast.toUser requires event');
        $data    = $params['data'] ?? [];
        $channel = "private-user.{$userId}";

        broadcast(new \Escalated\Laravel\Bridge\Events\PluginBroadcastEvent($channel, $event, $data));

        return null;
    }

    private function broadcastToTicket(array $params): null
    {
        $ticketId = $params['ticketId'] ?? throw new \InvalidArgumentException('ctx.broadcast.toTicket requires ticketId');
        $event    = $params['event'] ?? throw new \InvalidArgumentException('ctx.broadcast.toTicket requires event');
        $data     = $params['data'] ?? [];
        $channel  = "private-ticket.{$ticketId}";

        broadcast(new \Escalated\Laravel\Bridge\Events\PluginBroadcastEvent($channel, $event, $data));

        return null;
    }

    // -------------------------------------------------------------------------
    // Misc
    // -------------------------------------------------------------------------

    private function emit(array $params): null
    {
        $hook = $params['hook'] ?? throw new \InvalidArgumentException('ctx.emit requires hook');
        $data = $params['data'] ?? [];

        if ($this->bridge !== null) {
            $this->bridge->dispatchAction($hook, $data);
        }

        return null;
    }

    private function ctxLog(array $params): null
    {
        $level   = $params['level'] ?? 'info';
        $message = $params['message'] ?? '';
        $context = $params['data'] ?? [];

        $context['plugin'] = $params['plugin'] ?? $this->currentPlugin;

        match ($level) {
            'debug'   => Log::debug($message, $context),
            'warn', 'warning' => Log::warning($message, $context),
            'error'   => Log::error($message, $context),
            default   => Log::info($message, $context),
        };

        return null;
    }

    private function currentUser(): ?array
    {
        $user = Auth::user();

        return $user?->toArray();
    }
}
