<?php

use Escalated\Laravel\Database\Migration;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Tenancy\TenantTables;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    // Credential/capability tokens and ticket references remain globally unique.
    private array $uniqueKeys = [
        'departments' => ['slug'], 'tags' => ['slug'], 'inbound_emails' => ['message_id'],
        'settings' => ['key'], 'custom_fields' => ['slug'], 'ticket_statuses' => ['slug'],
        'roles' => ['slug'], 'skills' => ['slug'], 'custom_objects' => ['slug'],
        'article_categories' => ['slug'], 'articles' => ['slug'], 'contacts' => ['email'],
        'agent_profiles' => ['user_id'], 'agent_capacity' => ['user_id', 'channel'],
    ];

    public function up(): void
    {
        $schema = Escalated::schema();
        foreach (TenantTables::NAMES as $name) {
            $table = Escalated::table($name);
            $index = 'esc_tenant_'.substr(sha1($table), 0, 12);
            $schema->table($table, function (Blueprint $blueprint) use ($index) {
                $column = $blueprint->string('tenant_id', 128)->default('');
                if (in_array(Escalated::db()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $column->collation('utf8mb4_bin');
                } elseif (Escalated::db()->getDriverName() === 'pgsql') {
                    $column->collation('C');
                }
                $blueprint->index('tenant_id', $index);
            });

            $columns = $this->uniqueKeys[$name] ?? null;
            if ($columns === null) {
                continue;
            }
            foreach ($schema->getIndexes($table) as $existing) {
                if ($existing['unique'] && ! $existing['primary'] && $existing['columns'] === $columns) {
                    $schema->table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($existing['name']));
                }
            }
            $schema->table($table, fn (Blueprint $blueprint) => $blueprint->unique(['tenant_id', ...$columns], $index.'_unique'));
        }
    }

    public function down(): void
    {
        // Collapsing tenants can collide on email/slug keys and expose data. Require
        // explicit archival/repartitioning instead of silently undoing the boundary.
        foreach (TenantTables::NAMES as $name) {
            if (Escalated::db()->table(Escalated::table($name))->where('tenant_id', '!=', '')->exists()) {
                throw new RuntimeException('Cannot remove tenant namespaces while assigned tenant data exists.');
            }
        }

        $schema = Escalated::schema();
        foreach (TenantTables::NAMES as $name) {
            $table = Escalated::table($name);
            $index = 'esc_tenant_'.substr(sha1($table), 0, 12);
            $schema->table($table, function (Blueprint $blueprint) use ($name, $index) {
                if (isset($this->uniqueKeys[$name])) {
                    $blueprint->dropUnique($index.'_unique');
                    $blueprint->unique($this->uniqueKeys[$name]);
                }
                $blueprint->dropIndex($index);
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};
