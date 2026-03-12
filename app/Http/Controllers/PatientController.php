<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    /**
     * Display a listing of patients with search & pagination.
     */
    public function index(Request $request): Response
    {
        $query = Patient::query()
            ->withCount('registrations')
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($gender = $request->input('gender')) {
            $query->where('gender', $gender);
        }

        $patients = $query->paginate(15)->withQueryString();

        return Inertia::render('Patients/Index', [
            'patients' => $patients->through(fn($p) => [
                'id'                   => $p->id,
                'nik'                  => $p->nik,
                'name'                 => $p->name,
                'birth_place'          => $p->birth_place,
                'birth_date'           => $p->birth_date?->format('d M Y'),
                'gender'               => $p->gender,
                'address'              => $p->address,
                'phone'                => $p->phone,
                'email'                => $p->email,
                'registrations_count'  => $p->registrations_count,
                'created_at'           => $p->created_at->format('d M Y'),
            ]),
            'filters' => $request->only(['search', 'gender']),
        ]);
    }

    /**
     * Show the form for creating a new patient.
     */
    public function create(): Response
    {
        return Inertia::render('Patients/Create');
    }

    /**
     * Store a newly created patient in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nik'         => ['nullable', 'string', 'size:16', 'unique:patients,nik'],
            'name'        => ['required', 'string', 'max:255'],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date'  => ['required', 'date', 'before:today'],
            'gender'      => ['required', Rule::in(['male', 'female'])],
            'address'     => ['required', 'string', 'max:500'],
            'phone'       => ['required', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:255'],
        ]);

        $patient = Patient::create($validated);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', "Pasien {$patient->name} berhasil ditambahkan.");
    }

    /**
     * Display the specified patient with their latest records.
     */
    public function show(Patient $patient): Response
    {
        $patient->load([
            'registrations' => fn($q) => $q->with(['doctor.user', 'vitalSign', 'medicalRecord.prescription'])
                ->orderByDesc('created_at')
                ->limit(10),
        ]);

        $age = $patient->birth_date
            ? \Carbon\Carbon::parse($patient->birth_date)->age
            : null;

        return Inertia::render('Patients/Show', [
            'patient' => [
                'id'          => $patient->id,
                'nik'         => $patient->nik,
                'name'        => $patient->name,
                'birth_place' => $patient->birth_place,
                'birth_date'  => $patient->birth_date?->format('d M Y'),
                'age'         => $age,
                'gender'      => $patient->gender,
                'address'     => $patient->address,
                'phone'       => $patient->phone,
                'email'       => $patient->email,
                'created_at'  => $patient->created_at->format('d M Y'),
            ],
            'registrations' => $patient->registrations->map(fn($reg) => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'doctor_name'  => $reg->doctor->user->name ?? '-',
                'diagnosis'    => $reg->medicalRecord->diagnosis ?? null,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified patient.
     */
    public function edit(Patient $patient): Response
    {
        return Inertia::render('Patients/Edit', [
            'patient' => [
                'id'          => $patient->id,
                'nik'         => $patient->nik,
                'name'        => $patient->name,
                'birth_place' => $patient->birth_place,
                'birth_date'  => $patient->birth_date?->format('Y-m-d'),
                'gender'      => $patient->gender,
                'address'     => $patient->address,
                'phone'       => $patient->phone,
                'email'       => $patient->email,
            ],
        ]);
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'nik'         => ['nullable', 'string', 'size:16', Rule::unique('patients', 'nik')->ignore($patient->id)],
            'name'        => ['required', 'string', 'max:255'],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date'  => ['required', 'date', 'before:today'],
            'gender'      => ['required', Rule::in(['male', 'female'])],
            'address'     => ['required', 'string', 'max:500'],
            'phone'       => ['required', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:255'],
        ]);

        $patient->update($validated);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', "Data pasien {$patient->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified patient from storage.
     */
    public function destroy(Patient $patient): RedirectResponse
    {
        $name = $patient->name;
        $patient->delete();

        return redirect()
            ->route('patients.index')
            ->with('success', "Pasien {$name} berhasil dihapus.");
    }

    /**
     * Full visit history for a patient.
     */
    public function history(Patient $patient): Response
    {
        $registrations = Registration::with([
            'doctor.user',
            'vitalSign',
            'medicalRecord.prescription.items.medicine',
            'transaction',
        ])
            ->where('patient_id', $patient->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return Inertia::render('Patients/History', [
            'patient' => [
                'id'     => $patient->id,
                'name'   => $patient->name,
                'nik'    => $patient->nik,
                'gender' => $patient->gender,
                'phone'  => $patient->phone,
            ],
            'registrations' => $registrations->through(fn($reg) => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'doctor_name'  => $reg->doctor->user->name ?? '-',
                'vital_sign'   => $reg->vitalSign ? [
                    'systolic'         => $reg->vitalSign->systolic,
                    'diastolic'        => $reg->vitalSign->diastolic,
                    'temperature'      => $reg->vitalSign->temperature,
                    'pulse'            => $reg->vitalSign->pulse,
                    'respiratory_rate' => $reg->vitalSign->respiratory_rate,
                    'height'           => $reg->vitalSign->height,
                    'weight'           => $reg->vitalSign->weight,
                ] : null,
                'medical_record' => $reg->medicalRecord ? [
                    'id'           => $reg->medicalRecord->id,
                    'diagnosis'    => $reg->medicalRecord->diagnosis,
                    'action_taken' => $reg->medicalRecord->action_taken,
                    'notes'        => $reg->medicalRecord->notes,
                    'prescription' => $reg->medicalRecord->prescription ? [
                        'status' => $reg->medicalRecord->prescription->status,
                        'items'  => $reg->medicalRecord->prescription->items->map(fn($item) => [
                            'medicine_name' => $item->medicine->name ?? '-',
                            'quantity'      => $item->quantity,
                            'dosage'        => $item->dosage,
                            'unit'          => $item->medicine->unit ?? '-',
                        ]),
                    ] : null,
                ] : null,
                'transaction' => $reg->transaction ? [
                    'total_amount'   => $reg->transaction->total_amount,
                    'status'         => $reg->transaction->status,
                    'payment_method' => $reg->transaction->payment_method,
                ] : null,
                'created_at' => $reg->created_at->format('d M Y H:i'),
            ]),
        ]);
    }
}
