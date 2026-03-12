<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Registration;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display the reports overview / landing page.
     * Shows summary stats for quick access.
     */
    public function index(Request $request): Response
    {
        $today     = now()->toDateString();
        $thisYear  = now()->year;
        $thisMonth = now()->month;

        // ── Year-to-date totals ───────────────────────────────────────────
        $ytdPatients      = Patient::whereYear('created_at', $thisYear)->count();
        $ytdRegistrations = Registration::whereYear('created_at', $thisYear)->count();
        $ytdRevenue       = Transaction::where('status', 'paid')
            ->whereYear('updated_at', $thisYear)
            ->sum('total_amount');
        $ytdCompleted     = Registration::where('status', 'completed')
            ->whereYear('created_at', $thisYear)
            ->count();

        // ── Monthly summary for current year ─────────────────────────────
        $monthlyRegistrations = Registration::select(
                DB::raw("strftime('%m', created_at) as month"),
                DB::raw('count(*) as total')
            )
            ->whereYear('created_at', $thisYear)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlyRevenue = Transaction::select(
                DB::raw("strftime('%m', updated_at) as month"),
                DB::raw('sum(total_amount) as total')
            )
            ->where('status', 'paid')
            ->whereYear('updated_at', $thisYear)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlyLabels = [];
        $monthlyRegData = [];
        $monthlyRevData = [];

        for ($m = 1; $m <= 12; $m++) {
            $key               = str_pad($m, 2, '0', STR_PAD_LEFT);
            $monthlyLabels[]   = now()->setMonth($m)->format('M');
            $monthlyRegData[]  = (int)   ($monthlyRegistrations[$key]->total ?? 0);
            $monthlyRevData[]  = (float) ($monthlyRevenue[$key]->total       ?? 0);
        }

        // ── Doctor performance (YTD) ──────────────────────────────────────
        $doctorPerformance = Doctor::with('user')
            ->withCount([
                'registrations as total_patients_ytd' => fn($q) =>
                    $q->whereYear('created_at', $thisYear),
                'registrations as completed_ytd' => fn($q) =>
                    $q->whereYear('created_at', $thisYear)->where('status', 'completed'),
            ])
            ->where('is_active', true)
            ->orderByDesc('total_patients_ytd')
            ->limit(10)
            ->get()
            ->map(fn($d) => [
                'name'               => $d->user->name ?? '-',
                'specialization'     => $d->specialization,
                'total_patients_ytd' => $d->total_patients_ytd,
                'completed_ytd'      => $d->completed_ytd,
                'completion_rate'    => $d->total_patients_ytd > 0
                    ? round(($d->completed_ytd / $d->total_patients_ytd) * 100, 1)
                    : 0,
            ]);

        // ── Top diagnoses (YTD) ───────────────────────────────────────────
        $topDiagnoses = \App\Models\MedicalRecord::select(
                'diagnosis',
                DB::raw('count(*) as total')
            )
            ->whereYear('created_at', $thisYear)
            ->groupBy('diagnosis')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($d) => [
                'diagnosis' => \Illuminate\Support\Str::limit($d->diagnosis, 60),
                'total'     => $d->total,
            ]);

        // ── Low-stock alert ───────────────────────────────────────────────
        $lowStockCount = Medicine::where('stock', '<', 10)->count();

        return Inertia::render('Admin/Reports/Index', [
            'year' => $thisYear,
            'ytd'  => [
                'patients'      => $ytdPatients,
                'registrations' => $ytdRegistrations,
                'revenue'       => $ytdRevenue,
                'completed'     => $ytdCompleted,
            ],
            'monthlyChart' => [
                'labels'        => $monthlyLabels,
                'registrations' => $monthlyRegData,
                'revenue'       => $monthlyRevData,
            ],
            'doctorPerformance' => $doctorPerformance,
            'topDiagnoses'      => $topDiagnoses,
            'lowStockCount'     => $lowStockCount,
        ]);
    }

    /**
     * Daily report — detailed breakdown for a specific date.
     * Defaults to today.
     */
    public function daily(Request $request): Response
    {
        $date = $request->input('date', now()->toDateString());

        // ── Registration flow counts ──────────────────────────────────────
        $statuses = ['pending', 'vital_check', 'consultation', 'pharmacy', 'completed', 'cancelled'];

        $statusCounts = Registration::whereDate('created_at', $date)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $queueSummary = [];
        foreach ($statuses as $status) {
            $queueSummary[$status] = $statusCounts[$status] ?? 0;
        }

        $totalRegistrations = array_sum($queueSummary);
        $completedCount     = $queueSummary['completed'];
        $cancelledCount     = $queueSummary['cancelled'];

        // ── Revenue breakdown ─────────────────────────────────────────────
        $revenueTotal = Transaction::where('status', 'paid')
            ->whereDate('updated_at', $date)
            ->sum('total_amount');

        $revenueByMethod = Transaction::where('status', 'paid')
            ->whereDate('updated_at', $date)
            ->select('payment_method', DB::raw('sum(total_amount) as total'), DB::raw('count(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn($r) => [
                'method' => $r->payment_method ?? 'unknown',
                'label'  => self::paymentMethodLabel($r->payment_method),
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        $unpaidCount  = Transaction::where('status', 'unpaid')
            ->whereDate('created_at', $date)
            ->count();
        $unpaidAmount = Transaction::where('status', 'unpaid')
            ->whereDate('created_at', $date)
            ->sum('total_amount');

        // ── Patient list for the day ──────────────────────────────────────
        $registrations = Registration::with([
                'patient',
                'doctor.user',
                'medicalRecord',
                'transaction',
            ])
            ->whereDate('created_at', $date)
            ->orderBy('queue_number')
            ->get()
            ->map(fn($reg) => [
                'id'             => $reg->id,
                'queue_number'   => $reg->queue_number,
                'status'         => $reg->status,
                'complaint'      => $reg->complaint,
                'patient_name'   => $reg->patient->name ?? '-',
                'patient_gender' => $reg->patient->gender ?? '-',
                'doctor_name'    => $reg->doctor->user->name ?? '-',
                'specialization' => $reg->doctor->specialization ?? '-',
                'diagnosis'      => $reg->medicalRecord->diagnosis ?? null,
                'transaction'    => $reg->transaction ? [
                    'total_amount'   => $reg->transaction->total_amount,
                    'status'         => $reg->transaction->status,
                    'payment_method' => $reg->transaction->payment_method,
                ] : null,
                'registered_at'  => $reg->created_at->format('H:i'),
            ]);

        // ── Per-doctor breakdown ──────────────────────────────────────────
        $perDoctor = Registration::with('doctor.user')
            ->whereDate('created_at', $date)
            ->select('doctor_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('doctor_id', 'status')
            ->get()
            ->groupBy('doctor_id')
            ->map(fn($rows) => [
                'doctor_name'    => $rows->first()->doctor->user->name ?? '-',
                'specialization' => $rows->first()->doctor->specialization ?? '-',
                'total'          => $rows->sum('total'),
                'completed'      => (int) ($rows->firstWhere('status', 'completed')?->total ?? 0),
                'cancelled'      => (int) ($rows->firstWhere('status', 'cancelled')?->total ?? 0),
            ])
            ->values();

        // ── New patients registered on this day ───────────────────────────
        $newPatients = Patient::whereDate('created_at', $date)->count();

        return Inertia::render('Admin/Reports/Daily', [
            'date'              => $date,
            'dateFormatted'     => \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y'),
            'totalRegistrations'=> $totalRegistrations,
            'completedCount'    => $completedCount,
            'cancelledCount'    => $cancelledCount,
            'newPatients'       => $newPatients,
            'queueSummary'      => $queueSummary,
            'revenue'           => [
                'total'         => $revenueTotal,
                'byMethod'      => $revenueByMethod,
                'unpaidCount'   => $unpaidCount,
                'unpaidAmount'  => $unpaidAmount,
            ],
            'registrations' => $registrations,
            'perDoctor'     => $perDoctor,
            'statusLabels'  => self::statusLabels(),
        ]);
    }

    /**
     * Monthly report — aggregate data for a specific month/year.
     * Defaults to the current month.
     */
    public function monthly(Request $request): Response
    {
        $year  = (int) $request->input('year',  now()->year);
        $month = (int) $request->input('month', now()->month);

        $startOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        // ── Top-level totals ──────────────────────────────────────────────
        $totalRegistrations = Registration::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $completedCount     = Registration::where('status', 'completed')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();
        $newPatients = Patient::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        $revenueTotal = Transaction::where('status', 'paid')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $unpaidCount  = Transaction::where('status', 'unpaid')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();
        $unpaidAmount = Transaction::where('status', 'unpaid')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        // ── Daily trend within the month ──────────────────────────────────
        $daysInMonth = $startOfMonth->daysInMonth;

        $dailyRegistrations = Registration::select(
                DB::raw("strftime('%d', created_at) as day"),
                DB::raw('count(*) as total')
            )
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $dailyRevenue = Transaction::select(
                DB::raw("strftime('%d', updated_at) as day"),
                DB::raw('sum(total_amount) as total')
            )
            ->where('status', 'paid')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $dailyLabels  = [];
        $dailyRegData = [];
        $dailyRevData = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $key            = str_pad($d, 2, '0', STR_PAD_LEFT);
            $dailyLabels[]  = (string) $d;
            $dailyRegData[] = (int)   ($dailyRegistrations[$key]->total ?? 0);
            $dailyRevData[] = (float) ($dailyRevenue[$key]->total       ?? 0);
        }

        // ── Revenue by payment method ─────────────────────────────────────
        $revenueByMethod = Transaction::where('status', 'paid')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->select('payment_method', DB::raw('sum(total_amount) as total'), DB::raw('count(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn($r) => [
                'method' => $r->payment_method ?? 'unknown',
                'label'  => self::paymentMethodLabel($r->payment_method),
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        // ── Per-doctor breakdown ──────────────────────────────────────────
        $perDoctor = Doctor::with('user')
            ->withCount([
                'registrations as total_month' => fn($q) =>
                    $q->whereBetween('created_at', [$startOfMonth, $endOfMonth]),
                'registrations as completed_month' => fn($q) =>
                    $q->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                      ->where('status', 'completed'),
            ])
            ->orderByDesc('total_month')
            ->get()
            ->map(fn($d) => [
                'name'            => $d->user->name ?? '-',
                'specialization'  => $d->specialization,
                'total'           => $d->total_month,
                'completed'       => $d->completed_month,
                'completion_rate' => $d->total_month > 0
                    ? round(($d->completed_month / $d->total_month) * 100, 1)
                    : 0,
            ]);

        // ── Top diagnoses for the month ───────────────────────────────────
        $topDiagnoses = \App\Models\MedicalRecord::select(
                'diagnosis',
                DB::raw('count(*) as total')
            )
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('diagnosis')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($d) => [
                'diagnosis' => \Illuminate\Support\Str::limit($d->diagnosis, 70),
                'total'     => $d->total,
            ]);

        // ── Available years for the year picker ───────────────────────────
        $availableYears = Registration::select(
                DB::raw("strftime('%Y', created_at) as year")
            )
            ->groupBy('year')
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (empty($availableYears)) {
            $availableYears = [now()->year];
        }

        return Inertia::render('Admin/Reports/Monthly', [
            'year'  => $year,
            'month' => $month,
            'monthFormatted'     => $startOfMonth->translatedFormat('F Y'),
            'totalRegistrations' => $totalRegistrations,
            'completedCount'     => $completedCount,
            'newPatients'        => $newPatients,
            'revenue'            => [
                'total'        => $revenueTotal,
                'byMethod'     => $revenueByMethod,
                'unpaidCount'  => $unpaidCount,
                'unpaidAmount' => $unpaidAmount,
            ],
            'dailyChart' => [
                'labels'        => $dailyLabels,
                'registrations' => $dailyRegData,
                'revenue'       => $dailyRevData,
            ],
            'perDoctor'      => $perDoctor,
            'topDiagnoses'   => $topDiagnoses,
            'availableYears' => $availableYears,
            'months'         => self::monthOptions(),
        ]);
    }

    /**
     * Export a daily or monthly report as a CSV file.
     *
     * Query parameters:
     *   type  = 'daily' | 'monthly'
     *   date  = 'YYYY-MM-DD'  (for daily)
     *   year  = int            (for monthly)
     *   month = int            (for monthly)
     */
    public function export(Request $request): HttpResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $type = $request->input('type', 'daily');

        if ($type === 'daily') {
            return $this->exportDaily($request);
        }

        return $this->exportMonthly($request);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function exportDaily(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $date = $request->input('date', now()->toDateString());

        $registrations = Registration::with([
                'patient',
                'doctor.user',
                'medicalRecord',
                'transaction',
            ])
            ->whereDate('created_at', $date)
            ->orderBy('queue_number')
            ->get();

        $filename = "laporan-harian-{$date}.csv";

        return response()->streamDownload(function () use ($registrations) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($handle, [
                'No. Antrian',
                'Nama Pasien',
                'Jenis Kelamin',
                'Dokter',
                'Spesialisasi',
                'Keluhan',
                'Diagnosis',
                'Status',
                'Total Tagihan',
                'Status Pembayaran',
                'Metode Pembayaran',
                'Waktu Daftar',
            ]);

            foreach ($registrations as $reg) {
                fputcsv($handle, [
                    $reg->queue_number,
                    $reg->patient->name ?? '-',
                    $reg->patient->gender === 'male' ? 'Laki-laki' : 'Perempuan',
                    $reg->doctor->user->name ?? '-',
                    $reg->doctor->specialization === 'general' ? 'Umum' : 'Gigi',
                    $reg->complaint ?? '-',
                    $reg->medicalRecord->diagnosis ?? '-',
                    self::statusLabels()[$reg->status] ?? $reg->status,
                    $reg->transaction ? number_format($reg->transaction->total_amount, 0, ',', '.') : '0',
                    $reg->transaction?->status === 'paid' ? 'Lunas' : 'Belum Dibayar',
                    self::paymentMethodLabel($reg->transaction?->payment_method),
                    $reg->created_at->format('H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportMonthly(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $year  = (int) $request->input('year',  now()->year);
        $month = (int) $request->input('month', now()->month);

        $startOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $registrations = Registration::with([
                'patient',
                'doctor.user',
                'medicalRecord',
                'transaction',
            ])
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->orderBy('created_at')
            ->get();

        $monthLabel = $startOfMonth->format('Y-m');
        $filename   = "laporan-bulanan-{$monthLabel}.csv";

        return response()->streamDownload(function () use ($registrations) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Tanggal',
                'No. Antrian',
                'Nama Pasien',
                'Jenis Kelamin',
                'Dokter',
                'Spesialisasi',
                'Keluhan',
                'Diagnosis',
                'Status',
                'Total Tagihan',
                'Status Pembayaran',
                'Metode Pembayaran',
            ]);

            foreach ($registrations as $reg) {
                fputcsv($handle, [
                    $reg->created_at->format('d/m/Y'),
                    $reg->queue_number,
                    $reg->patient->name ?? '-',
                    $reg->patient->gender === 'male' ? 'Laki-laki' : 'Perempuan',
                    $reg->doctor->user->name ?? '-',
                    $reg->doctor->specialization === 'general' ? 'Umum' : 'Gigi',
                    $reg->complaint ?? '-',
                    $reg->medicalRecord->diagnosis ?? '-',
                    self::statusLabels()[$reg->status] ?? $reg->status,
                    $reg->transaction ? number_format($reg->transaction->total_amount, 0, ',', '.') : '0',
                    $reg->transaction?->status === 'paid' ? 'Lunas' : 'Belum Dibayar',
                    self::paymentMethodLabel($reg->transaction?->payment_method),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function statusLabels(): array
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

    private static function paymentMethodLabel(?string $method): string
    {
        $labels = [
            'cash'     => 'Tunai',
            'debit'    => 'Kartu Debit',
            'credit'   => 'Kartu Kredit',
            'transfer' => 'Transfer Bank',
            'bpjs'     => 'BPJS',
            'qris'     => 'QRIS',
        ];

        return $labels[$method] ?? '-';
    }

    private static function monthOptions(): array
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }
}
