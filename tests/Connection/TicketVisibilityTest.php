<?php

use Escalated\Laravel\Tests\Fixtures\ExercisesTicketVisibility;

uses(ExercisesTicketVisibility::class);

it('enforces visibility with a separate package connection', function () {
    $this->assertTicketVisibilityBoundaries();
});
