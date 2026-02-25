<template>
    <Head title="Unified Inventory Report" />
    <AuthenticatedLayout
        title="Unified Inventory Report"
        description="Unified Inventory Report"
        img="/assets/images/report.png"
    >
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Unified Inventory Report
            </h2>
        </template>

        <div class="py-5">
            <!-- Filters: five in one row, then Generate Report button with period input to its right -->
            <div class="bg-emerald-50/90 border border-emerald-200 rounded-lg shadow-sm p-6 mb-6">
                <!-- Row 1: five filters with labels above -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Region</label>
                        <select
                            v-model="filters.region_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                        >
                            <option :value="null">Region</option>
                            <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select District</label>
                        <select
                            v-model="filters.district_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                            :disabled="!filters.region_id"
                        >
                            <option :value="null">District</option>
                            <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Warehouse/Facility Name</label>
                        <select
                            v-model="filters.warehouse_or_facility"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                        >
                            <option value="">Warehouse/Facility</option>
                            <optgroup label="Warehouses">
                                <option v-for="w in filteredWarehouses" :key="'w-' + w.id" :value="'warehouse:' + w.id">
                                    {{ w.name }}
                                </option>
                            </optgroup>
                            <optgroup label="Facilities">
                                <option v-for="f in filteredFacilities" :key="'f-' + f.id" :value="'facility:' + f.id">
                                    {{ f.name }}
                                </option>
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Report Type</label>
                        <select
                            v-model="filters.report_type"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                        >
                            <option value="">Report Type</option>
                            <option v-for="rt in reportTypes" :key="rt.value" :value="rt.value">{{ rt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Report Period</label>
                        <div class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm overflow-hidden">
                            <input
                                v-model="filters.monthYear"
                                type="month"
                                class="block w-full min-h-[38px] py-2 px-3 focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm border-0 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>
                <!-- Row 2: Generate Report button below first three filters (aligned with Report Period to its right in row 1) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-5 mt-4 items-end">
                    <div class="lg:col-span-3 flex items-end">
                        <button
                            type="button"
                            @click="generateReport"
                            :disabled="generating"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-emerald-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                        >
                            <span v-if="generating">Generating...</span>
                            <span v-else>Generate Report</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="mb-4">
                <label class="sr-only">Search</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="block w-full rounded-md border-gray-300 pl-10 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm"
                        placeholder="Search Facility/Item Name"
                    />
                </div>
            </div>

            <!-- Report Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div v-if="generating" class="p-8 text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600 mx-auto"></div>
                    <p class="mt-4 text-gray-600">Loading report...</p>
                </div>
                <div v-else-if="reportData.length === 0 && hasGenerated" class="p-8 text-center text-gray-500">
                    No data found for the selected filters. Try a different report type or period.
                </div>
                <div v-else-if="reportData.length > 0" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Item</th>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Category</th>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">UoM</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Item Details</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Beginning<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">QTY<br>Received</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">QTY<br>Issued</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Adjust<br>ments</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Closing<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total<br>Closing<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">AMC</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">MOS<br>(Months<br>of Stock)</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Stockout<br>Days</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Unit<br>cost</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total<br>Cost</th>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-600 border border-gray-300">Batch No.:</th>
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-600 border border-gray-300">Expiry Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr
                                v-for="(row, index) in filteredRows"
                                :key="index"
                                :class="row.is_first_batch ? 'border-t border-gray-400' : ''"
                                class="hover:bg-gray-50"
                            >
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 border border-gray-300 align-top">{{ row.item }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-600 border border-gray-300 align-top">{{ row.category }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-600 border border-gray-300 align-top">{{ row.uom }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600 border border-gray-300 whitespace-nowrap">{{ row.batch_no || '–' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600 border border-gray-300 whitespace-nowrap">{{ formatExpiry(row.expiry_date) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.beginning_balance) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.qty_received) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.qty_issued) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.adjustment_neg) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.adjustment_pos) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.closing_balance) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatNum(row.total_closing_balance) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatNum(row.amc) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ row.mos ?? '–' }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top">{{ formatNum(row.stockout_days) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top">{{ formatCost(row.unit_cost) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatCost(row.total_cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-8 text-center text-gray-500">
                    Select at least one location filter (Region, District, or Warehouse/Facility), then click Generate Report.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    regions: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    reportTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const now = new Date();
const defaultMonthYear = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

const filters = ref({
    region_id: props.filters?.region_id ?? null,
    district_id: props.filters?.district_id ?? null,
    warehouse_or_facility: props.filters?.warehouse_or_facility ?? '',
    report_type: props.filters?.report_type ?? 'warehouse_inventory',
    monthYear: props.filters?.monthYear ?? defaultMonthYear,
});
const reportData = ref([]);
const generating = ref(false);
const hasGenerated = ref(false);
const searchQuery = ref('');

const filteredDistricts = computed(() => {
    const list = props.districts || [];
    if (!filters.value.region_id) return [];
    const regionName = props.regions?.find(r => r.id == filters.value.region_id)?.name;
    if (!regionName) return [];
    return list.filter(d => (d.region || '') === regionName);
});

const filteredWarehouses = computed(() => {
    let list = props.warehouses || [];
    if (filters.value.region_id) {
        const regionName = props.regions?.find(r => r.id == filters.value.region_id)?.name;
        if (regionName) list = list.filter(w => w.region === regionName);
    }
    if (filters.value.district_id) {
        const districtName = props.districts?.find(d => d.id == filters.value.district_id)?.name;
        if (districtName) list = list.filter(w => w.district === districtName);
    }
    return list;
});

const filteredFacilities = computed(() => {
    let list = props.facilities || [];
    if (filters.value.region_id) {
        const regionName = props.regions?.find(r => r.id == filters.value.region_id)?.name;
        if (regionName) list = list.filter(f => f.region === regionName);
    }
    if (filters.value.district_id) {
        const districtName = props.districts?.find(d => d.id == filters.value.district_id)?.name;
        if (districtName) list = list.filter(f => f.district === districtName);
    }
    return list;
});

const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return reportData.value;
    const q = searchQuery.value.toLowerCase();
    return reportData.value.filter(row => {
        const item = (row.item || '').toLowerCase();
        const facility = (row.facility_name || '').toLowerCase();
        const warehouse = (row.warehouse_name || '').toLowerCase();
        return item.includes(q) || facility.includes(q) || warehouse.includes(q);
    });
});

watch(() => filters.value.region_id, () => {
    filters.value.district_id = null;
});

function formatNum(n) {
    if (n == null || n === '') return '–';
    return Number(n).toLocaleString();
}
function formatCost(n) {
    if (n == null || n === '' || Number(n) === 0) return '–';
    return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatExpiry(d) {
    if (!d) return '–';
    if (d.length === 10) return d; // Y-m-d
    return d;
}

async function generateReport() {
    generating.value = true;
    hasGenerated.value = true;
    try {
        const monthYear = filters.value.monthYear || defaultMonthYear;
        const [year, month] = monthYear.split('-').map(Number);
        const params = {
            report_type: filters.value.report_type || 'warehouse_inventory',
            region_id: filters.value.region_id || undefined,
            district_id: filters.value.district_id || undefined,
            year: year || undefined,
            month: month || undefined,
        };
        if (filters.value.warehouse_or_facility) {
            const [type, id] = filters.value.warehouse_or_facility.split(':');
            if (type === 'warehouse') params.warehouse_id = id;
            if (type === 'facility') params.facility_id = id;
        }
        const { data } = await axios.get(route('reports.inventoryReportsUnified.data'), { params });
        if (data.success) {
            reportData.value = data.data || [];
        } else {
            reportData.value = [];
        }
    } catch (e) {
        reportData.value = [];
        console.error(e);
    } finally {
        generating.value = false;
    }
}
</script>
