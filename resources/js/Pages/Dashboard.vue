<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, usePage } from "@inertiajs/vue3";

const user = usePage().props.auth.user;
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Dashboard Overview
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl p-6 border-l-4 border-primary-500 transition hover:shadow-lg"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div
                                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Total Pasien
                                </div>
                                <div
                                    class="text-3xl font-bold text-gray-900 dark:text-white mt-1"
                                >
                                    1,204
                                </div>
                            </div>
                            <div
                                class="p-3 bg-primary-100 rounded-lg text-primary-600"
                            >
                                👥
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl p-6 border-l-4 border-blue-500 transition hover:shadow-lg"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div
                                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Antrian Hari Ini
                                </div>
                                <div
                                    class="text-3xl font-bold text-gray-900 dark:text-white mt-1"
                                >
                                    42
                                </div>
                            </div>
                            <div
                                class="p-3 bg-blue-100 rounded-lg text-blue-600"
                            >
                                ⏳
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl p-6 border-l-4 border-green-500 transition hover:shadow-lg"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div
                                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Dokter Aktif
                                </div>
                                <div
                                    class="text-3xl font-bold text-gray-900 dark:text-white mt-1"
                                >
                                    4
                                </div>
                            </div>
                            <div
                                class="p-3 bg-green-100 rounded-lg text-green-600"
                            >
                                👨‍⚕️
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl p-6 border-l-4 border-purple-500 transition hover:shadow-lg"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <div
                                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Pendapatan Bulan Ini
                                </div>
                                <div
                                    class="text-3xl font-bold text-gray-900 dark:text-white mt-1"
                                >
                                    Rp 45M
                                </div>
                            </div>
                            <div
                                class="p-3 bg-purple-100 rounded-lg text-purple-600"
                            >
                                💰
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700"
                >
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex items-center gap-4 mb-6">
                            <div
                                class="h-12 w-12 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 font-bold text-xl"
                            >
                                {{ user.name.charAt(0) }}
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">
                                    Selamat Datang, {{ user.name }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Anda login sebagai
                                    <span
                                        class="uppercase font-bold text-primary-600"
                                        >{{ user.role }}</span
                                    >
                                </p>
                            </div>
                        </div>

                        <div class="prose dark:prose-invert max-w-none">
                            <p>
                                Sistem Manajemen Klinik Kamulyan siap digunakan.
                                Silakan akses menu di sebelah kiri untuk
                                mengelola data klinik.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
