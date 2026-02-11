<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import MovingGridBackground from '@/Components/MovingGridBackground.vue';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <Head title="Klinik Pratama Kamulyan" />
    <div
        class="min-h-screen bg-gray-50 text-gray-800 font-sans selection:bg-primary-500 selection:text-white dark:bg-gray-900 dark:text-gray-100"
    >
        <!-- Navigation -->
        <nav
            class="fixed w-full z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md border-b border-gray-100 dark:border-gray-800 transition-all duration-300"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20 items-center">
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <div
                            class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-bold text-lg shadow-lg"
                        >
                            K
                        </div>
                        <span
                            class="text-2xl font-bold bg-gradient-to-r from-primary-600 to-teal-500 bg-clip-text text-transparent"
                        >
                            Klinik Kamulyan
                        </span>
                    </div>

                    <div class="hidden md:flex space-x-8">
                        <a
                            href="#services"
                            class="text-gray-600 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 transition font-medium"
                            >Layanan</a
                        >
                        <a
                            href="#doctors"
                            class="text-gray-600 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 transition font-medium"
                            >Dokter</a
                        >
                        <a
                            href="#contact"
                            class="text-gray-600 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 transition font-medium"
                            >Kontak</a
                        >
                    </div>

                    <div class="flex items-center space-x-4">
                        <template v-if="canLogin">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="text-gray-900 hover:text-primary-600 font-medium dark:text-white"
                                >Dashboard</Link
                            >
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="text-gray-900 hover:text-primary-600 font-medium px-4 py-2 dark:text-white"
                                    >Log in</Link
                                >
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="bg-primary-600 text-white px-5 py-2.5 rounded-full hover:bg-primary-700 transition shadow-lg shadow-primary-500/30 font-medium text-sm"
                                    >Daftar Online</Link
                                >
                            </template>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
            <div class="absolute inset-0 z-0 overflow-hidden">
                <div
                    class="absolute inset-0 bg-gradient-to-br from-primary-50/50 to-white dark:from-gray-950 dark:to-gray-900"
                ></div>
                <!-- Moving Grid Background -->
                <MovingGridBackground />
                
                <div
                    class="absolute right-0 top-0 -mt-20 -mr-20 w-96 h-96 bg-primary-200/20 rounded-full blur-3xl animate-pulse"
                ></div>
                <div
                    class="absolute left-0 bottom-0 -mb-20 -ml-20 w-80 h-80 bg-teal-200/20 rounded-full blur-3xl"
                ></div>
            </div>

            <div
                class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center"
            >
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 text-primary-700 text-sm font-medium mb-6 border border-primary-100 dark:bg-primary-900/30 dark:text-primary-300 dark:border-primary-800"
                >
                    <span
                        class="flex h-2 w-2 rounded-full bg-primary-500"
                    ></span>
                    Melayani dengan Sepenuh Hati
                </div>
                <h1
                    class="text-5xl md:text-7xl font-bold tracking-tight mb-6 leading-tight text-gray-900 dark:text-white"
                >
                    Kesehatan Anda,<br />
                    <span
                        class="text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-teal-500"
                        >Prioritas Utama Kami</span
                    >
                </h1>
                <p
                    class="text-xl text-gray-600 dark:text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed"
                >
                    Layanan kesehatan modern dengan pendekatan personal. Dokter
                    berpengalaman dan fasilitas lengkap untuk kenyamanan Anda
                    dan keluarga.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="px-8 py-4 bg-primary-600 text-white rounded-full text-lg font-semibold hover:bg-primary-700 transition shadow-xl shadow-primary-500/20 hover:shadow-primary-500/30 transform hover:-translate-y-1"
                    >
                        Daftar Berobat
                    </Link>
                    <a
                        href="#services"
                        class="px-8 py-4 bg-white text-gray-700 border border-gray-200 rounded-full text-lg font-semibold hover:bg-gray-50 transition dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700 hover:shadow-lg"
                    >
                        Lihat Layanan
                    </a>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section id="services" class="py-24 bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2
                        class="text-base text-primary-600 font-semibold tracking-wide uppercase"
                    >
                        Layanan Kami
                    </h2>
                    <p
                        class="mt-2 text-3xl leading-8 font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl"
                    >
                        Solusi Kesehatan Terpadu
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Service 1 -->
                    <div
                        class="p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-transparent hover:border-primary-100 dark:hover:border-primary-900 group relative overflow-hidden"
                    >
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-primary-50/50 to-transparent opacity-0 group-hover:opacity-100 transition duration-300 pointer-events-none"
                        ></div>
                        <div
                            class="w-14 h-14 bg-white dark:bg-gray-700 text-primary-600 rounded-2xl flex items-center justify-center mb-6 text-2xl group-hover:bg-primary-600 group-hover:text-white transition shadow-sm group-hover:shadow-md z-10 relative"
                        >
                            🩺
                        </div>
                        <h3
                            class="text-xl font-bold text-gray-900 dark:text-white mb-3 relative z-10"
                        >
                            Poli Umum
                        </h3>
                        <p
                            class="text-gray-600 dark:text-gray-400 relative z-10"
                        >
                            Pemeriksaan kesehatan menyeluruh oleh dokter umum
                            berpengalaman untuk segala keluhan penyakit ringan
                            hingga konsultasi kesehatan.
                        </p>
                    </div>

                    <!-- Service 2 -->
                    <div
                        class="p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-transparent hover:border-blue-100 dark:hover:border-blue-900 group relative overflow-hidden"
                    >
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-blue-50/50 to-transparent opacity-0 group-hover:opacity-100 transition duration-300 pointer-events-none"
                        ></div>
                        <div
                            class="w-14 h-14 bg-white dark:bg-gray-700 text-blue-600 rounded-2xl flex items-center justify-center mb-6 text-2xl group-hover:bg-blue-600 group-hover:text-white transition shadow-sm group-hover:shadow-md z-10 relative"
                        >
                            🦷
                        </div>
                        <h3
                            class="text-xl font-bold text-gray-900 dark:text-white mb-3 relative z-10"
                        >
                            Poli Gigi
                        </h3>
                        <p
                            class="text-gray-600 dark:text-gray-400 relative z-10"
                        >
                            Perawatan kesehatan gigi dan mulut profesional.
                            Mulai dari pembersihan karang gigi (scaling),
                            tambal, cabut, hingga perawatan estetik.
                        </p>
                    </div>

                    <!-- Service 3 -->
                    <div
                        class="p-8 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:shadow-xl transition duration-300 border border-transparent hover:border-green-100 dark:hover:border-green-900 group relative overflow-hidden"
                    >
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-green-50/50 to-transparent opacity-0 group-hover:opacity-100 transition duration-300 pointer-events-none"
                        ></div>
                        <div
                            class="w-14 h-14 bg-white dark:bg-gray-700 text-green-600 rounded-2xl flex items-center justify-center mb-6 text-2xl group-hover:bg-green-600 group-hover:text-white transition shadow-sm group-hover:shadow-md z-10 relative"
                        >
                            💊
                        </div>
                        <h3
                            class="text-xl font-bold text-gray-900 dark:text-white mb-3 relative z-10"
                        >
                            Farmasi
                        </h3>
                        <p
                            class="text-gray-600 dark:text-gray-400 relative z-10"
                        >
                            Layanan obat lengkap, terjamin keasliannya dan harga
                            terjangkau. Dikelola oleh apoteker profesional yang
                            siap memberikan konsultasi obat.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section (Optional) -->
        <section class="py-20 bg-primary-600 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <div>
                        <div class="text-4xl font-bold mb-2">10+</div>
                        <div class="text-primary-200">Dokter Spesialis</div>
                    </div>
                    <div>
                        <div class="text-4xl font-bold mb-2">5000+</div>
                        <div class="text-primary-200">Pasien Terlayani</div>
                    </div>
                    <div>
                        <div class="text-4xl font-bold mb-2">24/7</div>
                        <div class="text-primary-200">
                            Layanan Gawar Darurat
                        </div>
                    </div>
                    <div>
                        <div class="text-4xl font-bold mb-2">5 Tahun</div>
                        <div class="text-primary-200">Pengalaman</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="bg-gray-50 dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800 py-12"
        >
            <div
                class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8 mb-8"
            >
                <div class="col-span-1 md:col-span-2">
                    <span
                        class="text-2xl font-bold bg-gradient-to-r from-primary-600 to-teal-500 bg-clip-text text-transparent mb-4 block"
                    >
                        Klinik Kamulyan
                    </span>
                    <p class="text-gray-500 dark:text-gray-400 max-w-sm">
                        Menyediakan layanan kesehatan berkualitas tinggi dengan
                        harga yang terjangkau bagi masyarakat.
                    </p>
                </div>
                <div>
                    <h4 class="font-bold text-gray-900 dark:text-white mb-4">
                        Layanan
                    </h4>
                    <ul class="space-y-2 text-gray-500 dark:text-gray-400">
                        <li>
                            <a href="#" class="hover:text-primary-600"
                                >Poli Umum</a
                            >
                        </li>
                        <li>
                            <a href="#" class="hover:text-primary-600"
                                >Poli Gigi</a
                            >
                        </li>
                        <li>
                            <a href="#" class="hover:text-primary-600"
                                >Checkup Medis</a
                            >
                        </li>
                        <li>
                            <a href="#" class="hover:text-primary-600"
                                >Farmasi</a
                            >
                        </li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-gray-900 dark:text-white mb-4">
                        Kontak
                    </h4>
                    <ul class="space-y-2 text-gray-500 dark:text-gray-400">
                        <li>Jl. Sehat Sejahtera No. 123</li>
                        <li>(021) 555-0123</li>
                        <li>info@klinikkamulyan.com</li>
                    </ul>
                </div>
            </div>
            <div
                class="max-w-7xl mx-auto px-4 text-center border-t border-gray-200 dark:border-gray-800 pt-8"
            >
                <p class="text-gray-500 dark:text-gray-400">
                    © 2024 Klinik Pratama Kamulyan. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Optional: specific CSS if Tailwind isn't enough */
</style>
