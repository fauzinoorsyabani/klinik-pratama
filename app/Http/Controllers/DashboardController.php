<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Registration;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        // ── Core Stats ────────────────────────────────────────────────
        $totalPatients = Patient::count();
        $todayRegistrations = Registration::whereDate('created_at', $today)->count();
        $activeDoctors = Doctor::where('is_active', true)->count();

        $revenueThisMonth = Transaction::where('status', 'paid')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');

        $revenueLastMonth = Transaction::where('status', 'paid')
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('total_amount');

        $revenueGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : 0;

        // ── Today's Queue Summary ─────────────────────────────────────
        $todayQueueByStatus = Registration::whereDate('created_at', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $queueStatuses = ['pending', 'vital_check', 'consultation', 'pharmacy', 'completed', 'cancelled'];
        $todayQueue = [];
        foreach ($queueStatuses as $status) {
            $todayQueue[$status] = $todayQueueByStatus[$status] ?? 0;
        }

        // ── Recent Registrations (last 8) ─────────────────────────────
        $recentRegistrations = Registration::with(['patient', 'doctor.user'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn($reg) => [
                'id'           => $reg->id,
                'queue_number' => $reg->queue_number,
                'patient_name' => $reg->patient->name ?? '-',
                'doctor_name'  => $reg->doctor->user->name ?? '-',
                'status'       => $reg->status,
                'complaint'    => $reg->complaint,
                'created_at'   => $reg->created_at->format('d M Y H:i'),
            ]);

        // ── Weekly Patient Trend (last 7 days) ────────────────────────
        $weeklyTrend = Registration::select(
                DB::raw("strftime('%Y-%m-%d', created_at) as date"),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $trendLabels = [];
        $trendData   = [];
        for ($i = 6; $i >= 0; $i--) {
            $day           = now()->subDays($i)->toDateString();
            $trendLabels[] = now()->subDays($i)->format('d M');
            $trendData[]   = $weeklyTrend[$day]->total ?? 0;
        }

        // ── Low-stock Medicines (stock < 10) ──────────────────────────
        $lowStockMedicines = Medicine::where('stock', '<', 10)
            ->orderBy('stock')
            ->limit(5)
            ->get(['id', 'name', 'stock', 'unit']);

        // ── Unpaid Transactions count ─────────────────────────────────
        $unpaidCount = Transaction::where('status', 'unpaid')->count();

        // ── Monthly Revenue Trend (last 6 months) ─────────────────────
        $monthlyRevenue = Transaction::where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw("strftime('%Y-%m', created_at) as month"),
                DB::raw('sum(total_amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $revenueLabels = [];
        $revenueData   = [];
        for ($i = 5; $i >= 0; $i--) {
            $month           = now()->subMonths($i)->format('Y-m');
            $label           = now()->subMonths($i)->format('M Y');
            $revenueLabels[] = $label;
            $revenueData[]   = (float) ($monthlyRevenue[$month]->total ?? 0);
        }

        // ── Role-specific data ────────────────────────────────────────
        $user     = $request->user();
        $roleData = [];

        if ($user->role === 'doctor' && $user->doctor) {
            // Doctor sees their own pending patients today
            $roleData['myPatientsToday'] = Registration::with('patient')
                ->where('doctor_id', $user->doctor->id)
                ->whereDate('created_at', $today)
                ->whereIn('status', ['pending', 'vital_check', 'consultation'])
                ->count();

            $roleData['myCompletedToday'] = Registration::where('doctor_id', $user->doctor->id)
                ->whereDate('created_at', $today)
                ->where('status', 'completed')
                ->count();
        }

        if ($user->role === 'pharmacist') {
            $roleData['pendingPrescriptions'] = \App\Models\Prescription::whereIn('status', ['pending', 'preparing'])->count();
            $roleData['readyPrescriptions']   = \App\Models\Prescription::where('status', 'ready')->count();
        }

        if ($user->role === 'registration') {
            $roleData['todayTotal']     = $todayRegistrations;
            $roleData['todayCompleted'] = $todayQueue['completed'];
            $roleData['todayPending']   = $todayQueue['pending'];
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalPatients'      => $totalPatients,
                'todayRegistrations' => $todayRegistrations,
                'activeDoctors'      => $activeDoctors,
                'revenueThisMonth'   => $revenueThisMonth,
                'revenueGrowth'      => $revenueGrowth,
                'unpaidCount'        => $unpaidCount,
            ],
            'todayQueue'         => $todayQueue,
            'recentRegistrations'=> $recentRegistrations,
            'weeklyTrend'        => [
                'labels' => $trendLabels,
                'data'   => $trendData,
            ],
            'monthlyRevenue' => [
                'labels' => $revenueLabels,
                'data'   => $revenueData,
            ],
            'lowStockMedicines' => $lowStockMedicines,
            'roleData'          => $roleData,
        ]);
    }
}
