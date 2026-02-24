<template>
    <Head title="Product Expiry Tracking Report" />
    <AuthenticatedLayout title="Product Expiry Tracking Report" description="Track products expiring within different timeframes with detailed analysis"
        img="/assets/images/report.png">
    
                <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-4">
            <!-- Total Items -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Items</p>
                        <p class="text-2xl font-bold text-gray-900">{{ summary.total_items }}</p>
                    </div>
                </div>
            </div>

            <!-- Expired -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Expired</p>
                        <p class="text-2xl font-bold text-red-600">{{ summary.expired_count }}</p>
                    </div>
                </div>
            </div>

            <!-- Expiring in 6 Months -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">6 Months</p>
                        <p class="text-2xl font-bold text-orange-600">{{ summary.expiring_6_months_count }}</p>
                    </div>
                </div>
            </div>

            <!-- Expiring in 1 Year -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">1 Year</p>
                        <p class="text-2xl font-bold text-yellow-600">{{ summary.expiring_1_year_count }}</p>
                    </div>
                </div>
            </div>

            <!-- Total Quantity -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Quantity</p>
                        <p class="text-2xl font-bold text-gray-900">{{ summary.total_quantity }}</p>
                    </div>
                </div>
            </div>

            <!-- Total Value -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Value</p>
                        <p class="text-2xl font-bold text-gray-900">${{ formatCurrency(summary.total_value) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Filters</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500">Results: {{ expiryItems.total }}</span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <!-- Expiry Timeframe Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Timeframe</label>
                    <select v-model="expiry_timeframe" class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                        <option value="">All Timeframes</option>
                        <option value="expired">Expired</option>
                        <option value="6_months">Expiring within 6 months</option>
                        <option value="1_year">Expiring within 1 year</option>
                        <option value="all_expiring">All expiring within 1 year</option>
                    </select>
                </div>

                <!-- Product Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                    <Multiselect
                        v-model="product_ids"
                        :options="products"
                        :multiple="true"
                        :searchable="true"
                        :create-option="false"
                        :show-labels="false"
                        :close-on-select="true"
                        placeholder="Select products..."
                        track-by="id"
                        label="name"
                        class="w-full"
                    />
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <Multiselect
                        v-model="category_ids"
                        :options="categories"
                        :multiple="true"
                        :searchable="true"
                        :create-option="false"
                        :show-labels="false"
                        :close-on-select="true"
                        placeholder="Select categories..."
                        track-by="id"
                        label="name"
                        class="w-full"
                    />
                </div>

                <!-- Dosage Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dosage Form</label>
                    <Multiselect
                        v-model="dosage_ids"
                        :options="dosages"
                        :multiple="true"
                        :searchable="true"
                        :create-option="false"
                        :show-labels="false"
                        :close-on-select="true"
                        placeholder="Select dosage forms..."
                        track-by="id"
                        label="name"
                        class="w-full"
                    />
                </div>

                <!-- Warehouse Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
                    <Multiselect
                        v-model="warehouse_ids"
                        :options="warehouses"
                        :multiple="true"
                        :searchable="true"
                        :create-option="false"
                        :show-labels="false"
                        :close-on-select="true"
                        placeholder="Select warehouses..."
                        track-by="id"
                        label="name"
                        class="w-full"
                    />
                </div>

                <!-- Search Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by product name, ID, batch, or barcode..."
                        class="w-full border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
                    />
                </div>
            </div>
        </div>

        <!-- Expiry Items Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Expiry Tracking List</h3>
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600">Show:</label>
                        <select v-model="per_page" @change="props.filters.page = 1" class="border-gray-300 rounded-md text-sm shadow-sm">
                            <option value="25">25 Per page</option>
                            <option value="50">50 Per page</option>
                            <option value="100">100 Per page</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Product ID</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-[250px] border-b border-gray-200">Item</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Category</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Dosage Form</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Batch Number</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Quantity</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Unit Cost</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Total Value</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Expiry Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Days Until Expiry</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200">Warehouse</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-for="item in expiryItems.data" :key="item.id" class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                <span class="bg-gray-100 px-2 py-1 rounded-md text-xs">{{ item.product?.productID || 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 w-[250px] break-words">
                                <div class="font-medium">{{ item.product?.name || 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded-md text-xs">{{ item.product?.category?.name || 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="bg-purple-50 text-purple-700 px-2 py-1 rounded-md text-xs">{{ item.product?.dosage?.name || 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="bg-gray-50 text-gray-700 px-2 py-1 rounded-md text-xs">{{ item.batch_number || 'N/A' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="bg-gray-50 text-gray-700 px-2 py-1 rounded-md text-xs">{{ item.location || '—' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ item.quantity }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ formatCurrency(item.unit_cost || 0) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ formatCurrency(item.total_value || 0) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="bg-gray-50 text-gray-700 px-2 py-1 rounded-md text-xs">{{ formatDate(item.expiry_date) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span :class="getDaysUntilExpiryClass(item.days_until_expiry)">
                                    {{ formatDaysUntilExpiry(item.days_until_expiry) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="getExpiryStatusClass(item.expiry_status)">
                                    {{ getExpiryStatusLabel(item.expiry_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="bg-gray-50 text-gray-700 px-2 py-1 rounded-md text-xs">{{ item.warehouse?.name || 'N/A' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 flex justify-end mt-3">
                <TailwindPagination 
                    :data="expiryItems" 
                    :limit="2"
                    @pagination-change-page="getResults"
                />
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import { TailwindPagination } from "laravel-vue-pagination";

const props = defineProps({
    expiryItems: Object,
    products: Array,
    categories: Array,
    dosages: Array,
    warehouses: Array,
    filters: Object,
    summary: Object
});

const expiry_timeframe = ref(props.filters.expiry_timeframe || '');
const product_ids = ref(props.filters.product_ids || []);
const category_ids = ref(props.filters.category_ids || []);
const dosage_ids = ref(props.filters.dosage_ids || []);
const warehouse_ids = ref(props.filters.warehouse_ids || []);
const search = ref(props.filters.search || '');
const per_page = ref(props.filters.per_page || 25);

const applyFilters = () => {
    // Create a clean filters object by removing empty values
    const cleanFilters = {};
    
    if (expiry_timeframe.value) cleanFilters.expiry_timeframe = expiry_timeframe.value;
    if (product_ids.value && product_ids.value.length > 0) cleanFilters.product_ids = product_ids.value;
    if (category_ids.value && category_ids.value.length > 0) cleanFilters.category_ids = category_ids.value;
    if (dosage_ids.value && dosage_ids.value.length > 0) cleanFilters.dosage_ids = dosage_ids.value;
    if (warehouse_ids.value && warehouse_ids.value.length > 0) cleanFilters.warehouse_ids = warehouse_ids.value;
    if (search.value) cleanFilters.search = search.value;
    if (per_page.value && per_page.value !== 25) cleanFilters.per_page = per_page.value;
    if (props.filters.page) cleanFilters.page = props.filters.page;
    
    router.get(route('reports.products.expiry-tracking'), cleanFilters, {
        preserveState: true,
        preserveScroll: true,
        only: ['expiryItems', 'summary']
    });
};

const getResults = (page = 1) => {
    props.filters.page = page;
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
};

const formatCurrency = (amount) => {
    return parseFloat(amount || 0).toFixed(2);
};

const formatDaysUntilExpiry = (days) => {
    if (days === null || days === undefined) return 'N/A';
    
    const formattedDays = Math.round(days);
    if (formattedDays > 0) {
        return `+${formattedDays} days`;
    } else if (formattedDays < 0) {
        return `${formattedDays} days (expired)`;
    } else {
        return '0 days (expires today)';
    }
};

const getDaysUntilExpiryClass = (days) => {
    if (days < 0) return 'text-red-600 font-semibold';
    if (days <= 30) return 'text-red-600 font-semibold';
    if (days <= 90) return 'text-orange-600 font-semibold';
    if (days <= 180) return 'text-yellow-600 font-semibold';
    return 'text-green-600 font-semibold';
};

const getExpiryStatusClass = (status) => {
    const classes = {
        'expired': 'inline-flex px-3 py-1 text-xs font-semibold rounded-full shadow-sm bg-red-100 text-red-800 border border-red-200',
        'critical': 'inline-flex px-3 py-1 text-xs font-semibold rounded-full shadow-sm bg-red-100 text-red-800 border border-red-200',
        'warning': 'inline-flex px-3 py-1 text-xs font-semibold rounded-full shadow-sm bg-orange-100 text-orange-800 border border-orange-200',
        'notice': 'inline-flex px-3 py-1 text-xs font-semibold rounded-full shadow-sm bg-yellow-100 text-yellow-800 border border-yellow-200',
        'safe': 'inline-flex px-3 py-1 text-xs font-semibold rounded-full shadow-sm bg-green-100 text-green-800 border border-green-200'
    };
    return classes[status] || classes['safe'];
};

const getExpiryStatusLabel = (status) => {
    const labels = {
        'expired': 'Expired',
        'critical': 'Critical',
        'warning': 'Warning',
        'notice': 'Notice',
        'safe': 'Safe'
    };
    return labels[status] || 'Safe';
};

// Watch for filter changes and apply them
watch([
    () => search.value,
    () => expiry_timeframe.value,
    () => product_ids.value,
    () => category_ids.value,
    () => dosage_ids.value,
    () => warehouse_ids.value,
    () => per_page.value,
    () => props.filters.page
], () => {
    applyFilters();
});
</script> 