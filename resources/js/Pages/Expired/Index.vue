<template>
    <AuthenticatedLayout description="Expired" title="Expired" img="/assets/images/expires.png">
        <div class="p-2 mb-[100px]">
            <div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2">
                    <div class="col-span-1 md:col-span-2 min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input v-model="search" type="text" class="w-full"
                            placeholder="Search by item name, barcode" />
                    </div>
                    <div class="col-span-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <Multiselect v-model="category" :options="props.categories" :searchable="true"
                            :close-on-select="true" :show-labels="false" placeholder="Select a category"
                            :allow-empty="true" class="multiselect--with-icon w-full">
                        </Multiselect>
                    </div>
                    <div class="col-span-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Dosage Form</label>
                        <Multiselect v-model="dosage" :options="props.dosage" :searchable="true" :close-on-select="true"
                            :show-labels="false" placeholder="Select a dosage form" :allow-empty="true"
                            class="multiselect--with-icon w-full">
                        </Multiselect>
                    </div>
                    <div class="col-span-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                        <Multiselect v-model="warehouse" :options="props.warehouses" :searchable="true"
                            :close-on-select="true" :show-labels="false" placeholder="Select a warehouse"
                            :allow-empty="true" class="multiselect--with-icon multiselect--rounded w-full">
                        </Multiselect>
                    </div>
                    <div class="col-span-1 min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Storage Location</label>
                        <Multiselect v-model="location" :options="loadedLocation" :searchable="true"
                            :close-on-select="true" :show-labels="false" placeholder="Select a S. Location"
                            :allow-empty="true" :disabled="warehouse == null"
                            class="multiselect--with-icon multiselect--rounded w-full">
                        </Multiselect>
                    </div>
                </div>
                <div class="flex justify-end">
                    <select v-model="per_page"
                        class="rounded-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-[200px] mb-1"
                        @change="props.filters.page = 1">
                        <option :value="6">6 per page</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                        <option :value="100">100 per page</option>
                        <option :value="200">200 per page</option>
                    </select>
                </div>
            </div>
            <!-- Header Section with Icon Legend Button -->
            <div class="flex items-center justify-between mb-6">
                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" :class="[
                            activeTab === tab.id
                                ? 'border-green-500 text-green-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-xs'
                        ]">
                            {{ tab.name }}
                        </button>
                    </nav>
                </div>
                
                <!-- Icon Legend Button -->
                <button @click="showLegend = true"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 rounded-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Icon Legend
                </button>
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-12 gap-4">
                <!-- LEFT COLUMN: Table (8/12) -->
                <div class="col-span-12 md:col-span-9 overflow-auto">
                    <table class="w-full rounded-t-3xl overflow-hidden table-sm">
                        <thead>
                            <tr style="background-color: #EFF6FF;">
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b rounded-tl-lg w-[300px]"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Item
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Quantity
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Batch Number
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Location
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Expiry Date
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Days Until Expiry
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Status
                                </th>
                                <th
                                    class="px-3 py-3 text-left text-xs font-bold border-b rounded-tr-lg"
                                    style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-if="props.inventories.data.length === 0">
                                <td colspan="8" class="text-center py-8 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                            </path>
                                        </svg>
                                        <p class="mt-2 text-sm font-medium text-gray-900">
                                            No expired items available
                                        </p>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Adjust your filters to see results
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="item in props.inventories.data" :key="item.id"
                                class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-3 py-3 text-sm font-medium text-gray-900 border-b break-words" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ item.product?.name }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ item.batch_number }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ item.location || 'N/A' }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-700 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ formatDate(item.expiry_date) }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm border-b" style="border-bottom: 1px solid #B7C6E6;">
                                    <div :class="{
                                        'text-sm font-medium': true,
                                        'text-gray-600': item.expired,
                                        'text-orange-600': !item.expired && item.days_until_expiry > 180 && item.days_until_expiry <= 365,
                                        'text-pink-600': !item.expired && item.days_until_expiry <= 180 && item.days_until_expiry > 0
                                    }">
                                        {{ item.days_until_expiry }} days
                                    </div>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm border-b" style="border-bottom: 1px solid #B7C6E6;">
                                    <span v-if="item.expired"
                                        class="p-[5px] rounded-xl inline-flex items-center bg-gray-600 text-xs font-medium text-white">
                                        Expired
                                    </span>
                                    <span v-else-if="item.days_until_expiry <= 180 && item.days_until_expiry > 0"
                                        class="p-[5px] rounded-xl inline-flex items-center bg-pink-500 text-xs font-medium text-white">
                                        Expiring Very Soon
                                    </span>
                                    <span v-else-if="item.days_until_expiry > 180 && item.days_until_expiry <= 365"
                                        class="p-[5px] rounded-xl inline-flex items-center bg-orange-400 text-xs font-medium text-white">
                                        Expiring Soon
                                    </span>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-sm border-b rounded-tr-3xl" style="border-bottom: 1px solid #B7C6E6;">
                                    <template v-if="item.expired">
                                        <button class="text-red-600 hover:text-red-900"
                                            @click="disposeItem(item)">
                                            <img src="/assets/images/Disposal.png" alt="Dispose" class="w-10" title="Dispose">
                                        </button>
                                    </template>
                                    <template v-else>
                                        <Link class="text-blue-600 hover:text-blue-900"
                                            :href="route('expired.transfer', item.id)">
                                            <img src="/assets/images/facility.png" alt="Transfer" class="w-10" title="Transfer">
                                        </Link>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="mt-6 px-4 py-3 flex items-center justify-end">
                        <TailwindPagination :data="props.inventories" @pagination-change-page="getResults" :limit="3" />
                    </div>
                </div>

                <!-- RIGHT COLUMN: Summary cards (4/12) -->
                <div class="col-span-12 md:col-span-3">
                    <div v-if="activeTab === 'all' || activeTab === 'year'"
                        class="bg-orange-400 rounded-lg p-4 text-white shadow-lg mb-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium">Expiring within next 1 Year</h3>
                                <div class="mt-1 text-lg font-bold">{{ filteredStats.year }} Item</div>
                                <div class="text-xs mt-1">{{ formatDate(oneYearFromNow) }}</div>
                            </div>
                            <img src="/assets/images/soon_expire.png" class="w-12 h-12" alt="box">
                        </div>
                    </div>

                    <div v-if="activeTab === 'all' || activeTab === 'six_months'"
                        class="bg-pink-500 rounded-lg p-4 text-white shadow-lg mb-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium">Expiring within next 6 months</h3>
                                <div class="mt-1 text-lg font-bold">{{ filteredStats.six_months }} Items</div>
                                <div class="text-xs mt-1">{{ formatDate(sixMonthsFromNow) }}</div>
                            </div>
                            <img src="/assets/images/Near Expiration Alert.png" class="w-12 h-12" alt="box">
                        </div>
                    </div>

                    <div v-if="activeTab === 'all' || activeTab === 'expired'"
                        class="bg-gray-600 rounded-lg p-4 text-white shadow-lg">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium">Expired Items</h3>
                                <div class="mt-1 text-lg font-bold">{{ filteredStats.expired }} Items</div>
                            </div>
                            <img src="/assets/images/expired_stock.png" class="w-12 h-12" alt="box">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Dispose Modal -->
    <Modal :show="showDisposeModal" @close="showDisposeModal = false">
        <form @submit.prevent="submitDisposal" class="p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Dispose Item</h2>

            <!-- Product Info -->
            <div v-if="selectedItem" class="bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Product Name</p>
                        <p class="text-xs text-gray-900">{{ selectedItem.product?.name }}</p>
                        <p class="text-xs font-medium text-gray-500">Batch Number</p>
                        <p class="text-xs text-gray-900">{{ selectedItem.batch_number }}</p>
                        <p class="text-xs font-medium text-gray-500">Barcode</p>
                        <p class="text-xs text-gray-900">{{ selectedItem.barcode || 'N/A' }}</p>
                        <p class="text-xs font-medium text-gray-500">UOM</p>
                        <p class="text-xs text-gray-900">{{ selectedItem.uom }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Expiry Date</p>
                        <p class="text-xs text-gray-900">{{ formatDate(selectedItem.expiry_date) }}</p>
                        <p class="text-xs"
                            :class="{ 'text-red-600': selectedItem.expired, 'text-green-600': !selectedItem.expired }">
                            {{
                                selectedItem.expired ? 'Expired' : 'Not Expired' }}</p>
                    </div>
                </div>
            </div>

            <!-- Quantity -->
            <div>
                <label for="quantity" class="block text-xs font-medium text-gray-700">Quantity</label>
                <input type="number" id="quantity" v-model.number="disposeForm.quantity" readonly
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs"
                    :min="1" :max="selectedItem?.quantity" step="1" required>
            </div>

            <!-- Note -->
            <div>
                <label for="note" class="block text-xs font-medium text-gray-700">Note</label>
                <textarea id="note" v-model="disposeForm.note"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xs"
                    rows="3" required></textarea>
            </div>

            <!-- Attachments (optional) -->
            <div>
                <label class="block text-xs font-medium text-gray-700">Attachments (PDF files, optional)</label>
                <input type="file" ref="disposeFileInput" @change="handleFileChange"
                    class="mt-1 block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    multiple accept=".pdf">
            </div>

            <!-- Selected Files Preview -->
            <div v-if="disposeForm.attachments.length > 0" class="mt-2">
                <h4 class="text-xs font-medium text-gray-700 mb-2">Selected Files:</h4>
                <ul class="space-y-2">
                    <li v-for="(file, index) in disposeForm.attachments" :key="index"
                        class="flex items-center justify-between text-xs text-gray-500 bg-gray-50 p-2 rounded">
                        <span>{{ file.name }}</span>
                        <button type="button" @click="removeFile(index)" class="text-red-500 hover:text-red-700">
                            Remove
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" :disabled="isDisposing"
                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    @click="showDisposeModal = false">
                    Cancel
                </button>
                <button type="submit" :disabled="isDisposing"
                    class="inline-flex justify-center rounded-md border border-transparent bg-yellow-500 px-4 py-2 text-xs font-medium text-white shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2">
                    {{ isDisposing ? "Disposing..." : "Dispose" }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Slideover for Icon Legend -->
    <transition name="slide">
        <div v-if="showLegend" class="fixed inset-0 z-50 flex justify-end">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-30 transition-opacity" @click="showLegend = false"></div>
            <!-- Slideover Panel -->
            <div class="relative w-full max-w-sm bg-white shadow-xl h-full flex flex-col p-6 overflow-y-auto">
                <button @click="showLegend = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <h2 class="text-lg font-bold text-blue-700 mb-6 mt-2">Icon Legend</h2>
                <ul class="space-y-5">
                    <li class="flex items-center gap-4">
                        <img src="/assets/images/Disposal.png" class="w-10 h-10" alt="Dispose" />
                        <div>
                            <div class="font-semibold text-red-600">Dispose</div>
                            <div class="text-xs text-gray-500">Dispose of expired items with documentation.</div>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <img src="/assets/images/facility.png" class="w-10 h-10" alt="Transfer" />
                        <div>
                            <div class="font-semibold text-blue-600">Transfer</div>
                            <div class="text-xs text-gray-500">Transfer items to another facility or warehouse.</div>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-gray-600 rounded-xl flex items-center justify-center">
                            <span class="text-xs font-medium text-white">Exp</span>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-600">Expired</div>
                            <div class="text-xs text-gray-500">Items that have passed their expiry date.</div>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-pink-500 rounded-xl flex items-center justify-center">
                            <span class="text-xs font-medium text-white">VS</span>
                        </div>
                        <div>
                            <div class="font-semibold text-pink-600">Expiring Very Soon</div>
                            <div class="text-xs text-gray-500">Items expiring within 6 months (≤180 days).</div>
                        </div>
                    </li>
                    <li class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-orange-400 rounded-xl flex items-center justify-center">
                            <span class="text-xs font-medium text-white">S</span>
                        </div>
                        <div>
                            <div class="font-semibold text-orange-600">Expiring Soon</div>
                            <div class="text-xs text-gray-500">Items expiring within 1 year (≤365 days).</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </transition>
</template>
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { format, addMonths, addYears } from 'date-fns'
import { ref, computed, watch, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import Transfer from './Transfer.vue'
import Modal from '@/Components/Modal.vue'
import { useToast } from 'vue-toastification'
import moment from 'moment'
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import { TailwindPagination } from "laravel-vue-pagination";

import TextInput from "@/Components/TextInput.vue";

const toast = useToast()

const props = defineProps({
    inventories: Object,
    products: Array,
    dosage: Array,
    categories: Array,
    warehouses: Array,
    filters: Object,
    summary: Object,
})

const activeTab = ref(props.filters.tab || 'all')

// Icon Legend state
const showLegend = ref(false)

const tabs = [
    { id: 'all', name: 'All Items' },
    { id: 'year', name: 'Expiring within next 1 Year' },
    { id: 'six_months', name: 'Expiring within next 6 months' },
    { id: 'expired', name: 'Expired' },
]

const now = new Date()
const sixMonthsFromNow = addMonths(now, 6)
const oneYearFromNow = addYears(now, 1)

const formatDate = (date) => {
    return moment(date).format('DD/MM/YYYY');
}

const showDisposeModal = ref(false);
const selectedItem = ref(null);
const disposeFileInput = ref(null);

const disposeForm = ref({
    quantity: 0,
    note: '',
    attachments: []
});

const handleFileChange = (e) => {
    const files = Array.from(e.target.files || []);
    disposeForm.value.attachments = files;
};

const removeFile = (index) => {
    disposeForm.value.attachments.splice(index, 1);
};

const disposeItem = (item) => {
    selectedItem.value = item;
    const qty = Math.floor(Number(item.quantity)) || 0;
    disposeForm.value = {
        quantity: qty,
        note: '',
        attachments: []
    };
    showDisposeModal.value = true;
};

const search = ref(props.filters.search || "");
const location = ref(props.filters.location || "");
const dosage = ref(props.filters.dosage || "");
const category = ref(props.filters.category || "");
const warehouse = ref(props.filters.warehouse || "");
const per_page = ref(props.filters.per_page || 25);

const loadedLocation = ref([]);

// Initialize locations if warehouse is already selected
onMounted(() => {
    if (warehouse.value) {
        loadLocations();
    }
});

// Apply filters
const applyFilters = () => {
    const query = {};
    // Add all filter values to query object
    if (search.value) query.search = search.value;
    if (location.value) query.location = location.value;
    if (warehouse.value) query.warehouse = warehouse.value;
    if (dosage.value) query.dosage = dosage.value;
    if (category.value) query.category = category.value;

    // Add active tab to query
    if (activeTab.value && activeTab.value !== 'all') {
        query.tab = activeTab.value;
    }

    // Always include per_page in query if it exists
    if (per_page.value) query.per_page = per_page.value;
    
    // Always include page in query
    if (props.filters.page) query.page = props.filters.page;

    router.get(route("expired.index"), query, {
        preserveState: true,
        preserveScroll: true,
        only: [
            "inventories",
            "products",
            "warehouses",
            "filters",
            "summary",
            "locations",
            "dosage",
            "category",
        ],
    });
};

// Watch for changes in search input and other filters
watch(
    [
        () => search.value,
        () => location.value,
        () => per_page.value,
        () => warehouse.value,
        () => dosage.value,
        () => category.value,
        () => props.filters.page,
    ],
    () => {
        applyFilters();
    }
);

// Watch for tab changes separately to reset page
watch(
    () => activeTab.value,
    () => {
        // Reset page to 1 when tab changes
        props.filters.page = 1;
        applyFilters();
    }
);

function getResults(page = 1) {
    props.filters.page = page;
}

const isDisposing = ref(false)

const submitDisposal = async () => {
    isDisposing.value = true
    const formData = new FormData();
    formData.append('id', selectedItem.value.id);
    const quantity = Math.floor(Number(disposeForm.value.quantity)) || 1;
    formData.append('quantity', quantity);
    formData.append('note', disposeForm.value.note);
    formData.append('type', 'Expired');
    formData.append('barcode', selectedItem.value.barcode);
    formData.append('batch_number', selectedItem.value.batch_number);
    formData.append('uom', selectedItem.value.uom);
    formData.append('expiry_date', selectedItem.value.expiry_date);
    formData.append('status', 'Expired');
    formData.append('product_id', selectedItem.value.product_id);

    // Append each attachment
    for (let i = 0; i < disposeForm.value.attachments.length; i++) {
        formData.append('attachments[]', disposeForm.value.attachments[i]);
    }

    await axios.post(route('expired.dispose'), formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })
        .then((response) => {
            isDisposing.value = false;
            const message = typeof response.data === 'string' ? response.data : (response.data?.message || 'Item has been disposed successfully');
            Swal.fire({
                icon: 'success',
                title: message,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                showDisposeModal.value = false;
                disposeForm.value = {
                    quantity: 0,
                    note: '',
                    attachments: []
                };
                if (disposeFileInput.value) disposeFileInput.value.value = '';
                router.get(route('expired.index'), {}, {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['inventories', 'summary', 'filters']
                });
            });
        })
        .catch(error => {
            console.error('Error disposing item:', error);
            isDisposing.value = false;
            const errMsg = error.response?.data?.message ?? (typeof error.response?.data === 'string' ? error.response.data : 'Failed to dispose item');
            Swal.fire({
                icon: 'error',
                title: errMsg,
                showConfirmButton: false,
                timer: 1500
            });
        });
};

const filteredStats = computed(() => {
    // When a specific tab is selected, the backend will filter the data
    // So we show the appropriate stats based on the current tab
    if (activeTab.value === 'all') {
        return {
            year: props.summary.expiring_within_1_year || 0,
            six_months: props.summary.expiring_within_6_months || 0,
            expired: props.summary.expired || 0,
            disposed: props.summary.disposed || 0
        }
    } else if (activeTab.value === 'year') {
        return {
            year: props.summary.expiring_within_1_year || 0,
            six_months: 0,
            expired: 0,
            disposed: 0
        }
    } else if (activeTab.value === 'six_months') {
        return {
            year: 0,
            six_months: props.summary.expiring_within_6_months || 0,
            expired: 0,
            disposed: 0
        }
    } else if (activeTab.value === 'expired') {
        return {
            year: 0,
            six_months: 0,
            expired: props.summary.expired || 0,
            disposed: 0
        }
    }
    return { year: 0, six_months: 0, expired: 0, disposed: 0 }
})


watch([() => warehouse.value], () => {
    if (warehouse.value == null) {
        location.value = null;
        loadedLocation.value = [];
        return;
    }
    loadLocations();
});

async function loadLocations() {
    try {
        const response = await axios.get(route("inventories.getLocations"), {
            params: {
                warehouse: warehouse.value,
            }
        });
        loadedLocation.value = response.data.map(item => item.location);
    } catch (error) {
        console.log(error);
        toast.error('Failed to load locations');
        loadedLocation.value = [];
    }
}

</script>