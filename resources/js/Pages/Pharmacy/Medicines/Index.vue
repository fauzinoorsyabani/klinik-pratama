<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
    medicines: Object,
    filters: Object,
    units: Array,
    lowStockCount: Number,
});

const search = ref(props.filters.search || '');

watch(search, (value) => {
    router.get(
        route('medicines.index'),
        { search: value },
        { preserveState: true, replace: true }
    );
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8 font-sans">
        <!-- Header Section -->
        <div class="max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Manajemen Apotek <span class="text-blue-600">Pro</span></h1>
                    <p class="mt-2 text-sm text-slate-600">Kelola inventaris obat, batch, dan stok dengan mudah.</p>
                </div>
                <!-- Warning Alert for Low Stock -->
                <div v-if="lowStockCount > 0" class="flex items-center space-x-2 bg-red-50 text-red-700 px-4 py-2 rounded-xl border border-red-100 shadow-sm animate-pulse-slow">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="font-medium text-sm">{{ lowStockCount }} Obat dengan stok menipis!</span>
                </div>
            </div>

            <!-- Toolbar (Search & Actions) -->
            <div class="flex flex-col sm:flex-row gap-4 bg-white p-4 rounded-2xl shadow-sm border border-slate-100">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input v-model="search" type="text" placeholder="Cari nama atau SKU obat..." class="block w-full pl-10 pr-3 py-3 border-none bg-slate-50 focus:bg-white rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all duration-300 text-sm">
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama Obat</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Stok Total</th>
                                <th scope="col" class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Harga</th>
                                <th scope="col" class="px-6 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="medicine in medicines.data" :key="medicine.id" class="hover:bg-slate-50 transition-colors duration-200 group">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-gradient-to-br from-blue-100 to-indigo-100 text-blue-600 font-bold shadow-inner">
                                            {{ medicine.name.charAt(0) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">{{ medicine.name }}</div>
                                            <div class="text-xs text-slate-500">{{ medicine.unit }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-800 font-mono">
                                        {{ medicine.sku }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-2 w-2 rounded-full mr-2" :class="medicine.is_low ? 'bg-red-500 animate-pulse' : 'bg-emerald-500'"></div>
                                        <span class="text-sm font-bold" :class="medicine.is_low ? 'text-red-700' : 'text-slate-900'">
                                            {{ medicine.stock }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-slate-900">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(medicine.price) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <Link :href="route('medicines.show', medicine.id)" class="inline-flex items-center px-3 py-1.5 border border-slate-200 shadow-sm text-xs font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-50 hover:text-blue-600 hover:border-blue-300 focus:outline-none transition-all duration-200">
                                        Detail & Batch
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="medicines.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-slate-900">Belum Ada Obat</h3>
                                    <p class="mt-1 text-sm text-slate-500">Silakan tambahkan data obat baru ke sistem.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
<style>
.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
