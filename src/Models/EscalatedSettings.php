<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class EscalatedSettings extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('settings');
    }

    /**
     * Every setting, read once and kept until flushed, per connection and
     * tenant.
     *
     * A page reads several settings (the shared Inertia props alone read
     * five), and each read was its own query. They are read together on first
     * use instead. Writes through the model flush it, and so does the start of
     * every request, queued job and Octane operation, so a long-running
     * worker never serves a value another process has since changed.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $memo = [];

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        static::$memo = [];
    }

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::allValues();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        static::flushCache();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function allValues(): array
    {
        $query = static::query();
        $scope = $query->getConnection()->getName().'|'.(app(TenantContext::class)->current() ?? '');

        return static::$memo[$scope] ??= $query->get(['key', 'value'])
            ->mapWithKeys(fn (self $setting) => [$setting->key => $setting->value])
            ->all();
    }

    /**
     * Get a boolean setting value.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get an integer setting value.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = static::get($key);

        return $value !== null ? (int) $value : $default;
    }

    /**
     * Check if guest tickets are enabled.
     */
    public static function guestTicketsEnabled(): bool
    {
        return static::getBool('guest_tickets_enabled', true);
    }

    /**
     * Check if the knowledge base is enabled.
     */
    public static function knowledgeBaseEnabled(): bool
    {
        return static::getBool('knowledge_base_enabled', true);
    }

    /**
     * Check if the knowledge base is publicly accessible (no login required).
     */
    public static function knowledgeBasePublic(): bool
    {
        return static::getBool('knowledge_base_public', true);
    }

    /**
     * Check if article feedback (helpful/not helpful) is enabled.
     */
    public static function knowledgeBaseFeedbackEnabled(): bool
    {
        return static::getBool('knowledge_base_feedback_enabled', true);
    }
}
