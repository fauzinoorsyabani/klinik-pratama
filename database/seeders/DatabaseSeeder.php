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
            'name' => 'Administrator',
            'email' => 'admin@klinik.com',
            'role' => 'admin',
            'phone' => '081234567890',
            'password' => bcrypt('password'),
        ]);

        $this->call([
            DoctorSeeder::class,
            MedicineSeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
