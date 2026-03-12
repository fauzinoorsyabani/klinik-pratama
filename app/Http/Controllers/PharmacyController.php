<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyController extends Controller
{
    /**
     * Display the pharmacy queue — all incoming prescriptions.
     * Pharmacist sees pending/preparing/ready; can filter by status.
     */
    public function index(Request $request): Response
    {
        $query = Prescription::with([
            'medicalRecord.registration.patient',
            'medicalRecord.registration.doctor.user',
            'medicalRecord.registration.transaction',
            'items.medicine',
        ])->latest();

        // Default: show active prescriptions (not yet delivered)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['pending', 'preparing', 'ready']);
        }

        if ($search = $request->input('search')) {
            $query->whereHas(
                'medicalRecord.registration.patient',
                fn($q) => $q->where('name', 'like', "%{$search}%")
            )->orWhereHas(
                'medicalRecord.registration',
                fn($q) => $q->where('queue_number', 'like', "%{$search}%")
            );
        }

        if ($date = $request->input('date')) {
            $query->whereDate('created_at', $date);
        }

        $prescriptions = $query->paginate(20)->withQueryString();

        // Summary counts
        $pendingCount   = Prescription::where('status', 'pending')->count();
        $preparingCount = Prescription::where('status', 'preparing')->count();
        $readyCount     = Prescription::where('status', 'ready')->count();
        $todayDelivered = Prescription::where('status', 'delivered')
            ->whereDate('updated_at', now()->toDateString())
            ->count();

        return Inertia::render('Pharmacy/Index', [
            'prescriptions' => $prescriptions->through(fn($p) => self::mapPrescription($p)),
            'filters'       => $request->only(['status', 'search', 'date']),
            'summary'       => [
                'pending'        => $pendingCount,
                'preparing'      => $preparingCount,
                'ready'          => $readyCount,
                'todayDelivered' => $todayDelivered,
            ],
            'statusOptions' => self::statusOptions(),
        ]);
    }

    /**
     * Display a single prescription in detail for the pharmacist.
     * Shows all items, patient info, and transaction status.
     */
    public function show(Prescription $prescription): Response
    {
        $prescription->load([
            'items.medicine',
            'medicalRecord.registration.patient',
            'medicalRecord.registration.doctor.user',
            'medicalRecord.registration.vitalSign',
            'medicalRecord.registration.transaction',
        ]);

        $reg = $prescription->medicalRecord->registration;

        $age = $reg->patient->birth_date
            ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
            : null;

        $totalMedicineCost = $prescription->items->sum(
            fn($item) => $item->price_at_moment * $item->quantity
        );

        return Inertia::render('Pharmacy/Show', [
            'prescription' => [
                'id'         => $prescription->id,
                'status'     => $prescription->status,
                'notes'      => $prescription->notes,
                'created_at' => $prescription->created_at->format('d M Y H:i'),
                'updated_at' => $prescription->updated_at->format('d M Y H:i'),
                'items'      => $prescription->items->map(fn($item) => [
                    'id'              => $item->id,
                    'medicine_id'     => $item->medicine_id,
                    'medicine_name'   => $item->medicine->name ?? '-',
                    'medicine_sku'    => $item->medicine->sku ?? '-',
                    'unit'            => $item->medicine->unit ?? '-',
                    'current_stock'   => $item->medicine->stock ?? 0,
                    'quantity'        => $item->quantity,
                    'dosage'          => $item->dosage,
                    'price_at_moment' => $item->price_at_moment,
                    'subtotal'        => $item->price_at_moment * $item->quantity,
                ]),
                'total_medicine_cost' => $totalMedicineCost,
            ],
            'medicalRecord' => [
                'id'           => $prescription->medicalRecord->id,
                'diagnosis'    => $prescription->medicalRecord->diagnosis,
                'action_taken' => $prescription->medicalRecord->action_taken,
                'notes'        => $prescription->medicalRecord->notes,
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
            ] : null,
            'transaction' => $reg->transaction ? [
                'id'             => $reg->transaction->id,
                'total_amount'   => $reg->transaction->total_amount,
                'status'         => $reg->transaction->status,
                'payment_method' => $reg->transaction->payment_method,
            ] : null,
            'statusOptions' => self::statusOptions(),
            'nextStatus'    => self::nextStatus($prescription->status),
        ]);
    }

    /**
     * Update the status of a prescription.
     *
     * Allowed transitions:
     *   pending → preparing → ready → delivered
     *
     * When status becomes 'delivered':
     *   - Registration status is advanced to 'completed'
     *   - (Transaction payment is handled separately by cashier)
     */
    public function updateStatus(Request $request, Prescription $prescription): RedirectResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['pending', 'preparing', 'ready', 'delivered']),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $currentStatus = $prescription->status;
        $newStatus     = $validated['status'];

        // Enforce forward-only transitions
        $allowedTransitions = self::allowedTransitions();
        $allowed = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return back()->with(
                'error',
                "Status tidak dapat diubah dari \"{$this->statusLabel($currentStatus)}\" ke \"{$this->statusLabel($newStatus)}\"."
            );
        }

        DB::transaction(function () use ($prescription, $newStatus, $validated) {
            $updateData = ['status' => $newStatus];

            if (!empty($validated['notes'])) {
                $updateData['notes'] = $validated['notes'];
            }

            $prescription->update($updateData);

            // When medicine is delivered, complete the registration
            if ($newStatus === 'delivered') {
                $registration = $prescription->medicalRecord->registration;

                if ($registration->status === 'pharmacy') {
                    $registration->update(['status' => 'completed']);
                }
            }
        });

        $label = $this->statusLabel($newStatus);

        return back()->with('success', "Status resep berhasil diubah menjadi \"{$label}\".");
    }

    /**
     * Quick-advance a prescription to the next logical status.
     * Used by the "Proses Selanjutnya" button on the pharmacy queue board.
     */
    public function advance(Prescription $prescription): RedirectResponse
    {
        $next = self::nextStatus($prescription->status);

        if (!$next) {
            return back()->with('error', 'Resep ini sudah selesai diproses.');
        }

        DB::transaction(function () use ($prescription, $next) {
            $prescription->update(['status' => $next]);

            if ($next === 'delivered') {
                $registration = $prescription->medicalRecord->registration;
                if ($registration->status === 'pharmacy') {
                    $registration->update(['status' => 'completed']);
                }
            }
        });

        $label = $this->statusLabel($next);

        return back()->with('success', "Resep berhasil diproses: status sekarang \"{$label}\".");
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Map a Prescription model to the array shape used in Vue pages.
     */
    private static function mapPrescription(Prescription $p): array
    {
        $reg = $p->medicalRecord->registration;

        $totalMedicineCost = $p->items->sum(
            fn($item) => $item->price_at_moment * $item->quantity
        );

        return [
            'id'                  => $p->id,
            'status'              => $p->status,
            'notes'               => $p->notes,
            'total_medicine_cost' => $totalMedicineCost,
            'items_count'         => $p->items->count(),
            'items_preview'       => $p->items->take(3)->map(fn($item) => [
                'medicine_name' => $item->medicine->name ?? '-',
                'quantity'      => $item->quantity,
                'unit'          => $item->medicine->unit ?? '-',
                'dosage'        => $item->dosage,
            ]),
            'patient'      => [
                'id'     => $reg->patient->id,
                'name'   => $reg->patient->name,
                'gender' => $reg->patient->gender,
                'phone'  => $reg->patient->phone,
            ],
            'doctor'       => [
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization,
            ],
            'queue_number' => $reg->queue_number,
            'diagnosis'    => $p->medicalRecord->diagnosis,
            'transaction'  => $reg->transaction ? [
                'total_amount'   => $reg->transaction->total_amount,
                'status'         => $reg->transaction->status,
                'payment_method' => $reg->transaction->payment_method,
            ] : null,
            'created_at'   => $p->created_at->format('d M Y H:i'),
            'updated_at'   => $p->updated_at->format('d M Y H:i'),
        ];
    }

    /**
     * Return the next status in the prescription workflow,
     * or null if already at the final state.
     */
    private static function nextStatus(string $current): ?string
    {
        $flow = [
            'pending'   => 'preparing',
            'preparing' => 'ready',
            'ready'     => 'delivered',
        ];

        return $flow[$current] ?? null;
    }

    /**
     * Map each status to a list of statuses it can transition to.
     */
    private static function allowedTransitions(): array
    {
        return [
            'pending'   => ['preparing'],
            'preparing' => ['ready', 'pending'],   // allow rollback to pending
            'ready'     => ['delivered', 'preparing'], // allow rollback
            'delivered' => [],                     // terminal state
        ];
    }

    /**
     * Human-readable status labels (Indonesian).
     */
    private static function statusOptions(): array
    {
        return [
            'pending'   => 'Menunggu Diproses',
            'preparing' => 'Sedang Disiapkan',
            'ready'     => 'Siap Diambil',
            'delivered' => 'Sudah Diserahkan',
        ];
    }

    private function statusLabel(string $status): string
    {
        return self::statusOptions()[$status] ?? $status;
    }
}
