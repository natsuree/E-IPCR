<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data runs everywhere. Demo accounts and records only run
     * locally and in tests, never in production.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
