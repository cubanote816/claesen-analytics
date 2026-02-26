<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Bert Bertels',
            'email' => 'bert.Bertels@claesen-verlichting.be',
        ]);
        User::factory()->create([
            'name' => 'Bert Kenis',
            'email' => 'bert.kenis@claesen-verlichting.be',
        ]);
        User::factory()->create([
            'name' => 'Toti',
            'email' => 'orelvys.cuellar@claesen-verlichting.be',
        ]);

        $this->call([
            RolesAndPermissionsSeeder::class,
            ProjectInsightSeeder::class,
        ]);
    }
}
