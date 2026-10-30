<?php

namespace Escalated\Laravel\Bridge;

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Plugin;
use Escalated\Laravel\Services\PluginUIService;
use Illuminate\Support\Facades\Log;

/**
 * Which SDK plugins are active (domain-model/plugin-contract.md, Storage and
 * Lifecycle). SDK plugins share the `escalated_plugins` table with native
 * ones, keyed by the contract's slug of their name; a plugin with no row is
 * inactive.
 *
 * The runtime loads every installed SDK plugin, so the host gates each call
 * by this: hooks, endpoints, webhooks, pages, cron, badges and UI extensions
 * only reach active plugins.
 */
class SdkPluginActivation
{
    /**
     * Set once SDK plugins have been recorded. Before it, every SDK plugin
     * ran whether or not anyone activated it.
     */
    public const RECORDED_SETTING = 'plugins_sdk_activation_recorded';

    public static function slug(string $name): string
    {
        return PluginUIService::slug($name);
    }

    /**
     * The names among $names whose plugins are active, in the given order.
     * Nothing is active when the table cannot be read.
     *
     * @param  iterable<string>  $names
     * @return list<string>
     */
    public static function activeNames(iterable $names): array
    {
        $bySlug = [];
        foreach ($names as $name) {
            $bySlug[self::slug((string) $name)][] = (string) $name;
        }

        if ($bySlug === []) {
            return [];
        }

        try {
            $active = Plugin::query()->whereIn('slug', array_keys($bySlug))->where('is_active', true)
                ->pluck('slug')->all();
        } catch (\Throwable $e) {
            Log::debug('Escalated plugins: could not read SDK plugin activation', ['error' => $e->getMessage()]);

            return [];
        }

        $result = [];
        foreach ($bySlug as $slug => $list) {
            if (in_array($slug, $active, true)) {
                array_push($result, ...$list);
            }
        }

        return $result;
    }

    public static function isActive(string $name): bool
    {
        return self::activeNames([$name]) !== [];
    }

    /**
     * Keep only the manifests of active plugins.
     *
     * @param  array<string, array>  $manifests  keyed by plugin name
     * @return array<string, array>
     */
    public static function activeManifests(array $manifests): array
    {
        return array_intersect_key($manifests, array_flip(self::activeNames(array_keys($manifests))));
    }

    /**
     * The first time SDK plugins are seen, record every one of them as
     * active, so an upgrade from a version that ran every SDK plugin does not
     * silently switch them off. Plugins installed later start inactive.
     *
     * @param  list<string>  $names
     */
    public static function recordInstalled(array $names): void
    {
        if ($names === []) {
            return;
        }

        try {
            if (EscalatedSettings::get(self::RECORDED_SETTING) !== null) {
                return;
            }

            foreach ($names as $name) {
                $slug = self::slug($name);
                if (! Plugin::query()->where('slug', $slug)->exists()) {
                    Plugin::create(['slug' => $slug, 'is_active' => true, 'activated_at' => now()]);
                }
            }

            EscalatedSettings::set(self::RECORDED_SETTING, now()->toIso8601String());
        } catch (\Throwable $e) {
            // The tables may not exist yet (first migrate); try again next boot.
            Log::debug('Escalated plugins: could not record installed SDK plugins', ['error' => $e->getMessage()]);
        }
    }
}
