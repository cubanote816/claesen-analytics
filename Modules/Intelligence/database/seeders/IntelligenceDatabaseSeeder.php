<?php

namespace Modules\Intelligence\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Database\Seeders\NbbPriceIndexSeeder;

class IntelligenceDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            BiConfigSeeder::class,
            NbbPriceIndexSeeder::class,
        ]);
    }
}
