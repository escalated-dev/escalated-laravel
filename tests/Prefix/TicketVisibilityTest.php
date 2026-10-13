<?php

use Escalated\Laravel\Tests\Fixtures\ExercisesTicketVisibility;

uses(ExercisesTicketVisibility::class);

it('enforces visibility with a custom table prefix', function () {
    $this->assertTicketVisibilityBoundaries();
});
