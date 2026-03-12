<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\VitalSign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VitalSignController extends Controller
{
    /**
     * Display a listing of registrations that are in the vital_check stage.
     * This is the nurse's main work queue.
     */
    public function index(Request $request): Response
    {
        $today = now()->toDateString();

        $query = Registration::with(['patient', 'doctor.user', 'vitalSign'])
            ->whereDate('created_at', $today)
            ->orderBy('queue_number');

        // Nurses see all pending + vital_check patients by default
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['pending', 'vital_check']);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('queue_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $registrations = $query->get()->map(fn($reg) => [
            'id'            => $reg->id,
            'queue_number'  => $reg->queue_number,
            'status'        => $reg->status,
            'complaint'     => $reg->complaint,
            'has_vital'     => $reg->vitalSign !== null,
            'patient'       => [
                'id'         => $reg->patient->id,
                'name'       => $reg->patient->name,
                'gender'     => $reg->patient->gender,
                'birth_date' => $reg->patient->birth_date?->format('d M Y'),
                'age'        => $reg->patient->birth_date
                    ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
                    : null,
                'phone'      => $reg->patient->phone,
            ],
            'doctor'        => [
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization,
            ],
            'vital_sign'    => $reg->vitalSign ? [
                'systolic'         => $reg->vitalSign->systolic,
                'diastolic'        => $reg->vitalSign->diastolic,
                'temperature'      => $reg->vitalSign->temperature,
                'pulse'            => $reg->vitalSign->pulse,
                'respiratory_rate' => $reg->vitalSign->respiratory_rate,
                'height'           => $reg->vitalSign->height,
                'weight'           => $reg->vitalSign->weight,
                'bmi'              => self::calculateBmi(
                    $reg->vitalSign->height,
                    $reg->vitalSign->weight
                ),
                'notes'            => $reg->vitalSign->notes,
                'recorded_at'      => $reg->vitalSign->created_at->format('H:i'),
            ] : null,
            'created_at'    => $reg->created_at->format('H:i'),
        ]);

        // Summary counts
        $totalToday     = Registration::whereDate('created_at', $today)->count();
        $doneVital      = Registration::whereDate('created_at', $today)
            ->whereHas('vitalSign')
            ->count();
        $waitingVital   = Registration::whereDate('created_at', $today)
            ->whereIn('status', ['pending', 'vital_check'])
            ->whereDoesntHave('vitalSign')
            ->count();

        return Inertia::render('VitalSigns/Index', [
            'registrations' => $registrations,
            'summary'       => [
                'totalToday'   => $totalToday,
                'doneVital'    => $doneVital,
                'waitingVital' => $waitingVital,
            ],
            'filters'       => $request->only(['status', 'search']),
            'statusOptions' => [
                ''             => 'Menunggu & Pemeriksaan',
                'pending'      => 'Menunggu',
                'vital_check'  => 'Pemeriksaan Vital',
                'consultation' => 'Konsultasi',
                'completed'    => 'Selesai',
            ],
        ]);
    }

    /**
     * Show the vital signs input form for a specific registration.
     */
    public function create(Registration $registration): Response
    {
        // If vital signs already exist, redirect to edit
        if ($registration->vitalSign) {
            return redirect()->route('vital-signs.edit', $registration->vitalSign);
        }

        $registration->load(['patient', 'doctor.user']);

        $age = $registration->patient->birth_date
            ? \Carbon\Carbon::parse($registration->patient->birth_date)->age
            : null;

        return Inertia::render('VitalSigns/Create', [
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
                'gender'      => $registration->patient->gender,
                'birth_date'  => $registration->patient->birth_date?->format('d M Y'),
                'age'         => $age,
                'phone'       => $registration->patient->phone,
                'address'     => $registration->patient->address,
            ],
            'doctor' => [
                'name'           => $registration->doctor->user->name ?? '-',
                'specialization' => $registration->doctor->specialization,
            ],
            // Reference ranges for display in the form
            'referenceRanges' => self::referenceRanges(),
        ]);
    }

    /**
     * Store vital signs for a registration and advance the queue status
     * from pending → vital_check (or stay at vital_check if already there).
     */
    public function store(Request $request, Registration $registration): RedirectResponse
    {
        // Prevent duplicate entry
        if ($registration->vitalSign) {
            return redirect()
                ->route('vital-signs.edit', $registration->vitalSign)
                ->with('error', 'Data vital pasien ini sudah ada. Silakan edit data yang sudah ada.');
        }

        $validated = $request->validate([
            'systolic'         => ['nullable', 'integer', 'min:40', 'max:300'],
            'diastolic'        => ['nullable', 'integer', 'min:20', 'max:200'],
            'temperature'      => ['nullable', 'numeric', 'min:30.0', 'max:45.0'],
            'pulse'            => ['nullable', 'integer', 'min:20', 'max:300'],
            'respiratory_rate' => ['nullable', 'integer', 'min:4', 'max:60'],
            'height'           => ['nullable', 'numeric', 'min:30', 'max:250'],
            'weight'           => ['nullable', 'numeric', 'min:1', 'max:300'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        $registration->vitalSign()->create($validated);

        // Advance status: pending → vital_check
        if ($registration->status === 'pending') {
            $registration->update(['status' => 'vital_check']);
        }

        return redirect()
            ->route('vital-signs.index')
            ->with('success', "Data vital pasien {$registration->patient->name} berhasil disimpan. Status antrian diperbarui.");
    }

    /**
     * Show the form for editing existing vital signs.
     */
    public function edit(VitalSign $vitalSign): Response
    {
        $vitalSign->load(['registration.patient', 'registration.doctor.user']);
        $registration = $vitalSign->registration;

        $age = $registration->patient->birth_date
            ? \Carbon\Carbon::parse($registration->patient->birth_date)->age
            : null;

        return Inertia::render('VitalSigns/Edit', [
            'vitalSign' => [
                'id'               => $vitalSign->id,
                'systolic'         => $vitalSign->systolic,
                'diastolic'        => $vitalSign->diastolic,
                'temperature'      => $vitalSign->temperature,
                'pulse'            => $vitalSign->pulse,
                'respiratory_rate' => $vitalSign->respiratory_rate,
                'height'           => $vitalSign->height,
                'weight'           => $vitalSign->weight,
                'bmi'              => self::calculateBmi($vitalSign->height, $vitalSign->weight),
                'notes'            => $vitalSign->notes,
                'recorded_at'      => $vitalSign->created_at->format('d M Y H:i'),
            ],
            'registration' => [
                'id'           => $registration->id,
                'queue_number' => $registration->queue_number,
                'status'       => $registration->status,
                'complaint'    => $registration->complaint,
            ],
            'patient' => [
                'id'         => $registration->patient->id,
                'name'       => $registration->patient->name,
                'gender'     => $registration->patient->gender,
                'birth_date' => $registration->patient->birth_date?->format('d M Y'),
                'age'        => $age,
                'phone'      => $registration->patient->phone,
            ],
            'doctor' => [
                'name'           => $registration->doctor->user->name ?? '-',
                'specialization' => $registration->doctor->specialization,
            ],
            'referenceRanges' => self::referenceRanges(),
        ]);
    }

    /**
     * Update the specified vital signs record.
     */
    public function update(Request $request, VitalSign $vitalSign): RedirectResponse
    {
        $validated = $request->validate([
            'systolic'         => ['nullable', 'integer', 'min:40', 'max:300'],
            'diastolic'        => ['nullable', 'integer', 'min:20', 'max:200'],
            'temperature'      => ['nullable', 'numeric', 'min:30.0', 'max:45.0'],
            'pulse'            => ['nullable', 'integer', 'min:20', 'max:300'],
            'respiratory_rate' => ['nullable', 'integer', 'min:4', 'max:60'],
            'height'           => ['nullable', 'numeric', 'min:30', 'max:250'],
            'weight'           => ['nullable', 'numeric', 'min:1', 'max:300'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        $vitalSign->update($validated);

        $patientName = $vitalSign->registration->patient->name ?? 'Pasien';

        return redirect()
            ->route('vital-signs.index')
            ->with('success', "Data vital pasien {$patientName} berhasil diperbarui.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate BMI from height (cm) and weight (kg).
     * Returns null if either value is missing.
     */
    private static function calculateBmi(?float $heightCm, ?float $weightKg): ?float
    {
        if (!$heightCm || !$weightKg || $heightCm <= 0) {
            return null;
        }

        $heightM = $heightCm / 100;
        $bmi     = $weightKg / ($heightM ** 2);

        return round($bmi, 1);
    }

    /**
     * Normal reference ranges shown to nurses in the form as guidance.
     */
    private static function referenceRanges(): array
    {
        return [
            'systolic'         => ['min' => 90,   'max' => 120,  'unit' => 'mmHg', 'label' => 'Sistolik'],
            'diastolic'        => ['min' => 60,   'max' => 80,   'unit' => 'mmHg', 'label' => 'Diastolik'],
            'temperature'      => ['min' => 36.1, 'max' => 37.2, 'unit' => '°C',   'label' => 'Suhu'],
            'pulse'            => ['min' => 60,   'max' => 100,  'unit' => 'bpm',  'label' => 'Nadi'],
            'respiratory_rate' => ['min' => 12,   'max' => 20,   'unit' => 'x/mnt','label' => 'Pernapasan'],
        ];
    }
}
