<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    /**
     * Display a listing of all registrations with filters.
     */
    public function index(Request $request): Response
    {
        $query = Registration::with(['patient', 'doctor.user'])
            ->latest();

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
            // Default: show today's registrations
            $query->whereDate('created_at', now()->toDateString());
        }

        if ($doctorId = $request->input('doctor_id')) {
            $query->where('doctor_id', $doctorId);
        }

        $registrations = $query->paginate(20)->withQueryString();

        $doctors = Doctor::with('user')
            ->where('is_active', true)
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->user->name ?? '-',
                'specialization' => $d->specialization,
            ]);

        return Inertia::render('Registrations/Index', [
            'registrations' => $registrations->through(fn($reg) => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'patient'      => [
                    'id'     => $reg->patient->id,
                    'name'   => $reg->patient->name,
                    'nik'    => $reg->patient->nik,
                    'phone'  => $reg->patient->phone,
                    'gender' => $reg->patient->gender,
                ],
                'doctor' => [
                    'id'             => $reg->doctor->id,
                    'name'           => $reg->doctor->user->name ?? '-',
                    'specialization' => $reg->doctor->specialization,
                ],
                'status'     => $reg->status,
                'complaint'  => $reg->complaint,
                'created_at' => $reg->created_at->format('d M Y H:i'),
            ]),
            'filters' => $request->only(['search', 'status', 'date', 'doctor_id']),
            'doctors' => $doctors,
            'statusOptions' => self::statusOptions(),
        ]);
    }

    /**
     * Show the form for creating a new registration.
     */
    public function create(Request $request): Response
    {
        $doctors = Doctor::with('user')
            ->where('is_active', true)
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->user->name ?? '-',
                'specialization' => $d->specialization,
            ]);

        // Allow pre-filling patient if coming from patient profile
        $patient = null;
        if ($patientId = $request->input('patient_id')) {
            $p = Patient::find($patientId);
            if ($p) {
                $patient = [
                    'id'     => $p->id,
                    'name'   => $p->name,
                    'nik'    => $p->nik,
                    'phone'  => $p->phone,
                    'gender' => $p->gender,
                ];
            }
        }

        return Inertia::render('Registrations/Create', [
            'doctors'        => $doctors,
            'selectedPatient' => $patient,
        ]);
    }

    /**
     * Store a newly created registration and auto-generate queue number.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id'      => ['required', 'exists:patients,id'],
            'doctor_id'       => ['required', 'exists:doctors,id'],
            'complaint'       => ['nullable', 'string', 'max:1000'],
            // Allow walk-in (offline) or online flag if needed
            'registration_type' => ['nullable', Rule::in(['online', 'offline'])],
        ]);

        // Generate queue number: e.g. "A-001" for general, "B-001" for dental
        $doctor = Doctor::find($validated['doctor_id']);
        $prefix = $doctor->specialization === 'dental' ? 'B' : 'A';
        $todayCount = Registration::whereDate('created_at', now()->toDateString())
            ->whereHas('doctor', fn($q) => $q->where('specialization', $doctor->specialization))
            ->count();
        $queueNumber = $prefix . '-' . str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);

        $registration = Registration::create([
            'patient_id'   => $validated['patient_id'],
            'doctor_id'    => $validated['doctor_id'],
            'queue_number' => $queueNumber,
            'status'       => 'pending',
            'complaint'    => $validated['complaint'] ?? null,
        ]);

        return redirect()
            ->route('registrations.show', $registration)
            ->with('success', "Pendaftaran berhasil! Nomor antrian: {$queueNumber}");
    }

    /**
     * Display the specified registration detail.
     */
    public function show(Registration $registration): Response
    {
        $registration->load([
            'patient',
            'doctor.user',
            'vitalSign',
            'medicalRecord.prescription.items.medicine',
            'transaction',
        ]);

        return Inertia::render('Registrations/Show', [
            'registration' => [
                'id'           => $registration->id,
                'queue_number' => $registration->queue_number,
                'status'       => $registration->status,
                'complaint'    => $registration->complaint,
                'created_at'   => $registration->created_at->format('d M Y H:i'),
                'patient'      => [
                    'id'          => $registration->patient->id,
                    'name'        => $registration->patient->name,
                    'nik'         => $registration->patient->nik,
                    'phone'       => $registration->patient->phone,
                    'gender'      => $registration->patient->gender,
                    'birth_date'  => $registration->patient->birth_date?->format('d M Y'),
                    'address'     => $registration->patient->address,
                ],
                'doctor' => [
                    'id'             => $registration->doctor->id,
                    'name'           => $registration->doctor->user->name ?? '-',
                    'specialization' => $registration->doctor->specialization,
                    'sip_number'     => $registration->doctor->sip_number,
                ],
                'vital_sign' => $registration->vitalSign ? [
                    'systolic'         => $registration->vitalSign->systolic,
                    'diastolic'        => $registration->vitalSign->diastolic,
                    'temperature'      => $registration->vitalSign->temperature,
                    'pulse'            => $registration->vitalSign->pulse,
                    'respiratory_rate' => $registration->vitalSign->respiratory_rate,
                    'height'           => $registration->vitalSign->height,
                    'weight'           => $registration->vitalSign->weight,
                    'notes'            => $registration->vitalSign->notes,
                ] : null,
                'medical_record' => $registration->medicalRecord ? [
                    'id'           => $registration->medicalRecord->id,
                    'diagnosis'    => $registration->medicalRecord->diagnosis,
                    'action_taken' => $registration->medicalRecord->action_taken,
                    'notes'        => $registration->medicalRecord->notes,
                    'prescription' => $registration->medicalRecord->prescription ? [
                        'id'     => $registration->medicalRecord->prescription->id,
                        'status' => $registration->medicalRecord->prescription->status,
                        'notes'  => $registration->medicalRecord->prescription->notes,
                        'items'  => $registration->medicalRecord->prescription->items->map(fn($item) => [
                            'id'            => $item->id,
                            'medicine_name' => $item->medicine->name ?? '-',
                            'quantity'      => $item->quantity,
                            'dosage'        => $item->dosage,
                            'unit'          => $item->medicine->unit ?? '-',
                            'price'         => $item->price_at_moment,
                        ]),
                    ] : null,
                ] : null,
                'transaction' => $registration->transaction ? [
                    'id'             => $registration->transaction->id,
                    'total_amount'   => $registration->transaction->total_amount,
                    'status'         => $registration->transaction->status,
                    'payment_method' => $registration->transaction->payment_method,
                ] : null,
            ],
            'statusOptions' => self::statusOptions(),
        ]);
    }

    /**
     * Show the form for editing a registration.
     */
    public function edit(Registration $registration): Response
    {
        $doctors = Doctor::with('user')
            ->where('is_active', true)
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->user->name ?? '-',
                'specialization' => $d->specialization,
            ]);

        return Inertia::render('Registrations/Edit', [
            'registration' => [
                'id'         => $registration->id,
                'patient_id' => $registration->patient_id,
                'doctor_id'  => $registration->doctor_id,
                'status'     => $registration->status,
                'complaint'  => $registration->complaint,
                'patient'    => [
                    'id'   => $registration->patient->id,
                    'name' => $registration->patient->name,
                ],
            ],
            'doctors'       => $doctors,
            'statusOptions' => self::statusOptions(),
        ]);
    }

    /**
     * Update the specified registration.
     */
    public function update(Request $request, Registration $registration): RedirectResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'status'    => ['required', Rule::in(['pending', 'vital_check', 'consultation', 'pharmacy', 'completed', 'cancelled'])],
            'complaint' => ['nullable', 'string', 'max:1000'],
        ]);

        $registration->update($validated);

        return redirect()
            ->route('registrations.show', $registration)
            ->with('success', 'Data pendaftaran berhasil diperbarui.');
    }

    /**
     * Remove the specified registration.
     */
    public function destroy(Registration $registration): RedirectResponse
    {
        $queueNumber = $registration->queue_number;
        $registration->delete();

        return redirect()
            ->route('registrations.index')
            ->with('success', "Pendaftaran antrian {$queueNumber} berhasil dihapus.");
    }

    /**
     * Cancel a registration (soft status change).
     */
    public function cancel(Registration $registration): RedirectResponse
    {
        if (in_array($registration->status, ['completed', 'cancelled'])) {
            return back()->with('error', 'Pendaftaran ini tidak dapat dibatalkan.');
        }

        $registration->update(['status' => 'cancelled']);

        return back()->with('success', "Antrian {$registration->queue_number} telah dibatalkan.");
    }

    /**
     * Show today's queue board with real-time status.
     */
    public function today(Request $request): Response
    {
        $today = now()->toDateString();

        $queueQuery = Registration::with(['patient', 'doctor.user'])
            ->whereDate('created_at', $today)
            ->orderBy('queue_number');

        if ($doctorId = $request->input('doctor_id')) {
            $queueQuery->where('doctor_id', $doctorId);
        }

        if ($status = $request->input('status')) {
            $queueQuery->where('status', $status);
        }

        $queue = $queueQuery->get()->map(fn($reg) => [
            'id'           => $reg->id,
            'queue_number' => $reg->queue_number,
            'status'       => $reg->status,
            'complaint'    => $reg->complaint,
            'patient'      => [
                'id'     => $reg->patient->id,
                'name'   => $reg->patient->name,
                'gender' => $reg->patient->gender,
                'phone'  => $reg->patient->phone,
            ],
            'doctor' => [
                'id'             => $reg->doctor->id,
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization,
            ],
            'created_at' => $reg->created_at->format('H:i'),
        ]);

        // Summary counts per status
        $summary = [];
        foreach (array_keys(self::statusOptions()) as $status) {
            $summary[$status] = $queue->where('status', $status)->count();
        }

        $doctors = Doctor::with('user')
            ->where('is_active', true)
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->user->name ?? '-',
                'specialization' => $d->specialization,
            ]);

        return Inertia::render('Registrations/Today', [
            'queue'         => $queue,
            'summary'       => $summary,
            'doctors'       => $doctors,
            'filters'       => $request->only(['doctor_id', 'status']),
            'statusOptions' => self::statusOptions(),
            'date'          => now()->translatedFormat('l, d F Y'),
        ]);
    }

    /**
     * Advance the registration to the next status in the workflow.
     * Exposed as a POST from the queue board or detail page.
     */
    public function advance(Registration $registration): RedirectResponse
    {
        $flow = [
            'pending'      => 'vital_check',
            'vital_check'  => 'consultation',
            'consultation' => 'pharmacy',
            'pharmacy'     => 'completed',
        ];

        $current = $registration->status;

        if (!isset($flow[$current])) {
            return back()->with('error', 'Status tidak dapat diubah.');
        }

        $registration->update(['status' => $flow[$current]]);

        return back()->with('success', "Status antrian {$registration->queue_number} diperbarui.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private static function statusOptions(): array
    {
        return [
            'pending'      => 'Menunggu',
            'vital_check'  => 'Pemeriksaan Vital',
            'consultation' => 'Konsultasi Dokter',
            'pharmacy'     => 'Farmasi',
            'completed'    => 'Selesai',
            'cancelled'    => 'Dibatalkan',
        ];
    }
}
