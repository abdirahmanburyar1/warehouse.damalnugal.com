<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import moment from 'moment';
import { TailwindPagination } from 'laravel-vue-pagination';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    receivedBackorders: Object,
    filters: Object,
    stats: Object,
    warehouses: Array,
    facilities: Array,
});

const parseAttachments = (attachments) => {
    console.log('parseAttachments called with:', attachments);
    if (!attachments) {
        console.log('No attachments, returning empty array');
        return [];
    }
    const files = typeof attachments === 'string' ? JSON.parse(attachments) : attachments;
    console.log('Parsed files:', files);
    const result = files.map(file => ({
        name: file.name || file.path.split('/').pop(),
        url: `${file.path}`
    }));
    console.log('Final result:', result);
    return result;
};

const activeDropdown = ref(null);

const toggleDropdown = (id) => {
    console.log('Toggle dropdown clicked for ID:', id);
    console.log('Current activeDropdown:', activeDropdown.value);
    activeDropdown.value = activeDropdown.value === id ? null : id;
    console.log('New activeDropdown:', activeDropdown.value);
};

const handleClickOutside = (event) => {
    const dropdowns = document.querySelectorAll('.attachments-dropdown');
    let clickedInside = false;

    dropdowns.forEach(dropdown => {
        if (dropdown.contains(event.target)) {
            clickedInside = true;
        }
    });

    if (!clickedInside) {
        activeDropdown.value = null;
    }
};

const search = ref(props.filters?.search || "");
const per_page = ref(props.filters?.per_page || 25);
const warehouse = ref(props.filters?.warehouse || "");
const facility = ref(props.filters?.facility || "");
const status = ref(props.filters?.status || "");

const warehouseOptions = computed(() => {
    const options = props.warehouses?.map(warehouse => ({
        value: warehouse.name,
        label: warehouse.name
    })) || [];
    return [{ value: "", label: 'All Warehouses' }, ...options];
});

const facilityOptions = computed(() => {
    const options = props.facilities?.map(name => ({
        value: name,
        label: name
    })) || [];
    return [{ value: "", label: 'All Facilities' }, ...options];
});

const statusOptions = computed(() => [
    { value: "", label: 'All Statuses' },
    { value: "pending", label: 'Pending' },
    { value: "reviewed", label: 'Reviewed' },
    { value: "approved", label: 'Approved' },
    { value: "rejected", label: 'Rejected' }
]);

watch([
    () => search.value,
    () => per_page.value,
    () => warehouse.value,
    () => facility.value,
    () => status.value,
    () => props.filters.page
], () => {
    reloadPage();
});

function reloadPage() {
    const query = {};
    if (search.value) query.search = search.value;
    if (per_page.value) {
        query.per_page = per_page.value;
    }
    if (warehouse.value) query.warehouse = warehouse.value;
    if (facility.value) query.facility = facility.value;
    if (status.value) query.status = status.value;
    if (props.filters.page) query.page = props.filters.page;
    
    router.get(route('supplies.received-backorder.index'), query, {
        preserveState: true,
        preserveScroll: true,
        only: ['receivedBackorders']
    });
}

function getResults(page = 1) {
    props.filters.page = page;
}

const getStatusBadge = (receivedBackorder) => {
    if (receivedBackorder.approved_at) {
        return { class: 'bg-green-100 text-green-800 border-green-200', text: 'Approved', icon: '/assets/images/approve.png' };
    } else if (receivedBackorder.rejected_at) {
        return { class: 'bg-red-100 text-red-800 border-red-200', text: 'Rejected', icon: '/assets/images/rejected.png' };
    } else if (receivedBackorder.reviewed_at) {
        return { class: 'bg-blue-100 text-blue-800 border-blue-200', text: 'Reviewed', icon: '/assets/images/review.png' };
    } else {
        return { class: 'bg-yellow-100 text-yellow-800 border-yellow-200', text: 'Pending', icon: '⏳' };
    }
};

const getTypeBadge = (type) => {
    const badges = {
        'Missing': { class: 'bg-orange-100 text-orange-800 border-orange-200', icon: '📦' },
        'Damaged': { class: 'bg-red-100 text-red-800 border-red-200', icon: '💥' },
        'Expired': { class: 'bg-purple-100 text-purple-800 border-purple-200', icon: '📅' },
        'Low quality': { class: 'bg-gray-100 text-gray-800 border-gray-200', icon: '⚠️' }
    };
    return badges[type] || { class: 'bg-gray-100 text-gray-800 border-gray-200', icon: '❓' };
};

// Group received backorders by back_order
const groupedReceivedBackorders = computed(() => {
    const grouped = {};
    
    if (!props.receivedBackorders.data) return grouped;
    
    props.receivedBackorders.data.forEach(receivedBackorder => {
        const backOrderKey = receivedBackorder.back_order_id || 'no_back_order';
        
        if (!grouped[backOrderKey]) {
            grouped[backOrderKey] = {
                back_order: receivedBackorder.back_order,
                receivedBackorders: []
            };
        }
        
        grouped[backOrderKey].receivedBackorders.push(receivedBackorder);
    });
    
    return grouped;
});

// Toggle group expansion
const expandedGroups = ref(new Set());

const toggleGroup = (groupKey) => {
    if (expandedGroups.value.has(groupKey)) {
        expandedGroups.value.delete(groupKey);
    } else {
        expandedGroups.value.add(groupKey);
    }
};

// Combined onMounted hook
onMounted(() => {
    // Add click outside listener for dropdowns
    document.addEventListener('click', handleClickOutside);
    
    // Expand all groups by default
    Object.keys(groupedReceivedBackorders.value).forEach(key => {
        expandedGroups.value.add(key);
    });
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

</script>

<template>
    <Head title="Received Back Orders" />
    <AuthenticatedLayout title="Received Back Orders"
        description="Manage and track received back orders" img="/assets/images/supplies.png">
        
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">Received Back Orders</h2>
                    <p class="text-gray-600 mt-1">Manage and track received back orders with comprehensive details</p>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                <!-- Search Filter -->
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Search by RB number, back order number..."
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                
                <!-- Warehouse Filter -->
                <div class="lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                    <Multiselect
                        v-model="warehouse"
                        :options="warehouseOptions"
                        :searchable="true"
                        :allow-empty="true"
                        :multiple="false"
                        :show-labels="false"
                        placeholder="Filter by Warehouse"
                        label="label"
                        track-by="value"
                        class="text-sm text-black"
                    />
                </div>
                
                <!-- Status Filter -->
                <div class="lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <Multiselect
                        v-model="status"
                        :options="statusOptions"
                        :searchable="true"
                        :allow-empty="true"
                        :multiple="false"
                        :show-labels="false"
                        placeholder="Filter by Status"
                        label="label"
                        track-by="value"
                        class="text-sm text-black"
                    />
                </div>
            </div>
            
            <!-- Second Row: Per Page Filter (Right Aligned) -->
            <div class="flex justify-end mt-4">
                <div class="w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                    <select 
                        v-model="per_page" 
                        @change="props.filters.page = 1" 
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="5">5 per page</option>
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Modern Table Section -->
        <div class="bg-white border border-gray-200 overflow-hidden">
            <div v-if="props.receivedBackorders.data.length === 0" class="text-center py-12">
                <div class="mx-auto h-24 w-24 text-gray-400">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-full w-full">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No received back order records found</h3>
                <p class="mt-2 text-gray-500">Try adjusting your search criteria or check back later.</p>
            </div>

            <div v-else class="overflow-auto">
                <table class="min-w-full border border-gray-300 table-fixed">
                    <colgroup>
                        <col class="w-40">
                        <col class="w-80">
                        <col class="w-24">
                        <col class="w-28">
                        <col class="w-28">
                        <col class="w-24">
                        <col class="w-24">
                    </colgroup>
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                RB ID
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Details
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Items Count
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Received Date
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Type
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Status
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Attachments
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr 
                            v-for="(receivedBackorder, index) in props.receivedBackorders.data" 
                            :key="receivedBackorder.id"
                            class="hover:bg-gray-50 transition-colors duration-150 border-b border-gray-300"
                        >
                            <!-- RB ID (link to detail) -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <Link 
                                    :href="route('supplies.received-backorder.show', receivedBackorder.id)"
                                    class="text-sm font-bold text-blue-700 hover:text-blue-900 hover:underline"
                                >
                                    #{{ receivedBackorder.received_backorder_number }}
                                </Link>
                            </td>

                            <!-- Details -->
                            <td class="px-4 py-3 border-r border-gray-300">
                                <div class="space-y-1">
                                    <div class="text-sm font-semibold text-gray-900 leading-tight">
                                        {{ receivedBackorder.source_display || (receivedBackorder.back_order ? receivedBackorder.back_order.back_order_number : 'Direct Received') }}
                                    </div>
                                    <div v-if="receivedBackorder.source_display && receivedBackorder.back_order" class="text-xs text-gray-600">
                                        {{ receivedBackorder.back_order.back_order_number }}
                                    </div>
                                    <div class="space-y-0.5 text-xs text-gray-600">
                                        <div v-if="receivedBackorder.warehouse"><span class="font-medium">Warehouse:</span> {{ receivedBackorder.warehouse.name || 'N/A' }}</div>
                                        <div v-if="receivedBackorder.facility"><span class="font-medium">Facility:</span> {{ receivedBackorder.facility.name || 'N/A' }}</div>
                                        <div v-if="receivedBackorder.note"><span class="font-medium">Note:</span> {{ receivedBackorder.note }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Items Count -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-900">{{ receivedBackorder.items_count || 0 }}</span>
                                    <span class="text-gray-500 ml-1">items</span>
                                </div>
                            </td>

                            <!-- Received Date -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ receivedBackorder.received_at ? moment(receivedBackorder.received_at).format('DD/MM/YYYY') : 'N/A' }}
                                </div>
                            </td>

                            <!-- Type -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ receivedBackorder.type || 'N/A' }}
                                </div>
                            </td>

                            <!-- Status Column -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="flex items-center">
                                    <span 
                                        :class="getStatusBadge(receivedBackorder).class"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                    >
                                        <img 
                                            v-if="getStatusBadge(receivedBackorder).icon && getStatusBadge(receivedBackorder).icon.startsWith('/')" 
                                            :src="getStatusBadge(receivedBackorder).icon" 
                                            class="w-3 h-3 mr-1"
                                            alt="Status"
                                        />
                                        <span v-else class="mr-1">{{ getStatusBadge(receivedBackorder).icon }}</span>
                                        {{ getStatusBadge(receivedBackorder).text }}
                                    </span>
                                </div>
                            </td>

                            <!-- Attachments Column -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="relative">
                                    <button 
                                        @click="toggleDropdown(receivedBackorder.id)"
                                        class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors duration-150"
                                    >
                                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Dropdown -->
                                    <div 
                                        v-if="activeDropdown === receivedBackorder.id"
                                        class="attachments-dropdown absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50"
                                    >
                                        <div class="p-3">
                                            <h4 class="text-sm font-medium text-gray-900 mb-2">Attachments</h4>
                                            <div v-if="parseAttachments(receivedBackorder.attachments).length > 0" class="space-y-2">
                                                <div 
                                                    v-for="(file, fileIndex) in parseAttachments(receivedBackorder.attachments)" 
                                                    :key="fileIndex"
                                                    class="flex items-center justify-between p-2 bg-gray-50 rounded"
                                                >
                                                    <div class="flex items-center space-x-2">
                                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                        </svg>
                                                        <span class="text-xs text-gray-700 truncate">{{ file.name }}</span>
                                                    </div>
                                                    <a 
                                                        :href="file.url" 
                                                        target="_blank"
                                                        class="text-blue-600 hover:text-blue-800 text-xs"
                                                    >
                                                        View
                                                    </a>
                                                </div>
                                            </div>
                                            <div v-else class="text-xs text-gray-500">No attachments</div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-4 mb-6">
            <TailwindPagination :data="props.receivedBackorders" :limit="3" @pagination-change-page="getResults" />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.rotate-180 {
    transform: rotate(180deg);
}
</style> 