<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\CreatesAgentTickets;
use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\EscalatedManager;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentTicketCreator
{
    public static function assertSupported(): CreatesAgentTickets
    {
        if (config('escalated.mode', 'self-hosted') !== 'self-hosted') {
            throw ValidationException::withMessages(['unsupported_driver' => 'This operation requires self-hosted mode.']);
        }
        $driver = app(EscalatedManager::class)->driver();
        if (! $driver instanceof CreatesAgentTickets) {
            throw ValidationException::withMessages(['unsupported_driver' => 'This operation requires a self-hosted driver supporting atomic agent creation.']);
        }

        return $driver;
    }

    public static function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:65535'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent,critical'],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Department::class, 'id')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists(Tag::class, 'id')],
            'external_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array', function ($field, $value, $fail) {
                try {
                    $json = json_encode($value, JSON_THROW_ON_ERROR);
                    if (strlen($json) > max(1, (int) config('escalated.api.max_metadata_bytes', 16384))) {
                        $fail('Ticket metadata exceeds the configured JSON size limit.');
                    }
                } catch (\JsonException) {
                    $fail('Ticket metadata must contain valid JSON values.');
                }
            }],
            'requester' => ['sometimes', 'array:id,name,email', function ($field, $value, $fail) {
                if (! is_array($value)) {
                    return;
                }
                if (array_key_exists('id', $value)) {
                    if (count($value) !== 1) {
                        $fail('Use either a requester ID or a name and email.');
                    }
                } elseif (! isset($value['name'], $value['email']) || ! is_string($value['name']) || trim($value['name']) === '') {
                    $fail('A named requester requires both name and email.');
                }
            }],
            'requester.id' => ['sometimes', 'required', function ($field, $value, $fail) {
                try {
                    app(TicketSubjectResolver::class)->validateKey($value, $field);
                } catch (ValidationException) {
                    $fail('A valid requester key is required.');
                }
            }],
            'requester.name' => ['sometimes', 'required', 'string', 'max:255'],
            'requester.email' => ['sometimes', 'required', 'email', 'max:255'],
        ] + TicketSubjectResolver::rules();
    }

    public function create(Model&Ticketable $actor, array $data): Ticket
    {
        self::assertSupported();
        $data = Validator::make($data, self::rules())->validate();
        $context = app(TenantContext::class);
        $requester = $actor;
        $named = $data['requester'] ?? null;
        if (isset($named['id'])) {
            $model = Escalated::newUserModel();
            app(TicketSubjectResolver::class)->validateKey($named['id'], 'requester.id', $model);
            $requester = $context->scopeHost($model->newQuery())->whereKey($named['id'])->first();
            if (! $requester instanceof Ticketable || ! $requester instanceof Model
                || ($context->enabled() && ! $context->resolver()->canReference($requester, $context->id()))) {
                throw ValidationException::withMessages(['requester.id' => 'This requester is unavailable.']);
            }
        }
        $subjects = app(TicketSubjectResolver::class)->resolveMany($data['subjects'] ?? [], $actor);

        return Escalated::db()->transaction(function () use ($actor, $data, $named, $requester, $subjects) {
            $ticket = new Ticket([
                'subject' => $data['subject'], 'description' => $data['description'],
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::from($data['priority'] ?? config('escalated.default_priority', 'medium')),
                'ticket_type' => 'question', 'channel' => 'web',
                'department_id' => $data['department_id'] ?? null,
                'metadata' => $data['metadata'] ?? null, 'external_reference' => $data['external_reference'] ?? null,
            ]);
            if ($named !== null && ! array_key_exists('id', $named)) {
                $contact = Contact::findOrCreateByEmail($named['email'], trim($named['name']));
                $ticket->fill(['contact_id' => $contact->id, 'guest_email' => $contact->email, 'guest_name' => $contact->name]);
            } else {
                $ticket->requester()->associate($requester);
            }
            $ticket->deferCreatedEvent = true;
            $ticket->save();
            if (! empty($data['tags'])) {
                $ticket->tags()->sync($data['tags']);
            }
            app(TicketSubjectService::class)->replace($ticket, $subjects, $actor);
            $ticket->logActivity(ActivityType::StatusChanged, $actor, ['new_status' => TicketStatus::Open->value]);
            $ticket->dispatchCreatedAfterCommit();

            return $ticket->load(['requester', 'contact', 'assignee', 'department', 'tags', 'subjects.subject']);
        });
    }
}
