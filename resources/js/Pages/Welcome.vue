<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted } from "vue";

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();

const mobileMenuOpen = ref(false);
const scrolled = ref(false);

const handleScroll = () => {
    scrolled.value = window.scrollY > 10;
};

onMounted(() => {
    window.addEventListener("scroll", handleScroll);
});

onUnmounted(() => {
    window.removeEventListener("scroll", handleScroll);
});

const services = [
    {
        title: "Layanan Dokter Umum",
        description:
            "Konsultasi medis umum dan penanganan penyakit ringan hingga menengah oleh dokter berpengalaman.",
        icon: "M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.324-1.414-3.414l5-5A2 2 0 0010 8.586V4h8z",
    },
    {
        title: "Layanan BPJS Kesehatan",
        description:
            "Melayani pasien peserta BPJS Kesehatan dengan fasilitas dan pelayanan terbaik sesuai standar.",
        icon: "M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z",
    },
    {
        title: "Layanan Tindakan",
        description:
            "Penanganan medis yang memerlukan tindakan cepat, tepat, dan aman oleh tenaga profesional.",
        icon: "M13 10V3L4 14h7v7l9-11h-7z",
    },
    {
        title: "Layanan Khitan",
        description:
            "Melayani khitan modern dengan metode aman, cepat sembuh, dan minim rasa sakit.",
        icon: "M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5",
    },
    {
        title: "Layanan Home Care",
        description:
            "Perawatan medis profesional langsung di rumah Anda, memastikan kenyamanan dan pemulihan maksimal.",
        icon: "M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6",
    },
];

const schedules = [
    {
        shift: "PAGI",
        days: "Senin - Sabtu",
        time: "06.00 - 11.00 WIB",
    },
    {
        shift: "SORE",
        days: "Senin - Sabtu",
        time: "16.00 - 21.00 WIB",
    },
];
</script>

<template>
    <Head title="Beranda" />

    <div
        class="min-h-screen font-sans text-gray-800 bg-gray-50 selection:bg-primary-500 selection:text-white"
    >
        <!-- Top bar (Info) -->
        <div
            class="bg-secondary-900 text-white py-2.5 text-xs sm:text-sm border-b border-secondary-800"
        >
            <div
                class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-2"
            >
                <div class="flex items-center gap-2">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-4 h-4 text-primary-400"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                    <span>Senin - Sabtu, 06.00 - 21.00 WIB</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-4 h-4 text-primary-400"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.896-1.596-5.436-4.036-7.034-6.931l1.292-.97c.361-.271.528-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"
                        />
                    </svg>
                    <span>Hubungi Kami: 081223282911</span>
                </div>
            </div>
        </div>

        <!-- Navbar -->
        <nav
            :class="[
                'sticky top-0 z-50 w-full bg-white transition-colors duration-300 border-b',
                scrolled ? 'border-gray-200' : 'border-gray-100',
            ]"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <div class="flex items-center gap-2.5">
                        <div
                            class="w-8 h-8 bg-primary-600 text-white flex items-center justify-center rounded font-bold text-lg"
                        >
                            K
                        </div>
                        <span
                            class="font-bold text-xl tracking-tight text-secondary-900"
                            >Klinik Kamulyan</span
                        >
                    </div>

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex space-x-8">
                        <a
                            href="#home"
                            class="text-sm font-medium text-gray-600 hover:text-primary-600 transition-colors"
                            >Beranda</a
                        >
                        <a
                            href="#about"
                            class="text-sm font-medium text-gray-600 hover:text-primary-600 transition-colors"
                            >Tentang Kami</a
                        >
                        <a
                            href="#services"
                            class="text-sm font-medium text-gray-600 hover:text-primary-600 transition-colors"
                            >Layanan</a
                        >
                        <a
                            href="#schedule"
                            class="text-sm font-medium text-gray-600 hover:text-primary-600 transition-colors"
                            >Jadwal Klinik</a
                        >
                        <a
                            href="#contact"
                            class="text-sm font-medium text-gray-600 hover:text-primary-600 transition-colors"
                            >Kontak</a
                        >
                    </div>

                    <!-- Desktop Actions -->
                    <div class="hidden md:flex items-center space-x-4">
                        <template v-if="canLogin">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="text-sm font-medium text-gray-600 hover:text-primary-600 transition-colors"
                            >
                                Dashboard
                            </Link>
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="text-sm font-medium text-gray-600 hover:text-primary-600 transition-colors"
                                >
                                    Login Petugas
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="bg-primary-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-primary-700 transition-colors"
                                >
                                    Daftar Online
                                </Link>
                            </template>
                        </template>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="text-gray-600 hover:text-primary-600 focus:outline-none p-2"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    v-if="!mobileMenuOpen"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    v-else
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </nav>
    </div>
</template>
