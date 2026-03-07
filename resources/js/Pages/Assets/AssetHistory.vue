<template>
    <AuthenticatedLayout :title="pageTitle" :description="pageDescription">
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="bg-white shadow-xl rounded-2xl">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 bg-white/20 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-10 h-10 text-white">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white">Asset History</h1>
                                <p class="text-blue-100 text-sm mt-1">
                                    {{ pageDescription }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 sm:mt-0">
                            <Link :href="route('assets.index')"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-blue-700 bg-white hover:bg-blue-50 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-4 h-4 mr-2">
                                    <path d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Assets
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Information -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">Asset Information</h2>
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-2">
                        <div class="flex items-center space-x-2">
                            <svg class="h-4 w-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs text-blue-700 font-medium">
                                History is tracked per individual asset item, not per asset
                            </span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                         <div>
                         <label class="block text-sm font-medium text-gray-500">Asset Number</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.asset?.asset_number || 'N/A' }}</p>
                     </div>
                     <div>
                         <label class="block text-sm font-medium text-gray-500">Asset Tag</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.asset?.asset_tag || 'N/A' }}</p>
                     </div>
                                         <div>
                         <label class="block text-sm font-medium text-gray-500">Category</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.category?.name || 'N/A' }}</p>
                     </div>
                     <div>
                         <label class="block text-sm font-medium text-gray-500">Type</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.type?.name || 'N/A' }}</p>
                     </div>
                                         <div>
                         <label class="block text-sm font-medium text-gray-500">Region</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.asset?.region?.name || 'N/A' }}</p>
                     </div>
                     <div>
                         <label class="block text-sm font-medium text-gray-500">District</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.asset?.district?.name || 'N/A' }}</p>
                     </div>
                     <div>
                         <label class="block text-sm font-medium text-gray-500">Location</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.asset?.facility?.name || 'N/A' }}</p>
                     </div>
                     <div>
                         <label class="block text-sm font-medium text-gray-500">Sub Location</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.asset?.sub_location?.name || 'N/A' }}</p>
                     </div>
                     <div>
                         <label class="block text-sm font-medium text-gray-500">Fund Source</label>
                         <p class="mt-1 text-sm text-gray-900">{{ assetItem?.asset?.fund_source?.name || 'N/A' }}</p>
                     </div>
                     <div>
                         <label class="block text-sm font-medium text-gray-500">Acquisition Date</label>
                         <p class="mt-1 text-sm text-gray-900">{{ formatDate(assetItem?.asset?.acquisition_date) }}</p>
                     </div>
                </div>
            </div>

            <!-- Asset Item Details -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Asset Item Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Asset Item Tag</label>
                        <p class="mt-1 text-sm text-gray-900 font-semibold">{{ assetItem?.asset_tag || 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Serial Number</label>
                        <p class="mt-1 text-sm text-gray-900">{{ assetItem?.serial_number || 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Status</label>
                        <span :class="getStatusClass(assetItem?.status)" class="mt-1 px-2 py-1 text-xs font-semibold rounded-full">
                            {{ formatStatus(assetItem?.status) }}
                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Assignee</label>
                        <p class="mt-1 text-sm text-gray-900">{{ assetItem?.assignee?.name || 'Unassigned' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Value</label>
                        <p class="mt-1 text-sm text-gray-900">${{ assetItem?.original_value ? Number(assetItem.original_value).toLocaleString() : 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Category</label>
                        <p class="mt-1 text-sm text-gray-900">{{ assetItem?.category?.name || 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Type</label>
                        <p class="mt-1 text-sm text-gray-900">{{ assetItem?.type?.name || 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- History Summary -->
            <div class="bg-white shadow-xl rounded-2xl p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">History Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 13.293A1 1 0 013 12.586V4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-blue-600">Asset Item</p>
                                <p class="text-2xl font-bold text-blue-900">{{ assetItem?.asset_tag || 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-600">Total History Records</p>
                                <p class="text-2xl font-bold text-green-900">{{ assetItem?.asset_history?.length || 0 }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                        <div class="flex items-center">
                            <div class="p-2 bg-orange-100 rounded-lg">
                                <svg class="w-6 h-6 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-orange-600">Latest Activity</p>
                                <p class="text-sm font-bold text-orange-900">{{ getLatestActivityDate() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Item History Timeline -->
            <div class="bg-white shadow-xl rounded-2xl p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Asset Item History Timeline</h2>
                <p class="text-sm text-gray-600 mb-6">
                    This asset item maintains its own history of actions, transfers, and status changes. 
                    Below you can see the detailed timeline for this specific asset item.
                </p>
                
                <div v-if="assetItem?.asset_history && assetItem.asset_history.length > 0" class="space-y-4">
                    <div class="relative">
                        <!-- Timeline Line -->
                        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                        
                        <!-- History Items -->
                        <div class="space-y-4">
                            <div v-for="(history, index) in assetItem.asset_history" :key="history.id" 
                                class="relative flex items-start space-x-4">
                                <!-- Timeline Dot -->
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center z-10">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                
                                <!-- History Content -->
                                <div class="flex-1 bg-gray-50 rounded-lg p-4 border-l-4 border-blue-500">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-semibold text-gray-900">{{ history.action }}</span>
                                            <span class="text-xs text-gray-500 bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                                {{ history.action_type }}
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ formatDate(history.performed_at) }}</span>
                                    </div>
                                    
                                    <p class="text-sm text-gray-700 mb-3">{{ history.notes }}</p>
                                    
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <div class="flex items-center space-x-4">
                                            <span v-if="history.performer">
                                                <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                                </svg>
                                                {{ history.performer?.name || 'Unknown User' }}
                                            </span>
                                            <span v-if="history.assignee">
                                                <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" clip-rule="evenodd" />
                                                </svg>
                                                {{ history.assignee?.name }}
                                            </span>
                                        </div>
                                        
                                        <!-- Action Type Icon -->
                                        <div class="flex items-center space-x-1">
                                            <svg v-if="history.action_type === 'transfer'" class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else-if="history.action_type === 'status_change'" class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.372-.836 2.942-2.106 2.106-1.372a1.533 1.533 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else-if="history.action_type === 'approval'" class="w-3 h-3 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="text-gray-400">{{ history.action_type }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- No History Message -->
                <div v-else class="text-center py-12 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-lg font-medium text-gray-900 mb-2">No History Records</p>
                    <p class="text-sm text-gray-500">This asset item has no recorded history yet.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
    assetItem: {
        type: Object,
        required: true,
    },
    pageTitle: {
        type: String,
        default: 'Asset History',
    },
    pageDescription: {
        type: String,
        default: 'View detailed history for this asset',
    },
});

// Utility functions
const formatStatus = (status) => {
    if (!status) return '-';
    const statusMap = {
        'functioning': 'Functioning',
        'not_functioning': 'Not functioning',
        'active': 'Active',
        'in_use': 'In Use',
        'maintenance': 'Maintenance',
        'retired': 'Retired',
        'disposed': 'Disposed',
        'pending_approval': 'Pending Approval'
    };
    return statusMap[status] || status.replace('_', ' ').toUpperCase();
};

const formatDate = (date) => {
    if (!date) return '-';
    return moment(date).format('DD/MM/YYYY');
};

const getStatusClass = (status) => {
    const statusClasses = {
        'functioning': 'bg-green-100 text-green-800',
        'not_functioning': 'bg-orange-100 text-orange-800',
        'active': 'bg-green-100 text-green-800',
        'in_use': 'bg-blue-100 text-blue-800',
        'maintenance': 'bg-yellow-100 text-yellow-800',
        'retired': 'bg-gray-100 text-gray-800',
        'disposed': 'bg-red-100 text-red-800',
        'pending_approval': 'bg-orange-100 text-orange-800'
    };
    return statusClasses[status] || 'bg-gray-100 text-gray-800';
};

// Helper functions for history summary
const getLatestActivityDate = () => {
    let latestDate = null;
    
    if (props.assetItem?.asset_history && props.assetItem.asset_history.length > 0) {
        props.assetItem.asset_history.forEach(history => {
            if (history.performed_at) {
                const historyDate = new Date(history.performed_at);
                if (!latestDate || historyDate > latestDate) {
                    latestDate = historyDate;
                }
            }
        });
    }
    
    if (latestDate) {
        return moment(latestDate).format('DD/MM/YYYY');
    }
    
    return 'No activity';
};
</script>
