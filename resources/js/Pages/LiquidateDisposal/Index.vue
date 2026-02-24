<template>
    <Head title="Wastage – Liquidate & Disposal" />
    <AuthenticatedLayout
        title="Wastage"
        description="Manage liquidate and disposal"
        img="/assets/images/orders.png"
    >
        <!-- Category Tabs: Liquidate, Disposal -->
        <div class="bg-white mb-6 rounded-lg shadow-sm border border-gray-200">
            <div class="border-b border-gray-200">
                <div class="flex items-center justify-between px-6">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            v-if="showLiquidateTab"
                            @click="activeTab = 'liquidate'"
                            class="whitespace-nowrap py-4 px-1 font-bold text-sm flex items-center space-x-2"
                            :class="[
                                activeTab === 'liquidate'
                                    ? 'border-b-4 border-blue-500 text-blue-600'
                                    : 'border-b-4 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Liquidate</span>
                            <span
                                v-if="props.stats?.liquidate"
                                class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800"
                            >
                                {{ props.stats.liquidate }}
                            </span>
                        </button>
                        <button
                            v-if="showDisposalTab"
                            @click="activeTab = 'disposal'"
                            class="whitespace-nowrap py-4 px-1 font-bold text-sm flex items-center space-x-2"
                            :class="[
                                activeTab === 'disposal'
                                    ? 'border-b-4 border-red-500 text-red-600'
                                    : 'border-b-4 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Disposal</span>
                            <span
                                v-if="props.stats?.disposal"
                                class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800"
                            >
                                {{ props.stats.disposal }}
                            </span>
                        </button>
                    </nav>
                    
                    <!-- Icon Legend Button -->
                    <button
                        @click="showIconLegend = true"
                        class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200"
                        title="Icon Legend"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white mb-6 rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center mb-5">
                <!-- Search -->
                <div class="relative w-full">
                    <input
                        type="text"
                        v-model="search"
                        placeholder="Search by ID or source"
                        class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <svg
                        class="absolute left-3 top-2.5 h-5 w-5 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                </div>

                <!-- Source Filter -->
                <div class="w-full">
                    <Multiselect
                        v-model="source"
                        :options="sourceOptions"
                        :searchable="true"
                        :close-on-select="true"
                        :allow-empty="true"
                        placeholder="Select Source"
                        track-by="value"
                        label="label"
                    >
                    </Multiselect>
                </div>

                <!-- Date From -->
                <div class="w-full flex items-center space-x-2">
                    <input
                        type="date"
                        v-model="dateFrom"
                        class="w-[150px] px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <span class="text-sm">To</span>
                    <input
                        type="date"
                        v-model="dateTo"
                        class="w-[150px] px-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>
            <div class="flex justify-end items-center">
                <select
                    v-model="per_page"
                    @change="props.filters.page = 1"
                    class="md:w-[200px] sm:w-[150px] xs:w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                >
                    <option value="10">10 Per page</option>
                    <option value="25">25 Per page</option>
                    <option value="50">50 Per page</option>
                    <option value="100">100 Per page</option>
                </select>
            </div>
            <!-- Status Tabs -->
            <div class="border-t border-gray-200 mt-4 pt-4">
                <nav class="-mb-px flex space-x-8">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        @click="currentStatus = tab.value"
                        class="whitespace-nowrap py-2 px-1 font-medium text-sm flex items-center space-x-2"
                        :class="[
                            currentStatus === tab.value
                                ? 'border-b-2 border-green-500 text-green-600'
                                : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        ]"
                    >
                        <img 
                            v-if="tab.value !== 'all'"
                            :src="tab.image" 
                            :alt="tab.label"
                            class="w-5 h-5"
                        />
                        <span>{{ tab.label }}</span>
                        <span
                            v-if="
                                props.stats &&
                                props.stats[tab.value || 'all']
                            "
                            class="px-2 py-0.5 text-xs rounded-full"
                            :class="`bg-${tab.color}-100 text-${tab.color}-800`"
                        >
                            {{ props.stats[tab.value || "all"] }}
                        </span>
                    </button>
                </nav>
            </div>
        </div>

        <!-- Icon Legend Slideover -->
        <div
            v-if="showIconLegend"
            class="fixed inset-0 overflow-hidden z-50"
            aria-labelledby="slide-over-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 overflow-hidden">
                <!-- Background overlay -->
                <div
                    class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    @click="showIconLegend = false"
                ></div>

                <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex sm:pl-16">
                    <div class="w-screen max-w-md">
                        <div class="h-full flex flex-col bg-white shadow-xl">
                            <!-- Header -->
                            <div class="px-4 py-6 bg-blue-50 sm:px-6">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-medium text-blue-900" id="slide-over-title">
                                        Status Icons Legend
                                    </h2>
                                    <button
                                        @click="showIconLegend = false"
                                        class="rounded-md text-blue-400 hover:text-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <span class="sr-only">Close panel</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="relative flex-1 px-4 sm:px-6 overflow-y-auto">
                                <div class="space-y-6 py-6">
                                    <div class="text-sm text-gray-600 mb-4">
                                        <p>These icons represent the current status of each liquidation/disposal in the workflow:</p>
                                    </div>
                                    
                                    <!-- Icon Legend Items -->
                                    <div class="space-y-4">
                                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center">
                                                <span class="text-white text-xs font-bold">P</span>
                                            </div>
                                            <div>
                                                <h3 class="font-medium text-gray-900">Pending</h3>
                                                <p class="text-sm text-gray-600">Liquidation/Disposal has been submitted and is awaiting review</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center">
                                                <span class="text-white text-xs font-bold">R</span>
                                            </div>
                                            <div>
                                                <h3 class="font-medium text-gray-900">Reviewed</h3>
                                                <p class="text-sm text-gray-600">Liquidation/Disposal has been reviewed by management</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                                                <span class="text-white text-xs font-bold">A</span>
                                            </div>
                                            <div>
                                                <h3 class="font-medium text-gray-900">Approved</h3>
                                                <p class="text-sm text-gray-600">Liquidation/Disposal has been approved for processing</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <div class="w-6 h-6 bg-red-500 rounded-full flex items-center justify-center">
                                                <span class="text-white text-xs font-bold">X</span>
                                            </div>
                                            <div>
                                                <h3 class="font-medium text-gray-900">Rejected</h3>
                                                <p class="text-sm text-gray-600">Liquidation/Disposal has been rejected and will not proceed</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Workflow Information -->
                                    <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                                        <h3 class="font-medium text-blue-900 mb-2">Workflow</h3>
                                        <p class="text-sm text-blue-800">
                                            Liquidations and disposals progress through these stages sequentially. Each icon represents a completed stage in the process.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="mb-[80px]">
            <!-- Tab Content -->
            <div v-if="showLiquidateTab && activeTab === 'liquidate'">
                <LiquidateTab 
                    :liquidates="props.liquidates" 
                    :filters="props.filters"
                    @pagination-change="getResult"
                />
            </div>
            <div v-else-if="showDisposalTab && activeTab === 'disposal'">
                <DisposalTab 
                    :disposals="props.disposals" 
                    :filters="props.filters"
                    @pagination-change="getResult"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import LiquidateTab from './LiquidateTab.vue';
import DisposalTab from './DisposalTab.vue';

const page = usePage();
const props = defineProps({
    liquidates: Object,
    disposals: Object,
    filters: Object,
    stats: Object,
});

const can = computed(() => page.props.auth?.can ?? {});

const showLiquidateTab = computed(() => !!(can.value.wastage_view || can.value.liquidation_view));
const showDisposalTab = computed(() => !!(can.value.wastage_view || can.value.disposal_view));

const activeTab = ref('liquidate');

watch([showLiquidateTab, showDisposalTab], () => {
    if (showDisposalTab.value && !showLiquidateTab.value) {
        activeTab.value = 'disposal';
    } else if (showLiquidateTab.value) {
        activeTab.value = 'liquidate';
    }
}, { immediate: true });
const showIconLegend = ref(false);
const currentStatus = ref('all');

const search = ref(props.filters?.search || '');
const per_page = ref(props.filters?.per_page || 25);
const source = ref(props.filters?.source || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const sourceOptions = [
    { value: '', label: 'All Sources' },
    { value: 'Packing List', label: 'Packing List' },
    { value: 'Missing', label: 'Missing' },
    { value: 'expiry', label: 'Expiry' },
    { value: 'audit_adjustment', label: 'Audit Adjustment' },
];

const statusTabs = [
    { value: 'all', label: 'All', color: 'gray' },
    { value: 'pending', label: 'Pending', color: 'yellow', image: '/assets/images/pending.png' },
    { value: 'reviewed', label: 'Reviewed', color: 'blue', image: '/assets/images/review.png' },
    { value: 'approved', label: 'Approved', color: 'green', image: '/assets/images/approved.png' },
    { value: 'rejected', label: 'Rejected', color: 'red', image: '/assets/images/rejected.png' },
];

watch([
    () => search.value,
    () => per_page.value,
    () => source.value,
    () => dateFrom.value,
    () => dateTo.value,
    () => currentStatus.value,
    () => props.filters.page
], () => {
    getResult();
});

function getResult(page = 1) {
    const query = {};
    if (search.value) query.search = search.value;
    if (per_page.value) query.per_page = per_page.value;
    if (source) query.source = typeof source === 'object' && source !== null && 'value' in source ? source.value : source;
    if (dateFrom.value) query.date_from = dateFrom.value;
    if (dateTo.value) query.date_to = dateTo.value;
    if (currentStatus.value !== 'all') query.status = currentStatus.value;
    if (page > 1) query.page = page;
    
    router.get(route('liquidate-disposal.index'), query, {
        preserveState: true,
        preserveScroll: true,
        only: ['liquidates', 'disposals', 'filters', 'stats']
    });
}
</script> 