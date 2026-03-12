<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Registration;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MedicalRecordController extends Controller
{
    /**
     * Display a listing of medical records (doctor's patient queue).
     * Doctors see patients assigned to them; admins see all.
     */
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $today = now()->toDateString();

        $query = Registration::with(['patient', 'doctor.user', 'medicalRecord'])
            ->whereIn('status', ['consultation', 'vital_check', 'pharmacy', 'completed'])
            ->latest();

        // Doctors only see their own patients
        if ($user->role === 'doctor' && $user->doctor) {
            $query->where('doctor_id', $user->doctor->id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('queue_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($date = $request->input('date')) {
            $query->whereDate('created_at', $date);
        } else {
            $query->whereDate('created_at', $today);
        }

        $registrations = $query->paginate(20)->withQueryString();

        // Summary for today
        $baseQuery = Registration::whereDate('created_at', $today);
        if ($user->role === 'doctor' && $user->doctor) {
            $baseQuery->where('doctor_id', $user->doctor->id);
        }

        $summaryQuery  = clone $baseQuery;
        $waitingCount  = (clone $summaryQuery)->where('status', 'consultation')->count();
        $doneCount     = (clone $summaryQuery)->where('status', 'completed')->count();
        $totalToday    = $summaryQuery->count();

        return Inertia::render('MedicalRecords/Index', [
            'registrations' => $registrations->through(fn($reg) => [
                'id'             => $reg->id,
                'queue_number'   => $reg->queue_number,
                'status'         => $reg->status,
                'complaint'      => $reg->complaint,
                'has_record'     => $reg->medicalRecord !== null,
                'patient'        => [
                    'id'         => $reg->patient->id,
                    'name'       => $reg->patient->name,
                    'gender'     => $reg->patient->gender,
                    'birth_date' => $reg->patient->birth_date?->format('d M Y'),
                    'age'        => $reg->patient->birth_date
                        ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
                        : null,
                    'phone'      => $reg->patient->phone,
                ],
                'doctor'         => [
                    'name'           => $reg->doctor->user->name ?? '-',
                    'specialization' => $reg->doctor->specialization,
                ],
                'created_at'     => $reg->created_at->format('d M Y H:i'),
                'medical_record_id' => $reg->medicalRecord?->id,
            ]),
            'filters'  => $request->only(['search', 'status', 'date']),
            'summary'  => [
                'totalToday'   => $totalToday,
                'waitingCount' => $waitingCount,
                'doneCount'    => $doneCount,
            ],
            'statusOptions' => [
                'vital_check'  => 'Selesai Vital',
                'consultation' => 'Konsultasi',
                'pharmacy'     => 'Farmasi',
                'completed'    => 'Selesai',
            ],
        ]);
    }

    /**
     * Show the examination form for a registration.
     * This is the main doctor workflow page.
     */
    public function create(Registration $registration): Response
    {
        // If record already exists, redirect to edit
        if ($registration->medicalRecord) {
            return redirect()->route('medical-records.edit', $registration->medicalRecord);
        }

        $registration->load(['patient', 'doctor.user', 'vitalSign']);

        $age = $registration->patient->birth_date
            ? \Carbon\Carbon::parse($registration->patient->birth_date)->age
            : null;

        // Load available medicines for prescription
        $medicines = Medicine::where('stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'price', 'stock']);

        // Load available services for this doctor's specialization
        $services = Service::where('type', $registration->doctor->specialization)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        // Previous visit history for context
        $visitHistory = MedicalRecord::whereHas(
            'registration',
            fn($q) => $q->where('patient_id', $registration->patient_id)
        )
            ->with('registration')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($mr) => [
                'id'           => $mr->id,
                'diagnosis'    => $mr->diagnosis,
                'action_taken' => $mr->action_taken,
                'notes'        => $mr->notes,
                'created_at'   => $mr->created_at->format('d M Y'),
            ]);

        return Inertia::render('MedicalRecords/Create', [
            'registration' => [
                'id'           => $registration->id,
                'queue_number' => $registration->queue_number,
                'status'       => $registration->status,
                'complaint'    => $registration->complaint,
                'created_at'   => $registration->created_at->format('d M Y H:i'),
            ],
            'patient' => [
                'id'          => $registration->patient->id,
                'name'        => $registration->patient->name,
                'nik'         => $registration->patient->nik,
                'gender'      => $registration->patient->gender,
                'birth_date'  => $registration->patient->birth_date?->format('d M Y'),
                'age'         => $age,
                'phone'       => $registration->patient->phone,
                'address'     => $registration->patient->address,
            ],
            'doctor' => [
                'id'             => $registration->doctor->id,
                'name'           => $registration->doctor->user->name ?? '-',
                'specialization' => $registration->doctor->specialization,
                'sip_number'     => $registration->doctor->sip_number,
            ],
            'vitalSign' => $registration->vitalSign ? [
                'systolic'         => $registration->vitalSign->systolic,
                'diastolic'        => $registration->vitalSign->diastolic,
                'temperature'      => $registration->vitalSign->temperature,
                'pulse'            => $registration->vitalSign->pulse,
                'respiratory_rate' => $registration->vitalSign->respiratory_rate,
                'height'           => $registration->vitalSign->height,
                'weight'           => $registration->vitalSign->weight,
                'notes'            => $registration->vitalSign->notes,
            ] : null,
            'medicines'    => $medicines,
            'services'     => $services,
            'visitHistory' => $visitHistory,
        ]);
    }

    /**
     * Store a new medical record, optional prescription, and auto-create a transaction.
     *
     * Expected payload:
     * {
     *   diagnosis: string,
     *   action_taken: string|null,
     *   notes: string|null,
     *   service_ids: int[],          // selected services for billing
     *   prescription: {              // optional
     *     notes: string|null,
     *     items: [
     *       { medicine_id, quantity, dosage }
     *     ]
     *   }|null
     * }
     */
    public function store(Request $request, Registration $registration): RedirectResponse
    {
        if ($registration->medicalRecord) {
            return redirect()
                ->route('medical-records.edit', $registration->medicalRecord)
                ->with('error', 'Rekam medis untuk pendaftaran ini sudah ada.');
        }

        $validated = $request->validate([
            'diagnosis'                        => ['required', 'string', 'max:2000'],
            'action_taken'                     => ['nullable', 'string', 'max:2000'],
            'notes'                            => ['nullable', 'string', 'max:2000'],
            'service_ids'                      => ['nullable', 'array'],
            'service_ids.*'                    => ['integer', 'exists:services,id'],
            'prescription'                     => ['nullable', 'array'],
            'prescription.notes'               => ['nullable', 'string', 'max:1000'],
            'prescription.items'               => ['nullable', 'array'],
            'prescription.items.*.medicine_id' => ['required_with:prescription.items', 'integer', 'exists:medicines,id'],
            'prescription.items.*.quantity'    => ['required_with:prescription.items', 'integer', 'min:1'],
            'prescription.items.*.dosage'      => ['required_with:prescription.items', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $registration) {
            // 1. Create the medical record
            $medicalRecord = MedicalRecord::create([
                'registration_id' => $registration->id,
                'doctor_id'       => $registration->doctor_id,
                'diagnosis'       => $validated['diagnosis'],
                'action_taken'    => $validated['action_taken'] ?? null,
                'notes'           => $validated['notes'] ?? null,
            ]);

            $totalAmount = 0;

            // 2. Add selected services to the total
            $serviceIds = $validated['service_ids'] ?? [];
            if (!empty($serviceIds)) {
                $services    = Service::whereIn('id', $serviceIds)->get();
                $totalAmount += $services->sum('price');
            }

            // 3. Create prescription if provided
            if (!empty($validated['prescription']['items'])) {
                $prescription = Prescription::create([
                    'medical_record_id' => $medicalRecord->id,
                    'status'            => 'pending',
                    'notes'             => $validated['prescription']['notes'] ?? null,
                ]);

                foreach ($validated['prescription']['items'] as $item) {
                    $medicine = Medicine::findOrFail($item['medicine_id']);

                    // Validate sufficient stock
                    if ($medicine->stock < $item['quantity']) {
                        throw new \Exception("Stok obat {$medicine->name} tidak mencukupi. Tersedia: {$medicine->stock} {$medicine->unit}.");
                    }

                    PrescriptionItem::create([
                        'prescription_id'  => $prescription->id,
                        'medicine_id'      => $medicine->id,
                        'quantity'         => $item['quantity'],
                        'dosage'           => $item['dosage'],
                        'price_at_moment'  => $medicine->price,
                    ]);

                    // Deduct stock immediately on prescription creation
                    $medicine->decrement('stock', $item['quantity']);

                    $totalAmount += $medicine->price * $item['quantity'];
                }

                // Advance status to pharmacy
                $registration->update(['status' => 'pharmacy']);
            } else {
                // No prescription — mark as completed
                $registration->update(['status' => 'completed']);
            }

            // 4. Auto-create a transaction (unpaid) with calculated total
            Transaction::create([
                'registration_id' => $registration->id,
                'total_amount'    => $totalAmount,
                'status'          => 'unpaid',
            ]);
        });

        return redirect()
            ->route('medical-records.show', $registration->medicalRecord->fresh())
            ->with('success', 'Rekam medis berhasil disimpan dan transaksi telah dibuat.');
    }

    /**
     * Display the specified medical record with full context.
     */
    public function show(MedicalRecord $medicalRecord): Response
    {
        $medicalRecord->load([
            'registration.patient',
            'registration.doctor.user',
            'registration.vitalSign',
            'registration.transaction',
            'prescription.items.medicine',
        ]);

        $reg = $medicalRecord->registration;

        $age = $reg->patient->birth_date
            ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
            : null;

        return Inertia::render('MedicalRecords/Show', [
            'medicalRecord' => [
                'id'           => $medicalRecord->id,
                'diagnosis'    => $medicalRecord->diagnosis,
                'action_taken' => $medicalRecord->action_taken,
                'notes'        => $medicalRecord->notes,
                'created_at'   => $medicalRecord->created_at->format('d M Y H:i'),
                'updated_at'   => $medicalRecord->updated_at->format('d M Y H:i'),
            ],
            'registration' => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ],
            'patient' => [
                'id'          => $reg->patient->id,
                'name'        => $reg->patient->name,
                'nik'         => $reg->patient->nik,
                'gender'      => $reg->patient->gender,
                'birth_date'  => $reg->patient->birth_date?->format('d M Y'),
                'age'         => $age,
                'phone'       => $reg->patient->phone,
                'address'     => $reg->patient->address,
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
                'notes'            => $reg->vitalSign->notes,
            ] : null,
            'prescription' => $medicalRecord->prescription ? [
                'id'     => $medicalRecord->prescription->id,
                'status' => $medicalRecord->prescription->status,
                'notes'  => $medicalRecord->prescription->notes,
                'items'  => $medicalRecord->prescription->items->map(fn($item) => [
                    'id'            => $item->id,
                    'medicine_name' => $item->medicine->name ?? '-',
                    'unit'          => $item->medicine->unit ?? '-',
                    'quantity'      => $item->quantity,
                    'dosage'        => $item->dosage,
                    'price'         => $item->price_at_moment,
                    'subtotal'      => $item->price_at_moment * $item->quantity,
                ]),
                'total' => $medicalRecord->prescription->items->sum(
                    fn($i) => $i->price_at_moment * $i->quantity
                ),
            ] : null,
            'transaction' => $reg->transaction ? [
                'id'             => $reg->transaction->id,
                'total_amount'   => $reg->transaction->total_amount,
                'status'         => $reg->transaction->status,
                'payment_method' => $reg->transaction->payment_method,
            ] : null,
        ]);
    }

    /**
     * Show the form for editing the specified medical record.
     */
    public function edit(MedicalRecord $medicalRecord): Response
    {
        $medicalRecord->load([
            'registration.patient',
            'registration.doctor.user',
            'registration.vitalSign',
            'prescription.items.medicine',
        ]);

        $reg = $medicalRecord->registration;

        $medicines = Medicine::orderBy('name')->get(['id', 'name', 'unit', 'price', 'stock']);
        $services  = Service::where('type', $reg->doctor->specialization)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        $age = $reg->patient->birth_date
            ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
            : null;

        return Inertia::render('MedicalRecords/Edit', [
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
                'systolic'         => $reg->vitalSign->systolic,
                'diastolic'        => $reg->vitalSign->diastolic,
                'temperature'      => $reg->vitalSign->temperature,
                'pulse'            => $reg->vitalSign->pulse,
                'respiratory_rate' => $reg->vitalSign->respiratory_rate,
                'height'           => $reg->vitalSign->height,
                'weight'           => $reg->vitalSign->weight,
            ] : null,
            'prescription' => $medicalRecord->prescription ? [
                'id'    => $medicalRecord->prescription->id,
                'notes' => $medicalRecord->prescription->notes,
                'items' => $medicalRecord->prescription->items->map(fn($item) => [
                    'id'          => $item->id,
                    'medicine_id' => $item->medicine_id,
                    'quantity'    => $item->quantity,
                    'dosage'      => $item->dosage,
                ]),
            ] : null,
            'medicines' => $medicines,
            'services'  => $services,
        ]);
    }

    /**
     * Update the specified medical record.
     * Only diagnosis/action/notes can be updated after the fact.
     */
    public function update(Request $request, MedicalRecord $medicalRecord): RedirectResponse
    {
        $validated = $request->validate([
            'diagnosis'    => ['required', 'string', 'max:2000'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $medicalRecord->update($validated);

        return redirect()
            ->route('medical-records.show', $medicalRecord)
            ->with('success', 'Rekam medis berhasil diperbarui.');
    }
}
