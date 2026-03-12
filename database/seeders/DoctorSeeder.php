<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── Dokter Umum 1 ─────────────────────────────────────────────────
        $userGeneral1 = User::create([
            "name" => "dr. Budi Santoso",
            "email" => "dr.budi@klinik.com",
            "password" => bcrypt("password"),
            "role" => "doctor",
            "phone" => "081299998888",
            "email_verified_at" => now(),
        ]);
        Doctor::create([
            "user_id" => $userGeneral1->id,
            "specialization" => "general",
            "sip_number" => "SIP-1001-GEN",
            "is_active" => true,
        ]);

        // ── Dokter Umum 2 ─────────────────────────────────────────────────
        $userGeneral2 = User::create([
            "name" => "dr. Ahmad Fauzi",
            "email" => "dr.ahmad@klinik.com",
            "password" => bcrypt("password"),
            "role" => "doctor",
            "phone" => "081355554444",
            "email_verified_at" => now(),
        ]);
        Doctor::create([
            "user_id" => $userGeneral2->id,
            "specialization" => "general",
            "sip_number" => "SIP-1002-GEN",
            "is_active" => true,
        ]);

        // ── Dokter Gigi ────────────────────────────────────────────────────
        $userDental = User::create([
            "name" => "drg. Siti Aminah",
            "email" => "drg.siti@klinik.com",
            "password" => bcrypt("password"),
            "role" => "doctor",
            "phone" => "081277776666",
            "email_verified_at" => now(),
        ]);
        Doctor::create([
            "user_id" => $userDental->id,
            "specialization" => "dental",
            "sip_number" => "SIP-2001-DEN",
            "is_active" => true,
        ]);

        // ── Dokter Gigi 2 ──────────────────────────────────────────────────
        $userDental2 = User::create([
            "name" => "drg. Rizky Permata",
            "email" => "drg.rizky@klinik.com",
            "password" => bcrypt("password"),
            "role" => "doctor",
            "phone" => "081366665555",
            "email_verified_at" => now(),
        ]);
        Doctor::create([
            "user_id" => $userDental2->id,
            "specialization" => "dental",
            "sip_number" => "SIP-2002-DEN",
            "is_active" => true,
        ]);

        // ── Petugas Pendaftaran ────────────────────────────────────────────
        User::create([
            "name" => "Rina Kartika",
            "email" => "pendaftaran@klinik.com",
            "password" => bcrypt("password"),
            "role" => "registration",
            "phone" => "081244443333",
            "email_verified_at" => now(),
        ]);

        User::create([
            "name" => "Dimas Prasetyo",
            "email" => "pendaftaran2@klinik.com",
            "password" => bcrypt("password"),
            "role" => "registration",
            "phone" => "081233332222",
            "email_verified_at" => now(),
        ]);

        // ── Petugas Ruang Tunggu / Nurse ───────────────────────────────────
        User::create([
            "name" => "Dewi Rahayu",
            "email" => "nurse@klinik.com",
            "password" => bcrypt("password"),
            "role" => "nurse",
            "phone" => "081222221111",
            "email_verified_at" => now(),
        ]);

        User::create([
            "name" => "Eko Susanto",
            "email" => "nurse2@klinik.com",
            "password" => bcrypt("password"),
            "role" => "nurse",
            "phone" => "081211110000",
            "email_verified_at" => now(),
        ]);

        // ── Petugas Farmasi ────────────────────────────────────────────────
        User::create([
            "name" => "Hendra Wijaya",
            "email" => "farmasi@klinik.com",
            "password" => bcrypt("password"),
            "role" => "pharmacist",
            "phone" => "081288887777",
            "email_verified_at" => now(),
        ]);

        User::create([
            "name" => "Lestari Dewi",
            "email" => "farmasi2@klinik.com",
            "password" => bcrypt("password"),
            "role" => "pharmacist",
            "phone" => "081277776543",
            "email_verified_at" => now(),
        ]);
    }
}
