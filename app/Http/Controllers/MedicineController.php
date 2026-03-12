<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MedicineController extends Controller
{
    /**
     * Display a listing of medicines with search, filter, and pagination.
     */
    public function index(Request $request): Response
    {
        $query = Medicine::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->input('low_stock')) {
            $query->where('stock', '<', 10);
        }

        if ($unit = $request->input('unit')) {
            $query->where('unit', $unit);
        }

        $medicines = $query->paginate(20)->withQueryString();

        // Distinct units for filter dropdown
        $units = Medicine::distinct()->orderBy('unit')->pluck('unit');

        $lowStockCount = Medicine::where('stock', '<', 10)->count();

        return Inertia::render('Pharmacy/Medicines/Index', [
            'medicines' => $medicines->through(fn($m) => [
                'id'          => $m->id,
                'name'        => $m->name,
                'sku'         => $m->sku,
                'stock'       => $m->stock,
                'unit'        => $m->unit,
                'price'       => $m->price,
                'description' => $m->description,
                'is_low'      => $m->stock < 10,
                'created_at'  => $m->created_at->format('d M Y'),
                'updated_at'  => $m->updated_at->format('d M Y'),
            ]),
            'filters'       => $request->only(['search', 'low_stock', 'unit']),
            'units'         => $units,
            'lowStockCount' => $lowStockCount,
        ]);
    }

    /**
     * Show the form for creating a new medicine.
     */
    public function create(): Response
    {
        return Inertia::render('Pharmacy/Medicines/Create');
    }

    /**
     * Store a newly created medicine in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'sku'         => ['required', 'string', 'max:100', 'unique:medicines,sku'],
            'stock'       => ['required', 'integer', 'min:0'],
            'unit'        => ['required', 'string', 'max:50'],
            'price'       => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $medicine = Medicine::create($validated);

        return redirect()
            ->route('medicines.show', $medicine)
            ->with('success', "Obat \"{$medicine->name}\" berhasil ditambahkan.");
    }

    /**
     * Display the specified medicine with usage history.
     */
    public function show(Medicine $medicine): Response
    {
        // Load prescription items that used this medicine
        $usageHistory = $medicine->prescriptionItems()
            ->with([
                'prescription.medicalRecord.registration.patient',
                'prescription.medicalRecord.registration.doctor.user',
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'id'            => $item->id,
                'quantity'      => $item->quantity,
                'dosage'        => $item->dosage,
                'price'         => $item->price_at_moment,
                'patient_name'  => $item->prescription->medicalRecord->registration->patient->name ?? '-',
                'doctor_name'   => $item->prescription->medicalRecord->registration->doctor->user->name ?? '-',
                'dispensed_at'  => $item->created_at->format('d M Y H:i'),
            ]);

        $totalDispensed = $medicine->prescriptionItems()->sum('quantity');

        return Inertia::render('Pharmacy/Medicines/Show', [
            'medicine' => [
                'id'             => $medicine->id,
                'name'           => $medicine->name,
                'sku'            => $medicine->sku,
                'stock'          => $medicine->stock,
                'unit'           => $medicine->unit,
                'price'          => $medicine->price,
                'description'    => $medicine->description,
                'is_low'         => $medicine->stock < 10,
                'total_dispensed'=> $totalDispensed,
                'created_at'     => $medicine->created_at->format('d M Y'),
                'updated_at'     => $medicine->updated_at->format('d M Y'),
            ],
            'usageHistory' => $usageHistory,
        ]);
    }

    /**
     * Show the form for editing the specified medicine.
     */
    public function edit(Medicine $medicine): Response
    {
        return Inertia::render('Pharmacy/Medicines/Edit', [
            'medicine' => [
                'id'          => $medicine->id,
                'name'        => $medicine->name,
                'sku'         => $medicine->sku,
                'stock'       => $medicine->stock,
                'unit'        => $medicine->unit,
                'price'       => $medicine->price,
                'description' => $medicine->description,
            ],
        ]);
    }

    /**
     * Update the specified medicine in storage.
     */
    public function update(Request $request, Medicine $medicine): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'sku'         => ['required', 'string', 'max:100', Rule::unique('medicines', 'sku')->ignore($medicine->id)],
            'stock'       => ['required', 'integer', 'min:0'],
            'unit'        => ['required', 'string', 'max:50'],
            'price'       => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $medicine->update($validated);

        return redirect()
            ->route('medicines.show', $medicine)
            ->with('success', "Data obat \"{$medicine->name}\" berhasil diperbarui.");
    }

    /**
     * Remove the specified medicine from storage.
     */
    public function destroy(Medicine $medicine): RedirectResponse
    {
        $name = $medicine->name;

        // Prevent deletion if this medicine is referenced in any prescription items
        if ($medicine->prescriptionItems()->exists()) {
            return redirect()
                ->route('medicines.index')
                ->with('error', "Obat \"{$name}\" tidak dapat dihapus karena sudah digunakan dalam resep.");
        }

        $medicine->delete();

        return redirect()
            ->route('medicines.index')
            ->with('success', "Obat \"{$name}\" berhasil dihapus.");
    }

    /**
     * Adjust medicine stock (add or subtract).
     *
     * Used for manual stock-in / stock correction by pharmacist or admin.
     */
    public function adjustStock(Request $request, Medicine $medicine): RedirectResponse
    {
        $validated = $request->validate([
            'adjustment' => ['required', 'integer', 'not_in:0'],
            'reason'     => ['required', 'string', 'max:500', Rule::in([
                'restock',
                'correction',
                'expired',
                'damaged',
                'return',
                'other',
            ])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $newStock = $medicine->stock + $validated['adjustment'];

        if ($newStock < 0) {
            return back()->withErrors([
                'adjustment' => "Stok tidak mencukupi. Stok saat ini: {$medicine->stock} {$medicine->unit}.",
            ]);
        }

        $medicine->update(['stock' => $newStock]);

        $direction = $validated['adjustment'] > 0 ? 'ditambah' : 'dikurangi';
        $abs       = abs($validated['adjustment']);

        return back()->with(
            'success',
            "Stok \"{$medicine->name}\" berhasil {$direction} {$abs} {$medicine->unit}. Stok sekarang: {$newStock} {$medicine->unit}."
        );
    }
}
