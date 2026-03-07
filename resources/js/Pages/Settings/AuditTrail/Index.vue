<template>
    <AuthenticatedLayout title="Audit Trails" description="View System Activity Logs" img="/assets/images/settings.png">
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">System Audit Trails</h1>
            <Link :href="route('settings.index')" class="text-indigo-600 hover:text-indigo-900 font-medium">
                &larr; Back to Settings
            </Link>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" v-model="form.start_date" @change="filter" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" v-model="form.end_date" @change="filter" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                    <select v-model="form.action" @change="filter" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Actions</option>
                        <option v-for="action in actions" :key="action" :value="action">
                            {{ action.charAt(0).toUpperCase() + action.slice(1) }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" v-model="form.search" @input="debouncedSearch" placeholder="Search user or module..." class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button @click="resetFilters" class="text-sm text-gray-600 hover:text-gray-900 font-medium">
                    Reset Filters
                </button>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module / ID</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="audit in audits.data" :key="audit.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ formatDate(audit.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <div v-if="audit.user">
                                    {{ audit.user.name }}
                                    <div class="text-xs text-gray-500">{{ audit.user.email }}</div>
                                </div>
                                <span v-else class="text-gray-400 italic">System</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getActionClass(audit.action)">
                                    {{ audit.action }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ audit.auditable_type_short }}</div>
                                <div class="text-xs font-medium text-gray-900">ID: {{ audit.auditable_id }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button @click="viewDetails(audit)" class="text-indigo-600 hover:text-indigo-900">
                                    View Metadata
                                </button>
                            </td>
                        </tr>
                        <tr v-if="audits.data.length === 0">
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                No audit records found matching your criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 border-t border-gray-200" v-if="audits.data.length > 0">
                <Pagination :links="audits.links" />
            </div>
        </div>

        <!-- Metadata Modal -->
        <Modal :show="showModal" @close="closeModal" maxWidth="2xl">
            <div class="p-6">
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h2 class="text-lg font-medium text-gray-900">Audit Metadata Details</h2>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div v-if="selectedAudit">
                    <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-500 block">Action:</span>
                            <span class="font-semibold" :class="getActionTextClass(selectedAudit.action)">{{ selectedAudit.action.toUpperCase() }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-500 block">Date:</span>
                            {{ formatDate(selectedAudit.created_at) }}
                        </div>
                        <div>
                            <span class="font-medium text-gray-500 block">IP Address:</span>
                            {{ selectedAudit.metadata?.ip_address || 'N/A' }}
                        </div>
                        <div>
                            <span class="font-medium text-gray-500 block">User Agent:</span>
                            <span class="truncate block" :title="selectedAudit.metadata?.user_agent">{{ selectedAudit.metadata?.user_agent || 'N/A' }}</span>
                        </div>
                    </div>

                    <div v-if="selectedAudit.metadata?.old_values || selectedAudit.metadata?.new_values" class="mt-6 border border-gray-200 rounded-md overflow-hidden">
                        <div class="grid grid-cols-2 divide-x divide-gray-200">
                            <div class="p-4 bg-red-50">
                                <h3 class="text-sm font-semibold text-red-800 mb-2">Old Values</h3>
                                <pre class="text-xs text-red-700 whitespace-pre-wrap font-mono">{{ formatRawMetadata(selectedAudit.metadata?.old_values) }}</pre>
                            </div>
                            <div class="p-4 bg-green-50">
                                <h3 class="text-sm font-semibold text-green-800 mb-2">New Values</h3>
                                <pre class="text-xs text-green-700 whitespace-pre-wrap font-mono">{{ formatRawMetadata(selectedAudit.metadata?.new_values) }}</pre>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Raw Metadata</h3>
                        <div class="bg-gray-50 border border-gray-200 rounded-md p-4">
                            <pre class="text-xs text-gray-800 whitespace-pre-wrap font-mono">{{ formatRawMetadata(selectedAudit.metadata) }}</pre>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button @click="closeModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    audits: Object,
    filters: Object,
    actions: Array,
});

const form = ref({
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    action: props.filters?.action || '',
    search: props.filters?.search || '',
});

const showModal = ref(false);
const selectedAudit = ref(null);

const filter = () => {
    router.get(route('settings.audit-trail.index'), form.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const debouncedSearch = debounce(filter, 300);

const resetFilters = () => {
    form.value = { start_date: '', end_date: '', action: '', search: '' };
    filter();
};

const viewDetails = (audit) => {
    selectedAudit.value = audit;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => { selectedAudit.value = null; }, 300);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
};

const formatRawMetadata = (data) => {
    if (!data) return 'No data available';
    // Create a copy and remove common request info if it exists alongside old/new values
    const displayData = { ...data };
    if (displayData.ip_address) delete displayData.ip_address;
    if (displayData.user_agent) delete displayData.user_agent;
    if (displayData.facility_id) delete displayData.facility_id;
    
    // Return formatted JSON or "No specific data" if empty object
    return Object.keys(displayData).length > 0 ? JSON.stringify(displayData, null, 2) : 'No specific changes captured';
};

const getActionClass = (action) => {
    switch (action) {
        case 'created': return 'bg-green-100 text-green-800';
        case 'updated': return 'bg-blue-100 text-blue-800';
        case 'deleted': return 'bg-red-100 text-red-800';
        case 'login': return 'bg-purple-100 text-purple-800';
        case 'logout': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getActionTextClass = (action) => {
    switch (action) {
        case 'created': return 'text-green-600';
        case 'updated': return 'text-blue-600';
        case 'deleted': return 'text-red-600';
        case 'login': return 'text-purple-600';
        default: return 'text-gray-600';
    }
};
</script>