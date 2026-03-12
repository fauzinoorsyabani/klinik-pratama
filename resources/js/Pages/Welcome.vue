<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted } from 'vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();

const mobileMenuOpen = ref(false);
const scrolled = ref(false);

const handleScroll = () => {
    scrolled.value = window.scrollY > 20;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const services = [
    {
        title: 'Layanan Dokter Umum',
        description: 'Konsultasi medis umum dan penanganan penyakit ringan hingga menengah oleh dokter berpengalaman.',
        icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.324-1.414-3.414l5-5A2 2 0 0010 8.586V4h8z',
    },
    {
        title: 'Layanan BPJS Kesehatan',
        description: 'Melayani pasien peserta BPJS Kesehatan dengan fasilitas dan pelayanan terbaik sesuai standar.',
        icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    },
    {
        title: 'Layanan Tindakan',
        description: 'Penanganan medis yang memerlukan tindakan cepat, tepat, dan aman oleh tenaga profesional.',
        icon: 'M13 10V3L4 14h7v7l9-11h-7z',
    },
    {
        title: 'Layanan Khitan',
        description: 'Melayani khitan modern dengan metode aman, cepat sembuh, dan minim rasa sakit.',
        icon: 'M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5',
    },
    {
        title: 'Layanan Home Care',
        description: 'Perawatan medis profesional langsung di rumah Anda, memastikan kenyamanan dan pemulihan maksimal.',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
];

const doctors = [
    {
        name: 'dr. Budi Santoso',
        role: 'Dokter Umum',
        image: 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&q=80&w=400',
        schedule: 'Senin - Rabu (06.00 - 11.00)',
    },
    {
        name: 'dr. Ahmad Fauzi',
        role: 'Dokter Umum',
        image: 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&q=80&w=400',
        schedule: 'Kamis - Sabtu (16.00 - 21.00)',
    },
    {
        name: 'drg. Siti Aminah',
        role: 'Dokter Gigi',
        image: 'https://images.unsplash.com/photo-1594824436998-058a231161a0?auto=format&fit=crop&q=80&w=400',
        schedule: 'Senin, Rabu, Jumat',
    },
    {
        name: 'drg. Rizky Permata',
        role: 'Dokter Gigi',
        image: 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=400',
        schedule: 'Selasa, Kamis, Sabtu',
    }
];
</script>

<template>
    <Head title="Beranda" />

    <div class="min-h-screen bg-slate-50 font-sans selection:bg-primary-500 selection:text-white">
        <!-- Navigation -->
        <nav
            :class="[
                'fixed w-full z-50 transition-all duration-300 border-b',
                scrolled ? 'bg-white/95 backdrop-blur-md border-slate-200 py-3 shadow-sm' : 'bg-white border-transparent py-4'
            ]"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <!-- Logo -->
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded bg-primary-600 flex items-center justify-center text-white font-bold text-xl">K</div>
                        <span class="text-xl font-bold text-secondary-900 tracking-tight">Klinik <span class="text-primary-600">Kamulyan</span></span>
                    </div>

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <a href="#home" class="text-sm font-medium text-secondary-600 hover:text-primary-600 transition">Beranda</a>
                        <a href="#about" class="text-sm font-medium text-secondary-600 hover:text-primary-600 transition">Tentang Kami</a>
                        <a href="#services" class="text-sm font-medium text-secondary-600 hover:text-primary-600 transition">Layanan</a>
                        <a href="#schedule" class="text-sm font-medium text-secondary-600 hover:text-primary-600 transition">Jadwal Klinik</a>
                        <a href="#contact" class="text-sm font-medium text-secondary-600 hover:text-primary-600 transition">Kontak</a>
                    </div>

                    <!-- Actions -->
                    <div class="hidden md:flex items-center space-x-4">
                        <template v-if="canLogin">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="text-sm font-medium text-secondary-600 hover:text-primary-600 transition"
                            >
                                Dashboard
                            </Link>
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="text-sm font-medium text-secondary-600 hover:text-primary-600 transition"
                                >
                                    Login Petugas
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="px-5 py-2.5 rounded bg-primary-600 text-white text-sm font-bold hover:bg-primary-700 transition focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                >
                                    Daftar Online
                                </Link>
                            </template>
                        </template>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden flex items-center">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-secondary-600 hover:text-primary-600 p-2">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="
