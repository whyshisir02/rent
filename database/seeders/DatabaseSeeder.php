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
            'phone' => '9800000000',
            'user_type' => 'admin',
        ]);

        User::factory()->create([
            'phone' => '9811111111',
            'user_type' => 'landlord',
        ]);

        User::factory()->create([
            'phone' => '9822222222',
            'user_type' => 'tenant',
        ]);
    }
}
