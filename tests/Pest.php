<?php

use Escalated\Laravel\Tests\SeparateConnectionTestCase;
use Escalated\Laravel\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');

// Boots the package on a connection that is not the host default, so it
// needs its own base case and therefore its own directory.
uses(SeparateConnectionTestCase::class)->in('Connection');
