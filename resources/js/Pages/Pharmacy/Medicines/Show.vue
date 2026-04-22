<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    medicine: Object,
    usageHistory: Array,
});

const isModalOpen = ref(false);

const form = useForm({
    adjustment: 0,
    reason: 'restock',
    notes: '',
    batch_number: '',
    expiration_date: '',
});

const openModal = () => {
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const submitAdjustment = () => {
    form.post(route('medicines.adjust-stock', props.medicine.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8 font-sans">
        <div class="max-w-7xl mx-auto space-y-6">
            
            <!-- Breadcrumbs / Top Actions -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                <Link :href="route('medicines.index')" class="text-sm text-slate-500 hover:text-blue-600 transition-colors inline-flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" /></svg>
                    Kembali ke Daftar Obat
                </Link>
                <div class="flex space-x-3">
                    <button @click="openModal" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl shadow-sm hover:bg-blue-700 hover:shadow transition-all duration-200">
                        Tambah / Koreksi Stok
                    </button>
                </div>
            </div>

            <!-- Page Title & Stats Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Info Utama -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <div class="flex items-center space-x-5 mb-6">
                        <div class="flex-shrink-0 h-16 w-16 flex items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-indigo-100 text-blue-600 font-extrabold text-2xl shadow-inner">
                            {{ medicine.name.charAt(0) }}
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">{{ medicine.name }}</h1>
                            <p class="text-sm font-mono text-slate-500 mt-1">SKU: {{ medicine.sku }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 py-4 border-t border-slate-100">
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Stok Total</p>
                            <p class="mt-1 text-lg font-bold" :class="medicine.is_low ? 'text-red-600' : 'text-slate-900'">
                                {{ medicine.stock }} <span class="text-xs text-slate-500 font-normal">{{ medicine.unit }}</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Harga / Unit</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">
                                Rp {{ new Intl.NumberFormat('id-ID').format(medicine.price) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Telah Diresepkan</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">
                                {{ medicine.total_dispensed }} <span class="text-xs text-slate-500 font-normal">kali</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Status</p>
                            <span class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="medicine.is_low ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'">
                                {{ medicine.is_low ? 'Menipis' : 'Aman' }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="text-sm text-slate-600">{{ medicine.description || 'Tidak ada deskripsi tambahan untuk obat ini.' }}</p>
                    </div>
                </div>

                <!-- Informasi Batch Kedaluwarsa -->
                <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl shadow-lg border border-slate-700 p-6 text-white text-sm">
                    <h3 class="font-bold text-lg mb-4 text-slate-100 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        Batch Aktif Obat
                    </h3>
                    <ul class="space-y-3" v-if="medicine.batches && medicine.batches.length > 0">
                        <li v-for="batch in medicine.batches" :key="batch.id" class="flex justify-between items-center bg-slate-800/50 p-3 rounded-xl border border-slate-700">
                            <div>
                                <span class="font-mono text-xs text-indigo-300 block">Batch: {{ batch.batch_number }}</span>
                                <span class="text-xs text-slate-400">Exp: {{ batch.expiration_date }}</span>
                            </div>
                            <span class="font-bold text-lg text-emerald-400">{{ batch.stock }}</span>
                        </li>
                    </ul>
                    <div v-else class="text-center py-6 text-slate-500">
                        Belum ada data batch tercatat.
                    </div>
                </div>
            </div>

            <!-- Modal Tambah/Koreksi Stok -->
            <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                        <div class="absolute inset-0 bg-slate-900 opacity-75 backdrop-blur-sm"></div>
                    </div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-bold text-slate-900 mb-4" id="modal-title">
                                Tambah / Koreksi Stok Obat
                            </h3>
                            <form @submit.prevent="submitAdjustment" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Jenis Perubahan</label>
                                    <select v-model="form.reason" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-xl">
                                        <option value="restock">Restock (Tambah Stok Baru)</option>
                                        <option value="correction">Koreksi Data (Selisih)</option>
                                        <option value="damaged">Rusak</option>
                                        <option value="expired">Kedaluwarsa (Buang)</option>
                                    </select>
                                    <p class="text-red-500 text-xs mt-1" v-if="form.errors.reason">{{ form.errors.reason }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Nominal (+ / -)</label>
                                        <input type="number" v-model="form.adjustment" class="mt-1 block w-full border border-slate-300 rounded-xl shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Contoh: 100 atau -5">
                                        <p class="text-red-500 text-xs mt-1" v-if="form.errors.adjustment">{{ form.errors.adjustment }}</p>
                                    </div>
                                    <div v-if="form.reason === 'restock'">
                                        <label class="block text-sm font-medium text-slate-700">Batch Number</label>
                                        <input type="text" v-model="form.batch_number" class="mt-1 block w-full border border-slate-300 rounded-xl shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Contoh: BATCH-001">
                                        <p class="text-red-500 text-xs mt-1" v-if="form.errors.batch_number">{{ form.errors.batch_number }}</p>
                                    </div>
                                </div>

                                <div v-if="form.reason === 'restock'">
                                    <label class="block text-sm font-medium text-slate-700">Tanggal Kedaluwarsa</label>
                                    <input type="date" v-model="form.expiration_date" class="mt-1 block w-full border border-slate-300 rounded-xl shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <p class="text-red-500 text-xs mt-1" v-if="form.errors.expiration_date">{{ form.errors.expiration_date }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Catatan (Pilihan)</label>
                                    <textarea v-model="form.notes" rows="2" class="mt-1 block w-full border border-slate-300 rounded-xl shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Catatan tambahan..."></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-slate-100">
                            <button type="button" @click="submitAdjustment" :disabled="form.processing" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                                Simpan Perubahan
                            </button>
                            <button type="button" @click="closeModal" class="mt-3 w-full inline-flex justify-center rounded-xl border border-slate-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
