<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DoctorController extends Controller
{
    /**
     * Display a listing of doctors.
     */
    public function index(Request $request): Response
    {
        $query = Doctor::with('user')
            ->withCount('registrations')
            ->latest();

        if ($search = $request->input('search')) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"))
                ->orWhere('sip_number', 'like', "%{$search}%");
        }

        if ($specialization = $request->input('specialization')) {
            $query->where('specialization', $specialization);
        }

        if ($request->input('active') !== null) {
            $query->where('is_active', (bool) $request->input('active'));
        }

        $doctors = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Doctors/Index', [
            'doctors' => $doctors->through(fn($d) => [
                'id'                   => $d->id,
                'name'                 => $d->user->name ?? '-',
                'email'                => $d->user->email ?? '-',
                'phone'                => $d->user->phone ?? '-',
                'specialization'       => $d->specialization,
                'sip_number'           => $d->sip_number,
                'is_active'            => $d->is_active,
                'registrations_count'  => $d->registrations_count,
                'created_at'           => $d->created_at->format('d M Y'),
            ]),
            'filters' => $request->only(['search', 'specialization', 'active']),
            'specializationOptions' => [
                'general' => 'Dokter Umum',
                'dental'  => 'Dokter Gigi',
            ],
        ]);
    }

    /**
     * Show the form for creating a new doctor.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Doctors/Create', [
            'specializationOptions' => [
                'general' => 'Dokter Umum',
                'dental'  => 'Dokter Gigi',
            ],
        ]);
    }

    /**
     * Store a newly created doctor.
     * Creates a User account and a Doctor profile in a single transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // User account fields
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'                 => ['required', 'string', 'max:20'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            // Doctor profile fields
            'specialization'        => ['required', Rule::in(['general', 'dental'])],
            'sip_number'            => ['required', 'string', 'max:100', 'unique:doctors,sip_number'],
            'is_active'             => ['boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'phone'    => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'role'     => 'doctor',
            ]);

            Doctor::create([
                'user_id'        => $user->id,
                'specialization' => $validated['specialization'],
                'sip_number'     => $validated['sip_number'],
                'is_active'      => $validated['is_active'] ?? true,
            ]);
        });

        return redirect()
            ->route('admin.doctors.index')
            ->with('success', "Dokter {$validated['name']} berhasil ditambahkan.");
    }

    /**
     * Display the specified doctor's profile and stats.
     */
    public function show(Doctor $doctor): Response
    {
        $doctor->load('user');

        $totalPatients = $doctor->registrations()->distinct('patient_id')->count('patient_id');
        $totalToday    = $doctor->registrations()->whereDate('created_at', now()->toDateString())->count();
        $totalCompleted = $doctor->registrations()->where('status', 'completed')->count();

        // Last 5 registrations
        $recentRegistrations = $doctor->registrations()
            ->with('patient')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($reg) => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'patient_name' => $reg->patient->name ?? '-',
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Doctors/Show', [
            'doctor' => [
                'id'             => $doctor->id,
                'name'           => $doctor->user->name ?? '-',
                'email'          => $doctor->user->email ?? '-',
                'phone'          => $doctor->user->phone ?? '-',
                'specialization' => $doctor->specialization,
                'sip_number'     => $doctor->sip_number,
                'is_active'      => $doctor->is_active,
                'created_at'     => $doctor->created_at->format('d M Y'),
            ],
            'stats' => [
                'totalPatients'  => $totalPatients,
                'totalToday'     => $totalToday,
                'totalCompleted' => $totalCompleted,
            ],
            'recentRegistrations' => $recentRegistrations,
        ]);
    }

    /**
     * Show the form for editing the specified doctor.
     */
    public function edit(Doctor $doctor): Response
    {
        $doctor->load('user');

        return Inertia::render('Admin/Doctors/Edit', [
            'doctor' => [
                'id'             => $doctor->id,
                'name'           => $doctor->user->name ?? '',
                'email'          => $doctor->user->email ?? '',
                'phone'          => $doctor->user->phone ?? '',
                'specialization' => $doctor->specialization,
                'sip_number'     => $doctor->sip_number,
                'is_active'      => $doctor->is_active,
            ],
            'specializationOptions' => [
                'general' => 'Dokter Umum',
                'dental'  => 'Dokter Gigi',
            ],
        ]);
    }

    /**
     * Update the specified doctor (and their linked User account).
     */
    public function update(Request $request, Doctor $doctor): RedirectResponse
    {
        $doctor->load('user');

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($doctor->user->id)],
            'phone'          => ['required', 'string', 'max:20'],
            'password'       => ['nullable', 'string', 'min:8', 'confirmed'],
            'specialization' => ['required', Rule::in(['general', 'dental'])],
            'sip_number'     => ['required', 'string', 'max:100', Rule::unique('doctors', 'sip_number')->ignore($doctor->id)],
            'is_active'      => ['boolean'],
        ]);

        DB::transaction(function () use ($validated, $doctor) {
            $userPayload = [
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ];

            if (!empty($validated['password'])) {
                $userPayload['password'] = Hash::make($validated['password']);
            }

            $doctor->user->update($userPayload);

            $doctor->update([
                'specialization' => $validated['specialization'],
                'sip_number'     => $validated['sip_number'],
                'is_active'      => $validated['is_active'] ?? $doctor->is_active,
            ]);
        });

        return redirect()
            ->route('admin.doctors.show', $doctor)
            ->with('success', "Data dokter {$doctor->user->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified doctor (and their linked User account).
     */
    public function destroy(Doctor $doctor): RedirectResponse
    {
        $doctor->load('user');
        $name = $doctor->user->name ?? 'Dokter';

        DB::transaction(function () use ($doctor) {
            $doctor->user?->delete();
            $doctor->delete();
        });

        return redirect()
            ->route('admin.doctors.index')
            ->with('success', "Dokter {$name} berhasil dihapus.");
    }

    /**
     * Toggle the is_active status of a doctor.
     */
    public function toggleActive(Doctor $doctor): RedirectResponse
    {
        $doctor->load('user');
        $doctor->update(['is_active' => !$doctor->is_active]);

        $status = $doctor->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Dokter {$doctor->user->name} berhasil {$status}.");
    }
}
