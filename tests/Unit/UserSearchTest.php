<?php

namespace Escalated\Laravel\Tests\Unit;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Escalated\Laravel\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regression tests for https://github.com/escalated-dev/escalated-laravel/issues/63
 *
 * When the host's `users` table doesn't have a `name` column (e.g. it
 * uses split `first_name` / `last_name` or any other schema), the
 * ticket search must silently fall back to searching `email` instead
 * of raising `column users.name does not exist`.
 */
class UserSearchTest extends TestCase
{
    protected function tearDown(): void
    {
        Escalated::flushColumnCache();
        parent::tearDown();
    }

    public function test_search_uses_configured_display_column_when_it_exists(): void
    {
        $user = TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('x'),
        ]);

        Ticket::factory()->create([
            'requester_type' => TestUser::class,
            'requester_id' => $user->id,
            'subject' => 'Broken widget',
        ]);

        $results = Ticket::search('Alice')->get();

        $this->assertCount(1, $results);
    }

    public function test_search_falls_back_to_email_when_display_column_missing(): void
    {
        // Simulate a host whose users table lacks a `name` column by
        // dropping it before the cache is warmed.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
        Escalated::flushColumnCache();

        // Use a model subclass so we can insert without `name` (the
        // fixture model's `$fillable` and created_at timestamps still
        // work; `name` just isn't set).
        $user = new class extends Model
        {
            protected $table = 'users';

            protected $guarded = [];

            public $timestamps = true;
        };
        $user->fill([
            'email' => 'bob@example.com',
            'password' => bcrypt('x'),
        ])->save();

        Ticket::factory()->create([
            'requester_type' => TestUser::class,
            'requester_id' => $user->id,
            'subject' => 'Widget fell off',
        ]);

        // Search by email substring — works even though the column
        // `name` doesn't exist on the table. Prior to the fix this
        // threw "column users.name does not exist" against Postgres
        // and silently returned an empty result on sqlite (because
        // `WHERE name LIKE ...` evaluates to NULL against a missing
        // column in sqlite's lenient mode).
        $results = Ticket::search('bob')->get();
        $this->assertCount(1, $results);
    }

    public function test_apply_user_search_helper_skips_missing_column(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
        Escalated::flushColumnCache();

        // Inspect the generated SQL to confirm it no longer references
        // `users.name`.
        $query = TestUser::query()->getQuery();
        Escalated::applyUserSearch(TestUser::query()->getQuery(), 'test');

        // Rebuild a fresh builder to capture the where clauses the
        // helper applies.
        $builder = TestUser::query()->getQuery();
        Escalated::applyUserSearch($builder, 'test');

        $sql = $builder->toSql();
        $this->assertStringNotContainsString('"name"', $sql);
        $this->assertStringContainsString('"email"', $sql);
    }

    public function test_user_options_falls_back_to_email_when_column_missing(): void
    {
        TestUser::create([
            'name' => 'ignored', // will be dropped below, but required by the existing schema for now
            'email' => 'ann@example.com',
            'password' => bcrypt('x'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
        Escalated::flushColumnCache();

        $options = Escalated::userOptions();

        $this->assertNotEmpty($options);
        $this->assertContains('ann@example.com', array_values($options));
    }
}
