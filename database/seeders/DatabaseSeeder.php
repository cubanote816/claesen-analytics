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
        $user1 = User::factory()->create([
            'name' => 'Bert Bertels',
            'email' => 'bert.Bertels@claesen-verlichting.be',
        ]);
        $user2 = User::factory()->create([
            'name' => 'Bert Kenis',
            'email' => 'bert.kenis@claesen-verlichting.be',
        ]);
        $user3 = User::factory()->create([
            'name' => 'Toti',
            'email' => 'orelvys.cuellar@claesen-verlichting.be',
        ]);

        $this->call([
            RolesAndPermissionsSeeder::class,
            ProjectInsightSeeder::class,
        ]);

        $user1->assignRole('super_admin');
        $user2->assignRole('super_admin');
        $user3->assignRole('super_admin');
    }
}
