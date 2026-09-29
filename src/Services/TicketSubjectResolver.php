<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

class TicketSubjectResolver
{
    /** Only configured classes may be instantiated from HTTP input. */
    public function allowedTypes(): array
    {
        $types = [];
        foreach ((array) config('escalated.ticket_subjects.types', []) as $alias => $value) {
            $class = is_string($value) ? (Relation::getMorphedModel($value) ?? $value) : null;
            if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }
            $tokens = [$value, $class, (new $class)->getMorphClass()];
            if (is_string($alias)) {
                $tokens[] = $alias;
            }
            foreach ($tokens as $token) {
                if (isset($types[$token]) && $types[$token] !== $class) {
                    throw new \LogicException('Ticket subject aliases must identify exactly one configured class.');
                }
                $types[$token] = $class;
            }
        }

        return $types;
    }

    public function resolve(array $entry, ?Model $actor, ?Ticket $ticket = null, string $field = 'subjects'): Model
    {
        $prefix = $field === '' ? '' : $field.'.';
        $class = $this->allowedTypes()[$entry['type']] ?? null;
        if (! $class) {
            throw ValidationException::withMessages([$prefix.'type' => 'This ticket subject type is not enabled.']);
        }
        $model = new $class;
        $this->validateKey($entry['id'], $prefix.'id', $model);
        $subject = app(TenantContext::class)->scopeHost($model->newQuery())->whereKey($entry['id'])->first();
        if (! $subject || ! $this->canReference($subject, $actor, $ticket, 'attach')) {
            throw ValidationException::withMessages([$prefix.'id' => 'This subject is unavailable.']);
        }

        return $subject;
    }

    public function resolveMany(array $entries, ?Model $actor, ?Ticket $ticket = null): array
    {
        $resolved = [];
        $seen = [];
        foreach ($entries as $index => $entry) {
            $subject = $this->resolve($entry, $actor, $ticket, 'subjects.'.$index);
            $key = $subject->getMorphClass().':'.(string) $subject->getKey();
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['subjects.'.$index.'.id' => 'A subject may appear only once per ticket.']);
            }
            $seen[$key] = true;
            $resolved[] = [$subject, $entry['role'] ?? null];
        }

        return $resolved;
    }

    public function canReference(Model $subject, ?Model $actor, ?Ticket $ticket, string $purpose): bool
    {
        $context = app(TenantContext::class);
        if ($context->enabled() && ! $context->resolver()->canReference($subject, $context->id())) {
            return false;
        }
        $authorize = config('escalated.ticket_subjects.authorize');

        return $authorize === null || (is_callable($authorize) && $authorize($actor, $subject, $ticket, $purpose) === true);
    }

    public function validateKey(mixed $id, string $field, ?Model $model = null): void
    {
        $valid = (is_int($id) || is_string($id)) && (string) $id !== '' && strlen((string) $id) <= 255;
        if ($valid && $model?->getKeyType() === 'int') {
            $digits = ltrim((string) $id, '0');
            $valid = ctype_digit((string) $id) && (strlen($digits) < 19
                || (strlen($digits) === 19 && strcmp($digits, '9223372036854775807') <= 0));
        }
        if (! $valid) {
            throw ValidationException::withMessages([$field => 'A valid integer or string model key is required.']);
        }
    }

    public function assertAllowedModel(Model $subject): void
    {
        if ((array) config('escalated.ticket_subjects.types', []) !== []
            && ! in_array($subject::class, $this->allowedTypes(), true)) {
            throw new \InvalidArgumentException('This model is not an allowed ticket subject.');
        }
        $this->validateKey($subject->getKey(), 'subjects.id', $subject);
        if (! $subject->exists) {
            throw new \InvalidArgumentException('A ticket subject must be persisted.');
        }
    }

    public static function rules(bool $required = false): array
    {
        return [
            'subjects' => [$required ? 'present' : 'sometimes', 'array', 'list', 'max:'.max(1, (int) config('escalated.ticket_subjects.max_per_ticket', 100))],
            'subjects.*' => ['required', 'array:type,id,role'],
            'subjects.*.type' => ['required', 'string', 'max:255'],
            'subjects.*.id' => ['required', function ($field, $value, $fail) {
                try {
                    app(self::class)->validateKey($value, $field);
                } catch (ValidationException) {
                    $fail('A valid integer or string model key is required.');
                }
            }],
            'subjects.*.role' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
