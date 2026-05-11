<?php

namespace Modules\Safety\Database\Seeders;

use Illuminate\Database\Seeder;

class SafetyDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            SafetyChecklistSeeder::class,
        ]);
    }
}
