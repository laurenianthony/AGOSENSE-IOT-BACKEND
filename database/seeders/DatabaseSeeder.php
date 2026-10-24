<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'admin@agosense.com'],
            [
                'name' => 'Admin Official',
                'password' => bcrypt('Laurenanthony_11'),
                'role' => 'admin',
            ]
        );

        $this->call(DrainageSeeder::class);

        // Keep the daily-only history view populated immediately after seeding.
        Artisan::call('sensors:aggregate', ['--scope' => 'daily']);
    }
}
