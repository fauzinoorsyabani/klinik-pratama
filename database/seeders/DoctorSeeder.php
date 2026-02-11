<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // General Doctor
        $userGeneral = User::create([
            'name' => 'dr. Budi Santoso',
            'email' => 'dr.budi@klinik.com',
            'password' => bcrypt('password'),
            'role' => 'doctor_general',
            'phone' => '081299998888',
        ]);
        Doctor::create([
            'user_id' => $userGeneral->id,
            'specialization' => 'general',
            'sip_number' => 'SIP-1001-GEN',
            'is_active' => true,
        ]);

        // Dentist
        $userDental = User::create([
            'name' => 'drg. Siti Aminah',
            'email' => 'drg.siti@klinik.com',
            'password' => bcrypt('password'),
            'role' => 'doctor_dental',
            'phone' => '081277776666',
        ]);
        Doctor::create([
            'user_id' => $userDental->id,
            'specialization' => 'dental',
            'sip_number' => 'SIP-2002-DEN',
            'is_active' => true,
        ]);
    }
}
