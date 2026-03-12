<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of all system users with search & filter.
     */
    public function index(Request $request): Response
    {
        $query = User::query()
            ->withCount(['doctor as is_doctor' => fn($q) => $q->select(\Illuminate\Support\Facades\DB::raw('count(*)'))])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $users = $query->paginate(20)->withQueryString();

        // Role summary counts
        $roleCounts = User::select('role', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role')
            ->toArray();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->through(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'role'       => $u->role,
                'created_at' => $u->created_at->format('d M Y'),
            ]),
            'filters'     => $request->only(['search', 'role']),
            'roleCounts'  => $roleCounts,
            'roleOptions' => self::roleOptions(),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roleOptions' => self::roleOptions(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'                 => ['required', 'string', 'max:20'],
            'role'                  => ['required', Rule::in(array_keys(self::roleOptions()))],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'],
            'role'     => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Pengguna \"{$user->name}\" berhasil ditambahkan.");
    }

    /**
     * Display the specified user's profile.
     */
    public function show(User $user): Response
    {
        $user->load('doctor');

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'role'       => $user->role,
                'created_at' => $user->created_at->format('d M Y H:i'),
                'updated_at' => $user->updated_at->format('d M Y H:i'),
                'doctor'     => $user->doctor ? [
                    'id'             => $user->doctor->id,
                    'specialization' => $user->doctor->specialization,
                    'sip_number'     => $user->doctor->sip_number,
                    'is_active'      => $user->doctor->is_active,
                ] : null,
            ],
            'roleOptions' => self::roleOptions(),
            'roleLabel'   => self::roleOptions()[$user->role] ?? $user->role,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
            'roleOptions' => self::roleOptions(),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => ['required', 'string', 'max:20'],
            'role'     => ['required', Rule::in(array_keys(self::roleOptions()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $payload = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role'  => $validated['role'],
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Data pengguna \"{$user->name}\" berhasil diperbarui.");
    }

    /**
     * Remove the specified user from storage.
     * Prevents deletion of the currently authenticated user and the last admin.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Cannot delete yourself
        if ($request->user()->id === $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // Must keep at least one admin
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return redirect()
                    ->route('admin.users.index')
                    ->with('error', 'Tidak dapat menghapus satu-satunya akun admin.');
            }
        }

        $name = $user->name;

        // Cascade: if the user is linked to a doctor profile, delete that too
        $user->doctor?->delete();
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Pengguna \"{$name}\" berhasil dihapus.");
    }

    /**
     * Toggle user active/inactive status.
     * Uses email_verified_at as the "active" flag (null = inactive).
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        // Cannot deactivate yourself
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        if ($user->email_verified_at) {
            // Deactivate: clear verification timestamp
            $user->update(['email_verified_at' => null]);
            $status = 'dinonaktifkan';
        } else {
            // Activate: set verification timestamp to now
            $user->update(['email_verified_at' => now()]);
            $status = 'diaktifkan';
        }

        return back()->with('success', "Pengguna \"{$user->name}\" berhasil {$status}.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Human-readable role labels (Indonesian).
     */
    private static function roleOptions(): array
    {
        return [
            'admin'        => 'Administrator',
            'doctor'       => 'Dokter',
            'nurse'        => 'Petugas Ruang Tunggu',
            'registration' => 'Petugas Pendaftaran',
            'pharmacist'   => 'Petugas Farmasi',
        ];
    }
}
