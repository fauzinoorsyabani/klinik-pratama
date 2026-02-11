<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Service::create([
            'name' => 'Konsultasi Dokter Umum',
            'price' => 50000,
            'type' => 'general',
        ]);

        Service::create([
            'name' => 'Konsultasi Dokter Gigi',
            'price' => 75000,
            'type' => 'dental',
        ]);

        Service::create([
            'name' => 'Pembersihan Karang Gigi (Scaling)',
            'price' => 150000,
            'type' => 'dental',
        ]);

        Service::create([
            'name' => 'Cek Gula Darah',
            'price' => 25000,
            'type' => 'general',
        ]);
    }
}
