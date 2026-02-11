<?php

namespace Database\Seeders;

use App\Models\Medicine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Medicine::create([
            'name' => 'Paracetamol 500mg',
            'sku' => 'MED-001',
            'stock' => 100,
            'unit' => 'Strip',
            'price' => 5000,
            'description' => 'Obat penurun panas dan pereda nyeri',
        ]);

        Medicine::create([
            'name' => 'Amoxicillin 500mg',
            'sku' => 'MED-002',
            'stock' => 50,
            'unit' => 'Strip',
            'price' => 12000,
            'description' => 'Antibiotik untuk infeksi bakteri',
        ]);

        Medicine::create([
            'name' => 'Vitamin C 500mg',
            'sku' => 'MED-003',
            'stock' => 200,
            'unit' => 'Botol',
            'price' => 25000,
            'description' => 'Suplemen daya tahan tubuh',
        ]);
    }
}
