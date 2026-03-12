<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of all transactions with filters.
     */
    public function index(Request $request): Response
    {
        $query = Transaction::with([
            'registration.patient',
            'registration.doctor.user',
            'registration.medicalRecord',
        ])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('registration', function ($q) use ($search) {
                $q->where('queue_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($date = $request->input('date')) {
            $query->whereDate('created_at', $date);
        }

        if ($method = $request->input('payment_method')) {
            $query->where('payment_method', $method);
        }

        $transactions = $query->paginate(20)->withQueryString();

        // Summary stats
        $today = now()->toDateString();

        $todayRevenue = Transaction::where('status', 'paid')
            ->whereDate('updated_at', $today)
            ->sum('total_amount');

        $unpaidCount = Transaction::where('status', 'unpaid')->count();

        $todayPaidCount = Transaction::where('status', 'paid')
            ->whereDate('updated_at', $today)
            ->count();

        $monthRevenue = Transaction::where('status', 'paid')
            ->whereYear('updated_at', now()->year)
            ->whereMonth('updated_at', now()->month)
            ->sum('total_amount');

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions->through(fn($t) => self::mapTransaction($t)),
            'filters'      => $request->only(['status', 'search', 'date', 'payment_method']),
            'summary'      => [
                'todayRevenue'  => $todayRevenue,
                'unpaidCount'   => $unpaidCount,
                'todayPaid'     => $todayPaidCount,
                'monthRevenue'  => $monthRevenue,
            ],
            'statusOptions'        => self::statusOptions(),
            'paymentMethodOptions' => self::paymentMethodOptions(),
        ]);
    }

    /**
     * Display the specified transaction with full billing details.
     */
    public function show(Transaction $transaction): Response
    {
        $transaction->load([
            'registration.patient',
            'registration.doctor.user',
            'registration.medicalRecord.prescription.items.medicine',
            'registration.vitalSign',
        ]);

        $reg = $transaction->registration;

        $age = $reg->patient->birth_date
            ? \Carbon\Carbon::parse($reg->patient->birth_date)->age
            : null;

        // Build itemised bill breakdown
        $billItems = [];

        // Prescription medicine items
        if ($reg->medicalRecord?->prescription) {
            foreach ($reg->medicalRecord->prescription->items as $item) {
                $billItems[] = [
                    'type'        => 'medicine',
                    'description' => ($item->medicine->name ?? '-') . " ({$item->dosage})",
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->price_at_moment,
                    'subtotal'    => $item->price_at_moment * $item->quantity,
                ];
            }
        }

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id'             => $transaction->id,
                'total_amount'   => $transaction->total_amount,
                'status'         => $transaction->status,
                'payment_method' => $transaction->payment_method,
                'created_at'     => $transaction->created_at->format('d M Y H:i'),
                'paid_at'        => $transaction->status === 'paid'
                    ? $transaction->updated_at->format('d M Y H:i')
                    : null,
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
            'medicalRecord' => $reg->medicalRecord ? [
                'id'           => $reg->medicalRecord->id,
                'diagnosis'    => $reg->medicalRecord->diagnosis,
                'action_taken' => $reg->medicalRecord->action_taken,
                'notes'        => $reg->medicalRecord->notes,
                'prescription_status' => $reg->medicalRecord->prescription?->status,
            ] : null,
            'billItems'            => $billItems,
            'statusOptions'        => self::statusOptions(),
            'paymentMethodOptions' => self::paymentMethodOptions(),
        ]);
    }

    /**
     * Process payment for a transaction.
     *
     * Expected payload:
     * {
     *   payment_method: 'cash' | 'debit' | 'credit' | 'bpjs' | 'transfer',
     *   amount_paid: numeric   // for change calculation (cash only)
     * }
     */
    public function pay(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->status === 'paid') {
            return back()->with('error', 'Transaksi ini sudah dibayar.');
        }

        $validated = $request->validate([
            'payment_method' => [
                'required',
                Rule::in(array_keys(self::paymentMethodOptions())),
            ],
            'amount_paid' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        // Validate sufficient cash payment
        if (
            $validated['payment_method'] === 'cash' &&
            isset($validated['amount_paid']) &&
            $validated['amount_paid'] < $transaction->total_amount
        ) {
            return back()->withErrors([
                'amount_paid' => 'Jumlah pembayaran kurang dari total tagihan.',
            ])->withInput();
        }

        $transaction->update([
            'status'         => 'paid',
            'payment_method' => $validated['payment_method'],
        ]);

        // Ensure registration is marked completed
        $registration = $transaction->registration;
        if (!in_array($registration->status, ['completed'])) {
            $registration->update(['status' => 'completed']);
        }

        $change = 0;
        if (
            $validated['payment_method'] === 'cash' &&
            isset($validated['amount_paid'])
        ) {
            $change = max(0, $validated['amount_paid'] - $transaction->total_amount);
        }

        $message = 'Pembayaran berhasil dicatat.';
        if ($change > 0) {
            $formattedChange = 'Rp ' . number_format($change, 0, ',', '.');
            $message .= " Kembalian: {$formattedChange}.";
        }

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', $message);
    }

    /**
     * Display a printable receipt for a paid transaction.
     */
    public function receipt(Transaction $transaction): Response
    {
        if ($transaction->status !== 'paid') {
            abort(403, 'Struk hanya tersedia untuk transaksi yang sudah dibayar.');
        }

        $transaction->load([
            'registration.patient',
            'registration.doctor.user',
            'registration.medicalRecord.prescription.items.medicine',
        ]);

        $reg = $transaction->registration;

        // Build itemised receipt
        $receiptItems = [];

        if ($reg->medicalRecord?->prescription) {
            foreach ($reg->medicalRecord->prescription->items as $item) {
                $receiptItems[] = [
                    'description' => ($item->medicine->name ?? '-') . " - {$item->dosage}",
                    'quantity'    => $item->quantity,
                    'unit'        => $item->medicine->unit ?? '-',
                    'unit_price'  => $item->price_at_moment,
                    'subtotal'    => $item->price_at_moment * $item->quantity,
                ];
            }
        }

        // Service fee (total minus medicine cost)
        $medicineCost = collect($receiptItems)->sum('subtotal');
        $serviceFee   = max(0, $transaction->total_amount - $medicineCost);
        if ($serviceFee > 0) {
            array_unshift($receiptItems, [
                'description' => 'Biaya Konsultasi / Tindakan',
                'quantity'    => 1,
                'unit'        => 'layanan',
                'unit_price'  => $serviceFee,
                'subtotal'    => $serviceFee,
            ]);
        }

        return Inertia::render('Transactions/Receipt', [
            'transaction' => [
                'id'             => $transaction->id,
                'total_amount'   => $transaction->total_amount,
                'payment_method' => $transaction->payment_method,
                'paid_at'        => $transaction->updated_at->format('d M Y H:i'),
            ],
            'registration' => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ],
            'patient' => [
                'name'    => $reg->patient->name,
                'nik'     => $reg->patient->nik,
                'phone'   => $reg->patient->phone,
                'address' => $reg->patient->address,
                'gender'  => $reg->patient->gender,
            ],
            'doctor' => [
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization,
                'sip_number'     => $reg->doctor->sip_number,
            ],
            'diagnosis'   => $reg->medicalRecord?->diagnosis,
            'receiptItems'=> $receiptItems,
            'paymentMethodLabel' => self::paymentMethodOptions()[$transaction->payment_method] ?? $transaction->payment_method,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Map a Transaction model to the array shape used in Vue pages.
     */
    private static function mapTransaction(Transaction $t): array
    {
        $reg = $t->registration;

        return [
            'id'             => $t->id,
            'total_amount'   => $t->total_amount,
            'status'         => $t->status,
            'payment_method' => $t->payment_method,
            'created_at'     => $t->created_at->format('d M Y H:i'),
            'paid_at'        => $t->status === 'paid'
                ? $t->updated_at->format('d M Y H:i')
                : null,
            'patient' => [
                'id'     => $reg->patient->id ?? null,
                'name'   => $reg->patient->name ?? '-',
                'phone'  => $reg->patient->phone ?? '-',
                'gender' => $reg->patient->gender ?? '-',
            ],
            'doctor' => [
                'name'           => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization ?? '-',
            ],
            'queue_number' => $reg->queue_number,
            'diagnosis'    => $reg->medicalRecord?->diagnosis,
        ];
    }

    /**
     * Transaction status options (Indonesian).
     */
    private static function statusOptions(): array
    {
        return [
            'unpaid' => 'Belum Dibayar',
            'paid'   => 'Sudah Dibayar',
        ];
    }

    /**
     * Accepted payment methods.
     */
    private static function paymentMethodOptions(): array
    {
        return [
            'cash'     => 'Tunai',
            'debit'    => 'Kartu Debit',
            'credit'   => 'Kartu Kredit',
            'transfer' => 'Transfer Bank',
            'bpjs'     => 'BPJS',
            'qris'     => 'QRIS',
        ];
    }
}
