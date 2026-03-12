<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionController extends Controller
{
    /**
     * Show the form to create a new prescription for a medical record.
     * Only accessible if no prescription exists yet.
     */
    public function create(MedicalRecord $medicalRecord): Response|RedirectResponse
    {
        if ($medicalRecord->prescription) {
            return redirect()
                ->route('prescriptions.edit', $medicalRecord->prescription)
                ->with('error', 'Resep untuk rekam medis ini sudah ada. Silakan edit resep yang sudah ada.');
        }

        $medicalRecord->load([
            'registration.patient',
            'registration.doctor.user',
            'registration.vitalSign',
        ]);

        $reg = $medicalRecord->registration;

        $age = $reg->patient->birth_date
            ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
            : null;

        $medicines = Medicine::where('stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'price', 'stock', 'description']);

        return Inertia::render('Prescriptions/Create', [
            'medicalRecord' => [
                'id'           => $medicalRecord->id,
                'diagnosis'    => $medicalRecord->diagnosis,
                'action_taken' => $medicalRecord->action_taken,
                'notes'        => $medicalRecord->notes,
                'created_at'   => $medicalRecord->created_at->format('d M Y H:i'),
            ],
            'registration' => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ],
            'patient' => [
                'id'         => $reg->patient->id,
                'name'       => $reg->patient->name,
                'nik'        => $reg->patient->nik,
                'gender'     => $reg->patient->gender,
                'birth_date' => $reg->patient->birth_date?->format('d M Y'),
                'age'        => $age,
                'phone'      => $reg->patient->phone,
                'address'    => $reg->patient->address,
            ],
            'doctor' => [
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization,
                'sip_number'     => $reg->doctor->sip_number,
            ],
            'vitalSign' => $reg->vitalSign ? [
                'systolic'         => $reg->vitalSign->systolic,
                'diastolic'        => $reg->vitalSign->diastolic,
                'temperature'      => $reg->vitalSign->temperature,
                'pulse'            => $reg->vitalSign->pulse,
                'respiratory_rate' => $reg->vitalSign->respiratory_rate,
                'height'           => $reg->vitalSign->height,
                'weight'           => $reg->vitalSign->weight,
            ] : null,
            'medicines' => $medicines,
        ]);
    }

    /**
     * Store a new prescription with its items.
     *
     * Expected payload:
     * {
     *   notes: string|null,
     *   items: [
     *     { medicine_id: int, quantity: int, dosage: string }
     *   ]
     * }
     */
    public function store(Request $request, MedicalRecord $medicalRecord): RedirectResponse
    {
        if ($medicalRecord->prescription) {
            return redirect()
                ->route('prescriptions.edit', $medicalRecord->prescription)
                ->with('error', 'Resep sudah ada. Silakan edit resep yang sudah ada.');
        }

        $validated = $request->validate([
            'notes'                  => ['nullable', 'string', 'max:1000'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.medicine_id'    => ['required', 'integer', 'exists:medicines,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.dosage'         => ['required', 'string', 'max:255'],
        ]);

        // Pre-validate all stock levels before any DB writes
        $stockErrors = [];
        foreach ($validated['items'] as $index => $item) {
            $medicine = Medicine::find($item['medicine_id']);
            if ($medicine && $medicine->stock < $item['quantity']) {
                $stockErrors["items.{$index}.quantity"] =
                    "Stok {$medicine->name} tidak mencukupi. Tersedia: {$medicine->stock} {$medicine->unit}.";
            }
        }

        if (!empty($stockErrors)) {
            return back()->withErrors($stockErrors)->withInput();
        }

        DB::transaction(function () use ($validated, $medicalRecord) {
            $prescription = Prescription::create([
                'medical_record_id' => $medicalRecord->id,
                'status'            => 'pending',
                'notes'             => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $medicine = Medicine::findOrFail($item['medicine_id']);

                PrescriptionItem::create([
                    'prescription_id' => $prescription->id,
                    'medicine_id'     => $medicine->id,
                    'quantity'        => $item['quantity'],
                    'dosage'          => $item['dosage'],
                    'price_at_moment' => $medicine->price,
                ]);

                // Deduct stock
                $medicine->decrement('stock', $item['quantity']);
            }

            // Advance registration status to pharmacy
            $registration = $medicalRecord->registration;
            if (in_array($registration->status, ['consultation', 'vital_check'])) {
                $registration->update(['status' => 'pharmacy']);
            }

            // Update or create transaction total to include medicine costs
            $medicineCost = collect($validated['items'])->sum(function ($item) {
                return Medicine::find($item['medicine_id'])?->price * $item['quantity'] ?? 0;
            });

            $transaction = $registration->transaction;
            if ($transaction) {
                $transaction->increment('total_amount', $medicineCost);
            }
        });

        return redirect()
            ->route('medical-records.show', $medicalRecord)
            ->with('success', 'Resep berhasil dibuat dan dikirim ke farmasi.');
    }

    /**
     * Show the form for editing an existing prescription.
     * Only allowed if prescription status is still 'pending'.
     */
    public function edit(Prescription $prescription): Response|RedirectResponse
    {
        if (!in_array($prescription->status, ['pending', 'preparing'])) {
            return redirect()
                ->route('medical-records.show', $prescription->medicalRecord)
                ->with('error', 'Resep tidak dapat diedit karena sudah diproses oleh farmasi.');
        }

        $prescription->load([
            'items.medicine',
            'medicalRecord.registration.patient',
            'medicalRecord.registration.doctor.user',
            'medicalRecord.registration.vitalSign',
        ]);

        $medicalRecord = $prescription->medicalRecord;
        $reg           = $medicalRecord->registration;

        $age = $reg->patient->birth_date
            ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
            : null;

        $medicines = Medicine::orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'price', 'stock', 'description']);

        return Inertia::render('Prescriptions/Edit', [
            'prescription' => [
                'id'     => $prescription->id,
                'status' => $prescription->status,
                'notes'  => $prescription->notes,
                'items'  => $prescription->items->map(fn($item) => [
                    'id'          => $item->id,
                    'medicine_id' => $item->medicine_id,
                    'medicine'    => [
                        'id'    => $item->medicine->id,
                        'name'  => $item->medicine->name,
                        'unit'  => $item->medicine->unit,
                        'price' => $item->medicine->price,
                        'stock' => $item->medicine->stock,
                    ],
                    'quantity'        => $item->quantity,
                    'dosage'          => $item->dosage,
                    'price_at_moment' => $item->price_at_moment,
                ]),
            ],
            'medicalRecord' => [
                'id'           => $medicalRecord->id,
                'diagnosis'    => $medicalRecord->diagnosis,
                'action_taken' => $medicalRecord->action_taken,
                'notes'        => $medicalRecord->notes,
            ],
            'registration' => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ],
            'patient' => [
                'id'         => $reg->patient->id,
                'name'       => $reg->patient->name,
                'gender'     => $reg->patient->gender,
                'birth_date' => $reg->patient->birth_date?->format('d M Y'),
                'age'        => $age,
                'phone'      => $reg->patient->phone,
            ],
            'doctor' => [
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization,
            ],
            'vitalSign' => $reg->vitalSign ? [
                'systolic'    => $reg->vitalSign->systolic,
                'diastolic'   => $reg->vitalSign->diastolic,
                'temperature' => $reg->vitalSign->temperature,
                'pulse'       => $reg->vitalSign->pulse,
                'height'      => $reg->vitalSign->height,
                'weight'      => $reg->vitalSign->weight,
            ] : null,
            'medicines' => $medicines,
        ]);
    }

    /**
     * Update an existing prescription.
     *
     * Strategy:
     * - Restore stock for all existing items.
     * - Delete existing items.
     * - Re-create items from the new payload.
     * - Deduct stock for new items.
     * - Update transaction total difference.
     */
    public function update(Request $request, Prescription $prescription): RedirectResponse
    {
        if (!in_array($prescription->status, ['pending', 'preparing'])) {
            return back()->with('error', 'Resep tidak dapat diedit karena sudah diproses oleh farmasi.');
        }

        $validated = $request->validate([
            'notes'               => ['nullable', 'string', 'max:1000'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.quantity'    => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.dosage'      => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $prescription) {
            $medicalRecord = $prescription->medicalRecord;
            $registration  = $medicalRecord->registration;

            // ── Restore stock for old items ───────────────────────────
            foreach ($prescription->items as $oldItem) {
                $oldItem->medicine?->increment('stock', $oldItem->quantity);
            }

            // Capture old total for transaction diff
            $oldMedicineCost = $prescription->items->sum(
                fn($i) => $i->price_at_moment * $i->quantity
            );

            // ── Delete old items ──────────────────────────────────────
            $prescription->items()->delete();

            // ── Pre-validate new stock ────────────────────────────────
            foreach ($validated['items'] as $item) {
                $medicine = Medicine::findOrFail($item['medicine_id']);
                if ($medicine->stock < $item['quantity']) {
                    throw new \Exception(
                        "Stok {$medicine->name} tidak mencukupi. Tersedia: {$medicine->stock} {$medicine->unit}."
                    );
                }
            }

            // ── Create new items ──────────────────────────────────────
            $newMedicineCost = 0;

            foreach ($validated['items'] as $item) {
                $medicine = Medicine::findOrFail($item['medicine_id']);

                PrescriptionItem::create([
                    'prescription_id' => $prescription->id,
                    'medicine_id'     => $medicine->id,
                    'quantity'        => $item['quantity'],
                    'dosage'          => $item['dosage'],
                    'price_at_moment' => $medicine->price,
                ]);

                $medicine->decrement('stock', $item['quantity']);

                $newMedicineCost += $medicine->price * $item['quantity'];
            }

            // ── Update prescription notes ─────────────────────────────
            $prescription->update([
                'notes'  => $validated['notes'] ?? null,
                'status' => 'pending', // reset to pending on edit
            ]);

            // ── Adjust transaction total ──────────────────────────────
            $diff        = $newMedicineCost - $oldMedicineCost;
            $transaction = $registration->transaction;
            if ($transaction && $diff !== 0) {
                $newTotal = max(0, $transaction->total_amount + $diff);
                $transaction->update(['total_amount' => $newTotal]);
            }
        });

        return redirect()
            ->route('medical-records.show', $prescription->medicalRecord)
            ->with('success', 'Resep berhasil diperbarui.');
    }

    /**
     * Display the specified prescription (read-only view, used by pharmacist and doctor).
     */
    public function show(Prescription $prescription): Response
    {
        $prescription->load([
            'items.medicine',
            'medicalRecord.registration.patient',
            'medicalRecord.registration.doctor.user',
            'medicalRecord.registration.transaction',
        ]);

        $medicalRecord = $prescription->medicalRecord;
        $reg           = $medicalRecord->registration;

        $age = $reg->patient->birth_date
            ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
            : null;

        return Inertia::render('Prescriptions/Show', [
            'prescription' => [
                'id'         => $prescription->id,
                'status'     => $prescription->status,
                'notes'      => $prescription->notes,
                'created_at' => $prescription->created_at->format('d M Y H:i'),
                'updated_at' => $prescription->updated_at->format('d M Y H:i'),
                'items'      => $prescription->items->map(fn($item) => [
                    'id'              => $item->id,
                    'medicine_name'   => $item->medicine->name ?? '-',
                    'medicine_sku'    => $item->medicine->sku ?? '-',
                    'unit'            => $item->medicine->unit ?? '-',
                    'quantity'        => $item->quantity,
                    'dosage'          => $item->dosage,
                    'price_at_moment' => $item->price_at_moment,
                    'subtotal'        => $item->price_at_moment * $item->quantity,
                ]),
                'total' => $prescription->items->sum(
                    fn($i) => $i->price_at_moment * $i->quantity
                ),
            ],
            'medicalRecord' => [
                'id'           => $medicalRecord->id,
                'diagnosis'    => $medicalRecord->diagnosis,
                'action_taken' => $medicalRecord->action_taken,
                'notes'        => $medicalRecord->notes,
            ],
            'registration' => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ],
            'patient' => [
                'id'         => $reg->patient->id,
                'name'       => $reg->patient->name,
                'nik'        => $reg->patient->nik,
                'gender'     => $reg->patient->gender,
                'birth_date' => $reg->patient->birth_date?->format('d M Y'),
                'age'        => $age,
                'phone'      => $reg->patient->phone,
                'address'    => $reg->patient->address,
            ],
            'doctor' => [
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization,
                'sip_number'     => $reg->doctor->sip_number,
            ],
            'transaction' => $reg->transaction ? [
                'id'             => $reg->transaction->id,
                'total_amount'   => $reg->transaction->total_amount,
                'status'         => $reg->transaction->status,
                'payment_method' => $reg->transaction->payment_method,
            ] : null,
            'statusLabels' => self::statusLabels(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private static function statusLabels(): array
    {
        return [
            'pending'   => 'Menunggu Diproses',
            'preparing' => 'Sedang Disiapkan',
            'ready'     => 'Siap Diambil',
            'delivered' => 'Sudah Diserahkan',
        ];
    }
}
