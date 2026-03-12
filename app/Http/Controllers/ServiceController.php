<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * Display a listing of all clinic services / tariffs.
     */
    public function index(Request $request): Response
    {
        $query = Service::query()->latest();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $services = $query->paginate(20)->withQueryString();

        $generalCount = Service::where('type', 'general')->count();
        $dentalCount  = Service::where('type', 'dental')->count();

        return Inertia::render('Admin/Services/Index', [
            'services' => $services->through(fn($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'price'      => $s->price,
                'type'       => $s->type,
                'created_at' => $s->created_at->format('d M Y'),
                'updated_at' => $s->updated_at->format('d M Y'),
            ]),
            'filters' => $request->only(['search', 'type']),
            'summary' => [
                'general' => $generalCount,
                'dental'  => $dentalCount,
                'total'   => $generalCount + $dentalCount,
            ],
            'typeOptions' => self::typeOptions(),
        ]);
    }

    /**
     * Show the form for creating a new service.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Services/Create', [
            'typeOptions' => self::typeOptions(),
        ]);
    }

    /**
     * Store a newly created service in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'type'  => ['required', Rule::in(['general', 'dental'])],
        ]);

        $service = Service::create($validated);

        return redirect()
            ->route('admin.services.index')
            ->with('success', "Layanan \"{$service->name}\" berhasil ditambahkan.");
    }

    /**
     * Display the specified service.
     */
    public function show(Service $service): Response
    {
        return Inertia::render('Admin/Services/Show', [
            'service' => [
                'id'         => $service->id,
                'name'       => $service->name,
                'price'      => $service->price,
                'type'       => $service->type,
                'created_at' => $service->created_at->format('d M Y H:i'),
                'updated_at' => $service->updated_at->format('d M Y H:i'),
            ],
            'typeOptions' => self::typeOptions(),
        ]);
    }

    /**
     * Show the form for editing the specified service.
     */
    public function edit(Service $service): Response
    {
        return Inertia::render('Admin/Services/Edit', [
            'service' => [
                'id'    => $service->id,
                'name'  => $service->name,
                'price' => $service->price,
                'type'  => $service->type,
            ],
            'typeOptions' => self::typeOptions(),
        ]);
    }

    /**
     * Update the specified service in storage.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'type'  => ['required', Rule::in(['general', 'dental'])],
        ]);

        $service->update($validated);

        return redirect()
            ->route('admin.services.index')
            ->with('success', "Layanan \"{$service->name}\" berhasil diperbarui.");
    }

    /**
     * Remove the specified service from storage.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $name = $service->name;
        $service->delete();

        return redirect()
            ->route('admin.services.index')
            ->with('success', "Layanan \"{$name}\" berhasil dihapus.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private static function typeOptions(): array
    {
        return [
            'general' => 'Poli Umum',
            'dental'  => 'Poli Gigi',
        ];
    }
}
