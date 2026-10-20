<?php

namespace Database\Seeders;

use App\Services\DemoData;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the fictional E-IPCR demonstration roster (exact port of the
     * frontend prototype's seed() + upgradeRoster() data).
     */
    public function run(): void
    {
        DemoData::seed();
    }
}
