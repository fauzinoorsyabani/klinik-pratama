<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

interface Stats {
    totalPatients: number;
    todayRegistrations: number;
    activeDoctors: number;
    revenueThisMonth: number;
    revenueGrowth: number;
    unpaidCount: number;
}

interface TodayQueue {
    pending: number;
    vital_check: number;
    consultation: number;
    pharmacy: number;
    completed: number;
    cancelled: number;
}

interface RecentRegistration {
    id: number;
    queue_number: string;
    patient_name: string;
    doctor_name: string;
    status: string;
    complaint: string | null;
    created_at: string;
}

interface WeeklyTrend {
    labels: string[];
    data: number[];
}

interface MonthlyRevenue {
    labels: string[];
    data: number[];
}

interface LowStockMedicine {
    id: number;
    name: string;
    stock: number;
    unit: string;
}

interface RoleData {
    myPatientsToday?: number;
    myCompletedToday?: number;
    pendingPrescriptions?: number;
    readyPrescriptions?: number;
    todayTotal?: number;
    todayCompleted?: number;
    todayPending?: number;
}

const props = defineProps<{
    stats: Stats;
    todayQueue: TodayQueue;
    recentRegistrations: RecentRegistration[];
    weeklyTrend: WeeklyTrend;
    monthlyRevenue: MonthlyRevenue;
    lowStockMedicines: LowStockMedicine[];
    roleData: RoleData;
}>();

const user = computed(() => page.props.auth.user as { name: string; role: string; email: string });

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatRupiah(amount: number): string {
    if (amount >= 1_000_000_000) return `Rp ${(amount / 1_000_000_000).toFixed(1)}M`;
    if (amount >= 1_000_000)     return `Rp ${(amount / 1_000_000).toFixed(1)}Jt`;
    if (amount >= 1_000)         return `Rp ${(amount / 1_000).toFixed(0)}Rb`;
    return `Rp ${amount.toLocaleString('id-ID')}`;
}

function fullRupiah(amount: number): string {
    return `Rp ${amount.toLocaleString('id-ID', { minimumFractionDigits: 0 })}`;
}

const statusConfig: Record<string, { label: string; color: string; bg: string; dot: string }> = {
    pending:      { label: 'Menunggu',        color: 'text-yellow-700', bg: 'bg-yellow-100', dot: 'bg-yellow-400' },
    vital_check:  { label: 'Tanda Vital',     color: 'text-blue-700',   bg: 'bg-blue-100',   dot: 'bg-blue-400'   },
    consultation: { label: 'Konsultasi',      color: 'text-purple-700', bg: 'bg-purple-100', dot: 'bg-purple-400' },
    pharmacy:     { label: 'Farmasi',         color: 'text-orange-700', bg: 'bg-orange-100', dot: 'bg-orange-400' },
    completed:    { label: 'Selesai',         color: 'text-green-700',  bg: 'bg-green-100',  dot: 'bg-green-500'  },
    cancelled:    { label: 'Dibatalkan',      color: 'text-red-700',    bg: 'bg-red-100',    dot: 'bg-red-400'    },
};

const totalToday = computed(() =>
    Object.values(props.todayQueue).reduce((a, b) => a + b, 0)
);

// Weekly trend bar heights (normalised to max)
const weeklyMax = computed(() => Math.max(...props.weeklyTrend.data, 1));
const weeklyBars = computed(() =>
    props.weeklyTrend.data.map((v, i) => ({
        label: props.weeklyTrend.labels[i],
        value: v,
        height: Math.max(4, Math.round((v / weeklyMax.value) * 100)),
        isToday: i === props.weeklyTrend.data.length - 1,
    }))
);

// Monthly revenue bars
const revenueMax = computed(() => Math.max(...props.monthlyRevenue.data, 1));
const revenueBars = computed(() =>
    props.monthlyRevenue.data.map((v, i) => ({
        label: props.monthlyRevenue.labels[i],
        value: v,
        height: Math.max(4, Math.round((v / revenueMax.value) * 100)),
        isCurrentMonth: i === props.monthlyRevenue.data.length - 1,
    }))
);

// Greeting
const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Selamat Pagi';
    if (h < 15) return 'Selamat Siang';
    if (h < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-base font-semibold text-gray-700 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div class="p-4 space-y-6 sm:p-6">

            <!-- ── Greeting banner ────────────────────────────────────────── -->
            <div class="rounded-xl border border-primary-200 bg-primary-600 p-5 text-white dark:border-primary-800 dark:bg-primary-900">
                <div>
                    <p class="text-sm font-medium text-primary-100">{{ greeting }},</p>
                    <h1 class="mt-0.5 text-xl font-bold">{{ user.name }} 👋</h1>
                    <p class="mt-1 text-sm text-primary-200 capitalize">
                        {{ new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
                    </p>
                </div>
            </div>

            <!-- ── Core Stats Grid ────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <!-- Total Pasien -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Pasien</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {{ stats.totalPatients.toLocaleString('id-ID') }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-primary-50 p-2 dark:bg-primary-900/20">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-primary-600 dark:text-primary-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                            </svg>
                        </div>
                    </div>
                    <Link :href="route('patients.index')" class="mt-2 flex items-center gap-1 text-xs text-primary-600 hover:underline dark:text-primary-400">
                        Lihat semua
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </Link>
                </div>

                <!-- Antrian Hari Ini -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Antrian Hari Ini</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ totalToday }}</p>
                        </div>
                        <div class="rounded-lg bg-blue-50 p-2 dark:bg-blue-900/20">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-blue-600 dark:text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="h-2 w-2 rounded-full bg-green-400"></span>
                        {{ stats.todayRegistrations }} pendaftaran baru
                    </div>
                </div>

                <!-- Dokter Aktif -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Dokter Aktif</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.activeDoctors }}</p>
                        </div>
                        <div class="rounded-lg bg-green-50 p-2 dark:bg-green-900/20">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-green-600 dark:text-green-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Siap melayani pasien</p>
                </div>

                <!-- Pendapatan Bulan Ini -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pendapatan Bulan Ini</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white truncate">
                                {{ formatRupiah(stats.revenueThisMonth) }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-purple-50 p-2 dark:bg-purple-900/20 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-purple-600 dark:text-purple-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-1 text-xs">
                        <span v-if="stats.revenueGrowth >= 0" class="flex items-center gap-0.5 text-green-600 font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                            +{{ stats.revenueGrowth }}%
                        </span>
                        <span v-else class="flex items-center gap-0.5 text-red-500 font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                            {{ stats.revenueGrowth }}%
                        </span>
                        <span class="text-gray-400">vs bulan lalu</span>
                    </div>
                </div>
            </div>

            <!-- ── Role-specific quick stats ──────────────────────────────── -->
            <div v-if="Object.keys(roleData).length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <!-- Doctor role data -->
                <template v-if="user.role === 'doctor'">
                    <div class="col-span-2 rounded-xl border border-blue-100 bg-blue-50 p-4 dark:bg-blue-900/10 dark:border-blue-800">
                        <p class="text-xs font-medium text-blue-600 dark:text-blue-400">Pasien Saya Hari Ini</p>
                        <p class="mt-1 text-3xl font-bold text-blue-800 dark:text-blue-200">{{ roleData.myPatientsToday ?? 0 }}</p>
                        <p class="mt-1 text-xs text-blue-600">{{ roleData.myCompletedToday ?? 0 }} sudah selesai diperiksa</p>
                    </div>
                </template>

                <!-- Pharmacist role data -->
                <template v-if="user.role === 'pharmacist'">
                    <div class="rounded-xl border border-orange-100 bg-orange-50 p-4 dark:bg-orange-900/10 dark:border-orange-800">
                        <p class="text-xs font-medium text-orange-600">Resep Pending</p>
                        <p class="mt-1 text-3xl font-bold text-orange-800">{{ roleData.pendingPrescriptions ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-green-100 bg-green-50 p-4 dark:bg-green-900/10 dark:border-green-800">
                        <p class="text-xs font-medium text-green-600">Siap Diambil</p>
                        <p class="mt-1 text-3xl font-bold text-green-800">{{ roleData.readyPrescriptions ?? 0 }}</p>
                    </div>
                </template>
            </div>

            <!-- ── Alerts ─────────────────────────────────────────────────── -->
            <div class="flex flex-col gap-3">
                <!-- Unpaid transactions alert -->
                <div v-if="stats.unpaidCount > 0" class="flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-yellow-500">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    <span><strong>{{ stats.unpaidCount }}</strong> transaksi belum dibayar.</span>
                    <Link :href="route('transactions.index', { status: 'unpaid' })" class="ml-auto shrink-0 font-medium underline hover:no-underline">
                        Lihat →
                    </Link>
                </div>

                <!-- Low stock alert -->
                <div v-if="lowStockMedicines.length > 0" class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-red-500">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    <span>
                        <strong>{{ lowStockMedicines.length }}</strong> obat stok hampir habis:
                        {{ lowStockMedicines.slice(0, 3).map(m => m.name).join(', ') }}{{ lowStockMedicines.length > 3 ? ` +${lowStockMedicines.length - 3} lainnya` : '' }}.
                    </span>
                    <Link :href="route('medicines.index', { low_stock: 1 })" class="ml-auto shrink-0 font-medium underline hover:no-underline">
                        Kelola →
                    </Link>
                </div>
            </div>

            <!-- ── Middle row: Queue + Trend ─────────────────────────────── -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                <!-- Today's Queue Summary -->
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 dark:text-white">Antrian Hari Ini</h3>
                        <Link :href="route('queue.today')" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                            Lihat papan antrian →
                        </Link>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div
                            v-for="(key) in ['pending', 'vital_check', 'consultation', 'pharmacy', 'completed', 'cancelled']"
                            :key="key"
                            :class="[
                                'flex flex-col items-center rounded-xl p-3 text-center',
                                statusConfig[key]?.bg ?? 'bg-gray-100',
                            ]"
                        >
                            <span class="text-2xl font-bold" :class="statusConfig[key]?.color ?? 'text-gray-700'">
                                {{ todayQueue[key as keyof TodayQueue] ?? 0 }}
                            </span>
                            <span class="mt-1 text-xs font-medium" :class="statusConfig[key]?.color ?? 'text-gray-600'">
                                {{ statusConfig[key]?.label ?? key }}
                            </span>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="totalToday > 0" class="mt-4">
                        <div class="mb-1.5 flex items-center justify-between text-xs text-gray-500">
                            <span>Progress hari ini</span>
                            <span class="font-medium text-gray-700">
                                {{ todayQueue.completed }} / {{ totalToday }} selesai
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div
                                class="h-full rounded-full bg-green-500 transition-all duration-700"
                                :style="{ width: `${Math.round((todayQueue.completed / totalToday) * 100)}%` }"
                            ></div>
                        </div>
                    </div>

                    <div v-else class="mt-4 rounded-lg bg-gray-50 py-4 text-center text-sm text-gray-400">
                        Belum ada pendaftaran hari ini
                    </div>
                </div>

                <!-- Weekly Patient Trend -->
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 dark:text-white">Tren Pasien (7 Hari)</h3>
                        <span class="text-xs text-gray-400">Total: {{ weeklyTrend.data.reduce((a, b) => a + b, 0) }}</span>
                    </div>

                    <div class="flex h-32 items-end gap-1.5">
                        <div
                            v-for="bar in weeklyBars"
                            :key="bar.label"
                            class="group relative flex flex-1 flex-col items-center gap-1"
                        >
                            <!-- Tooltip -->
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 hidden rounded bg-gray-800 px-2 py-0.5 text-xs text-white group-hover:block whitespace-nowrap z-10">
                                {{ bar.value }} pasien
                            </div>
                            <div
                                :class="[
                                    'w-full rounded-t-md transition-all duration-500',
                                    bar.isToday ? 'bg-primary-500' : 'bg-primary-200 hover:bg-primary-400',
                                ]"
                                :style="{ height: bar.height + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div class="mt-2 flex gap-1.5">
                        <div v-for="bar in weeklyBars" :key="bar.label + '-label'" class="flex-1 text-center">
                            <span class="text-xs text-gray-400">{{ bar.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Monthly Revenue Chart ──────────────────────────────────── -->
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 dark:text-white">Pendapatan 6 Bulan Terakhir</h3>
                    <Link :href="route('transactions.index')" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                        Lihat transaksi →
                    </Link>
                </div>

                <div class="flex h-36 items-end gap-2 sm:gap-3">
                    <div
                        v-for="bar in revenueBars"
                        :key="bar.label"
                        class="group relative flex flex-1 flex-col items-center gap-1"
                    >
                        <!-- Tooltip -->
                        <div class="absolute -top-8 left-1/2 -translate-x-1/2 hidden rounded bg-gray-800 px-2 py-1 text-xs text-white group-hover:block whitespace-nowrap z-10">
                            {{ fullRupiah(bar.value) }}
                        </div>
                        <div
                            :class="[
                                'w-full rounded-t-lg transition-all duration-700',
                                bar.isCurrentMonth ? 'bg-primary-500' : 'bg-primary-100 hover:bg-primary-300',
                            ]"
                            :style="{ height: bar.height + '%' }"
                        ></div>
                    </div>
                </div>

                <div class="mt-2 flex gap-2 sm:gap-3">
                    <div v-for="bar in revenueBars" :key="bar.label + '-label'" class="flex-1 text-center">
                        <span class="text-xs text-gray-400">{{ bar.label }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Recent Registrations Table ─────────────────────────────── -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-800 dark:text-white">Pendaftaran Terbaru</h3>
                    <Link :href="route('registrations.index')" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                        Lihat semua →
                    </Link>
                </div>

                <div v-if="recentRegistrations.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-700/50 dark:text-gray-400">
                                <th class="px-5 py-3 text-left">No. Antrian</th>
                                <th class="px-5 py-3 text-left">Pasien</th>
                                <th class="px-5 py-3 text-left hidden sm:table-cell">Dokter</th>
                                <th class="px-5 py-3 text-left hidden md:table-cell">Keluhan</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left hidden lg:table-cell">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            <tr
                                v-for="reg in recentRegistrations"
                                :key="reg.id"
                                class="transition hover:bg-gray-50/80 dark:hover:bg-gray-700/30"
                            >
                                <td class="px-5 py-3.5">
                                    <Link
                                        :href="route('registrations.show', reg.id)"
                                        class="font-mono font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ reg.queue_number }}
                                    </Link>
                                </td>
                                <td class="px-5 py-3.5 font-medium text-gray-800 dark:text-gray-200">
                                    {{ reg.patient_name }}
                                </td>
                                <td class="px-5 py-3.5 text-gray-500 hidden sm:table-cell dark:text-gray-400">
                                    {{ reg.doctor_name }}
                                </td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell max-w-xs truncate dark:text-gray-400">
                                    {{ reg.complaint ?? '-' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            statusConfig[reg.status]?.bg ?? 'bg-gray-100',
                                            statusConfig[reg.status]?.color ?? 'text-gray-700',
                                        ]"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="statusConfig[reg.status]?.dot ?? 'bg-gray-400'"
                                        ></span>
                                        {{ statusConfig[reg.status]?.label ?? reg.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-xs text-gray-400 hidden lg:table-cell">
                                    {{ reg.created_at }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="py-12 text-center text-sm text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 h-10 w-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 0 2-2h2a2 2 0 0 0 2 2"/>
                    </svg>
                    Belum ada data pendaftaran
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
