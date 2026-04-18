<?php

namespace Database\Seeders;

use Escalated\Laravel\Database\Seeders\PermissionSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(DemoSeeder::class);
    }
}
