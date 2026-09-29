<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Validate associations on the database that actually owns the referenced model. */
class TenantReferences
{
    private const LOCAL_KEYS = [
        'ticket_id' => Models\Ticket::class,
        'parent_ticket_id' => Models\Ticket::class,
        'child_ticket_id' => Models\Ticket::class,
        'merged_into_id' => Models\Ticket::class,
        'reply_id' => Models\Reply::class,
        'department_id' => Models\Department::class,
        'sla_policy_id' => Models\SlaPolicy::class,
        'contact_id' => Models\Contact::class,
        'workflow_id' => Models\Workflow::class,
        'webhook_id' => Models\Webhook::class,
        'custom_field_id' => Models\CustomField::class,
        'object_id' => Models\CustomObject::class,
        'schedule_id' => Models\BusinessSchedule::class,
        'category_id' => Models\ArticleCategory::class,
        'import_job_id' => Models\ImportJob::class,
        'side_conversation_id' => Models\SideConversation::class,
        'role_id' => Models\Role::class,
        'permission_id' => Models\Permission::class,
        'tag_id' => Models\Tag::class,
        'skill_id' => Models\Skill::class,
        'list_id' => Models\Newsletter\NewsletterList::class,
        'target_list_id' => Models\Newsletter\NewsletterList::class,
        'newsletter_id' => Models\Newsletter\Newsletter::class,
        'template_id' => Models\Newsletter\NewsletterTemplate::class,
    ];

    private const HOST_KEYS = ['user_id', 'agent_id', 'assigned_to', 'created_by', 'sent_by', 'author_id', 'snoozed_by', 'added_by'];

    private const MORPHS = [
        'tickets' => ['requester'], 'replies' => ['author'], 'attachments' => ['attachable'],
        'ticket_subjects' => ['subject'], 'ticket_activities' => ['causer'],
        'satisfaction_ratings' => ['rated_by'], 'api_tokens' => ['tokenable'],
        'custom_field_values' => ['entity'],
        // Audit identifiers describe historical (including deleted) records or
        // a report type with ID 0. They are not live foreign-key assignments;
        // resolving auditable() still uses the mandatory tenant relation scope.
    ];

    public function validate(Model $model, array $values, bool $historical = false): void
    {
        $context = app(TenantContext::class);
        if (! $context->enabled() || ! TenantTables::contains($model->getTable())) {
            return;
        }
        $tenant = $context->id();
        foreach (array_keys($values) as $column) {
            if (str_contains((string) $column, '.')) {
                throw new AuthorizationException('Tenant writes require unqualified column names.');
            }
        }
        if (array_key_exists('tenant_id', $values) && $values['tenant_id'] !== $tenant) {
            throw new AuthorizationException('Tenant identity cannot be assigned by record data.');
        }
        $morphFields = [];
        foreach (self::MORPHS as $table => $names) {
            if ($model->getTable() !== Escalated::table($table)) {
                continue;
            }
            foreach ($names as $name) {
                $idKey = $name.'_id';
                $typeKey = $name.'_type';
                $morphFields[] = $idKey;
                if (! array_key_exists($idKey, $values) && ! array_key_exists($typeKey, $values)) {
                    continue;
                }
                $id = $values[$idKey] ?? ($model->exists ? $model->getAttribute($idKey) : null);
                $type = $values[$typeKey] ?? ($model->exists ? $model->getAttribute($typeKey) : null);
                if ($id === null && $type === null) {
                    continue;
                }
                if (! is_string($type) || $id === null) {
                    throw new AuthorizationException('Polymorphic references require an identity and type.');
                }
                $class = Relation::getMorphedModel($type) ?? $type;
                if (! is_a($class, Model::class, true)) {
                    throw new AuthorizationException('Invalid referenced model type.');
                }
                $this->assertReference(new $class, $id, $historical);
            }
        }
        $keys = self::LOCAL_KEYS;
        if ($model->getTable() === Escalated::table('article_categories')) {
            $keys['parent_id'] = Models\ArticleCategory::class;
        }
        foreach ($keys as $key => $class) {
            if (array_key_exists($key, $values) && $values[$key] !== null) {
                $this->assertReference(new $class, $values[$key], $historical);
            }
        }
        foreach (array_diff(self::HOST_KEYS, $morphFields) as $key) {
            if (array_key_exists($key, $values) && $values[$key] !== null) {
                $this->assertReference(Escalated::newUserModel(), $values[$key]);
            }
        }
    }

    public function assertReference(Model $model, mixed $id, bool $historical = false): void
    {
        if (! is_int($id) && ! is_string($id)) {
            throw new AuthorizationException('A referenced identity must be a scalar key.');
        }
        if ($model->getKeyType() === 'int' && ! ctype_digit((string) $id)) {
            throw new AuthorizationException('Invalid referenced identity.');
        }
        $context = app(TenantContext::class);
        $query = $model->newQuery();
        $local = TenantTables::contains($model->getTable());
        if ($historical && $local && in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            // Legacy history may still point to a soft-deleted local record.
            // Keep the mandatory tenant scope and every host membership check.
            $query->withTrashed();
        }
        $platformPermission = $model instanceof Models\Permission;
        if (! $local && ! $platformPermission) {
            $context->scopeHost($query);
        }
        $record = $query->whereKey($id)->first();
        if (! $record || (! $local && ! $platformPermission && ! $context->resolver()->canReference($record, $context->id()))) {
            throw new AuthorizationException('The referenced record is unavailable in this tenant.');
        }
    }

    public function isReferenceColumn(string $column): bool
    {
        return $column === 'tenant_id' || $column === 'id' || str_ends_with($column, '_id')
            || in_array($column, ['assigned_to', 'created_by', 'sent_by'], true);
    }
}
