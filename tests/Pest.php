<?php

use Escalated\Laravel\Bridge\SdkPluginActivation;
use Escalated\Laravel\Models\Plugin;
use Escalated\Laravel\Tests\PrefixedTablesTestCase;
use Escalated\Laravel\Tests\SeparateConnectionTestCase;
use Escalated\Laravel\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');

// Boots the package on a connection that is not the host default, so it
// needs its own base case and therefore its own directory.
uses(SeparateConnectionTestCase::class)->in('Connection');

// Boots the package with a table prefix other than `escalated_`.
uses(PrefixedTablesTestCase::class)->in('Prefix');

/**
 * Record SDK plugins (by name) as active, as Admin > Plugins does.
 */
function activateSdkPlugins(string ...$names): void
{
    foreach ($names as $name) {
        Plugin::updateOrCreate(
            ['slug' => SdkPluginActivation::slug($name)],
            ['is_active' => true, 'activated_at' => now()],
        );
    }
}
