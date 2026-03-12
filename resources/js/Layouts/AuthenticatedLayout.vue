<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth.user as { name: string; email: string; role: string });

const sidebarOpen = ref(false);
const openGroups = ref<Record<string, boolean>>({});

function toggleGroup(key: string) {
    openGroups.value[key] = !openGroups.value[key];
}

const isAdmin        = computed(() => user.value.role === 'admin');
const isDoctor       = computed(() => user.value.role === 'doctor');
const isNurse        = computed(() => user.value.role === 'nurse');
const isRegistration = computed(() => user.value.role === 'registration');
const isPharmacist   = computed(() => user.value.role === 'pharmacist');

const roleLabels: Record<string, string> = {
    admin:        'Administrator',
    doctor:       'Dokter',
    nurse:        'Petugas Ruang Tunggu',
    registration: 'Petugas Pendaftaran',
    pharmacist:   'Petugas Farmasi',
};

const roleBadgeColors: Record<string, string> = {
    admin:        'bg-purple-500/20 text-purple-300',
    doctor:       'bg-blue-500/20 text-blue-300',
    nurse:        'bg-pink-500/20 text-pink-300',
    registration: 'bg-orange-500/20 text-orange-300',
    pharmacist:   'bg-green-500/20 text-green-300',
};

const currentRoleLabel = computed(() => roleLabels[user.value.role] ?? user.value.role);
const currentRoleBadge = computed(() => roleBadgeColors[user.value.role] ?? 'bg-gray-500/20 text-gray-300');

const flash = computed(() => (page.props.flash ?? {}) as { success?: string; error?: string });
const showFlash = ref(true);

// Watch for new flash messages and re-show the banner
import { watch } from 'vue';
watch(flash, () => { showFlash.value = true; });

function isActive(pattern: string): boolean {
    try { return route().current(pattern) ?? false; } catch { return false; }
}

interface NavChild  { label: string; href: string; active: string; }
interface NavGroup  {
    key: string;
    label: string;
    single: boolean;
    href?: string;
    active?: string;
    children?: NavChild[];
}

const navGroups = computed((): NavGroup[] => {
    const g: NavGroup[] = [];

    g.push({ key: 'dashboard', label: 'Dashboard', single: true,
        href: route('dashboard'), active: 'dashboard' });

    if (isAdmin.value || isRegistration.value) {
        g.push({ key: 'patients', label: 'Pasien', single: false, children: [
            { label: 'Daftar Pasien',  href: route('patients.index'),  active: 'patients.index'  },
            { label: 'Tambah Pasien',  href: route('patients.create'), active: 'patients.create' },
        ]});
        g.push({ key: 'registrations', label: 'Pendaftaran', single: false, children: [
            { label: 'Antrian Hari Ini',  href: route('queue.today'),          active: 'queue.today'           },
            { label: 'Semua Pendaftaran', href: route('registrations.index'),  active: 'registrations.index'   },
            { label: 'Daftarkan Pasien',  href: route('registrations.create'), active: 'registrations.create'  },
        ]});
    }

    if (isAdmin.value || isNurse.value) {
        g.push({ key: 'vital', label: 'Tanda Vital', single: true,
            href: route('vital-signs.index'), active: 'vital-signs.*' });
    }

    if (isAdmin.value || isDoctor.value) {
        g.push({ key: 'medical', label: 'Rekam Medis', single: true,
            href: route('medical-records.index'), active: 'medical-records.*' });
    }

    if (isAdmin.value || isPharmacist.value) {
        g.push({ key: 'pharmacy', label: 'Farmasi', single: false, children: [
            { label: 'Antrian Resep', href: route('pharmacy.index'),   active: 'pharmacy.*'      },
            { label: 'Stok Obat',     href: route('medicines.index'),  active: 'medicines.index' },
            { label: 'Tambah Obat',   href: route('medicines.create'), active: 'medicines.create'},
        ]});
    }

    if (isAdmin.value || isRegistration.value) {
        g.push({ key: 'transactions', label: 'Keuangan', single: true,
            href: route('transactions.index'), active: 'transactions.*' });
    }

    if (isAdmin.value) {
        g.push({ key: 'admin', label: 'Manajemen', single: false, children: [
            { label: 'Dokter',          href: route('admin.doctors.index'),  active: 'admin.doctors.*'  },
            { label: 'Pengguna',        href: route('admin.users.index'),    active: 'admin.users.*'    },
            { label: 'Layanan & Tarif', href: route('admin.services.index'), active: 'admin.services.*' },
        ]});
        g.push({ key: 'reports', label: 'Laporan', single: false, children: [
            { label: 'Ikhtisar',        href: route('admin.reports.index'),   active: 'admin.reports.index'   },
            { label: 'Laporan Harian',  href: route('admin.reports.daily'),   active: 'admin.reports.daily'   },
            { label: 'Laporan Bulanan', href: route('admin.reports.monthly'), active: 'admin.reports.monthly' },
        ]});
    }

    return g;
});

function isGroupActive(g: NavGroup): boolean {
    if (g.single && g.active) return isActive(g.active);
    return g.children?.some(c => isActive(c.active)) ?? false;
}

function isOpen(g: NavGroup): boolean {
    return !!openGroups.value[g.key] || isGroupActive(g);
}

// Icon map — minimal inline SVG paths per group key
const groupIcons: Record<string, string> = {
    dashboard:    'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
    patients:     'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    registrations:'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 0 2-2h2a2 2 0 0 0 2 2m-6 7 2 2 4-4',
    vital:        'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z',
    medical:      'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
    pharmacy:     'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 1-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
    transactions: 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
    admin:        'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    reports:      'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
};
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-900 font-sans">

        <!-- Mobile sidebar overlay -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-20 bg-black/60 lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- ═══════════════════════════════════ SIDEBAR ═══════════════════════════════════ -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-secondary-900 shadow-xl transition-transform duration-300 ease-in-out',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <!-- Logo / Clinic Name -->
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-4">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-500 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M11.484 2.17a.75.75 0 0 1 1.032 0 11.209 11.209 0 0 0 7.877 3.08.75.75 0 0 1 .722.515 12.74 12.74 0 0 1 .635 3.985c0 5.942-4.064 10.933-9.563 12.348a.749.749 0 0 1-.374 0C6.314 20.683 2.25 15.692 2.25 9.75c0-1.39.223-2.73.635-3.985a.75.75 0 0 1 .722-.516l.143.001c2.996 0 5.718-1.17 7.734-3.08ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75ZM12 15a.75.75 0 0 0-.75.75v.008c0 .414.336.742.75.742h.008a.75.75 0 0 0 .75-.75v-.008a.75.75 0 0 0-.75-.75H12Z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-white leading-tight">Klinik Pratama</p>
                    <p class="truncate text-xs text-white/50 leading-tight">Kamulyan</p>
                </div>
                <!-- Close btn (mobile) -->
                <button
                    class="ml-auto shrink-0 rounded-md p-1.5 text-white/50 transition hover:bg-white/10 hover:text-white lg:hidden"
                    @click="sidebarOpen = false"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Nav links -->
            <nav class="flex-1 overflow-y-auto px-2.5 py-3 space-y-0.5 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-white/10">
                <template v-for="group in navGroups" :key="group.key">

                    <!-- ── Single link ── -->
                    <template v-if="group.single">
                        <Link
                            :href="group.href!"
                            :class="[
                                'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                isGroupActive(group)
                                    ? 'bg-primary-600 text-white shadow'
                                    : 'text-white/65 hover:bg-white/8 hover:text-white',
                            ]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="groupIcons[group.key] ?? ''" />
                            </svg>
                            <span>{{ group.label }}</span>
                        </Link>
                    </template>

                    <!-- ── Expandable group ── -->
                    <template v-else>
                        <button
                            type="button"
                            :class="[
                                'group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
                                isGroupActive(group)
                                    ? 'bg-white/10 text-white'
                                    : 'text-white/65 hover:bg-white/8 hover:text-white',
                            ]"
                            @click="toggleGroup(group.key)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="groupIcons[group.key] ?? ''" />
                            </svg>
                            <span class="flex-1 text-left">{{ group.label }}</span>
                            <svg
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="2.5" stroke="currentColor"
                                :class="['h-3.5 w-3.5 shrink-0 transition-transform duration-200', isOpen(group) ? 'rotate-180' : '']"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>

                        <!-- Children -->
                        <div v-show="isOpen(group)" class="mt-0.5 ml-3 space-y-0.5 border-l border-white/10 pl-3">
                            <Link
                                v-for="child in group.children"
                                :key="child.href"
                                :href="child.href"
                                :class="[
                                    'flex items-center gap-2 rounded-md px-2.5 py-2 text-sm transition-all duration-150',
                                    isActive(child.active)
                                        ? 'bg-primary-600/80 text-white font-medium'
                                        : 'text-white/55 hover:bg-white/8 hover:text-white',
                                ]"
                            >
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="isActive(child.active) ? 'bg-white' : 'bg-white/30'"></span>
                                {{ child.label }}
                            </Link>
                        </div>
                    </template>
                </template>
            </nav>

            <!-- User info / Logout -->
            <div class="shrink-0 border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-500 text-sm font-bold text-white shadow">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white leading-tight">{{ user.name }}</p>
                        <span :class="['inline-block rounded px-1.5 py-0.5 text-xs font-medium leading-tight', currentRoleBadge]">
                            {{ currentRoleLabel }}
                        </span>
                    </div>
                </div>
                <div class="mt-2 space-y-0.5">
                    <Link
                        :href="route('profile.edit')"
                        class="flex items-center gap-2 rounded-md px-3 py-2 text-xs text-white/60 transition hover:bg-white/8 hover:text-white"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        Profil Saya
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-xs text-white/60 transition hover:bg-red-500/20 hover:text-red-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                        </svg>
                        Keluar
                    </Link>
                </div>
            </div>
        </aside>

        <!-- ═══════════════════════════════════ MAIN AREA ═══════════════════════════════════ -->
        <div class="flex flex-1 flex-col overflow-hidden lg:pl-64">

            <!-- Top bar -->
            <header class="flex h-16 shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                <!-- Hamburger (mobile) -->
                <button
                    class="rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 lg:hidden"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>

                <!-- Page heading slot -->
                <div class="flex-1 min-w-0">
                    <slot name="header" />
                </div>

                <!-- Right side -->
                <div class="flex shrink-0 items-center gap-3">
                    <!-- Date display -->
                    <span class="hidden text-xs text-gray-400 sm:block">
                        {{ new Date().toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}
                    </span>

                    <!-- User avatar -->
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-500 text-sm font-bold text-white shadow">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="showFlash && (flash.success || flash.error)"
                    :class="[
                        'flex items-center justify-between gap-4 px-4 py-3 text-sm font-medium sm:px-6',
                        flash.success ? 'bg-green-50 text-green-800 border-b border-green-200' : 'bg-red-50 text-red-800 border-b border-red-200',
                    ]"
                >
                    <div class="flex items-center gap-2">
                        <svg v-if="flash.success" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-green-500">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-red-500">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ flash.success ?? flash.error }}</span>
                    </div>
                    <button
                        type="button"
                        class="rounded p-0.5 opacity-60 transition hover:opacity-100"
                        @click="showFlash = false"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </Transition>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
