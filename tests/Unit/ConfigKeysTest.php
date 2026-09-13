<?php

use Illuminate\Support\Arr;

/*
 * Every escalated.* key the package reads must exist in config/escalated.php.
 *
 * A key that is read but missing from the file cannot be discovered by a host
 * that publishes the config, and a misspelt key quietly reads its fallback
 * forever. Both have happened: the env webhook was never signed because
 * notifications.webhook_secret was nowhere to be found, and the widget's
 * kb_enabled read a knowledge_base.enabled key that did not exist.
 */

it('defines every escalated config key the package reads', function () {
    $root = dirname(__DIR__, 2);
    $config = require $root.'/config/escalated.php';
    $missing = [];

    foreach (['src', 'routes', 'resources/views'] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator("{$root}/{$directory}", FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            preg_match_all(
                '/(?:\bconfig\(|\bconfig\(\)->get\(|Config::get\()\s*[\'"]escalated\.([A-Za-z0-9_.]+)[\'"]/',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[1] as [$key, $offset]) {
                // A key finished at runtime, such as 'escalated.newsletters.'.$name,
                // cannot be checked here.
                if (str_ends_with($key, '.') || Arr::has($config, $key)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $missing[] = "escalated.{$key} ({$path}:{$line})";
            }
        }
    }

    expect($missing)->toBe([]);
});
