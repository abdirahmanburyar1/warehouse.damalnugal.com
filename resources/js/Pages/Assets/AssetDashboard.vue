<template>
    <Head title="Asset Dashboard" />
    <AuthenticatedLayout title="Asset Dashboard" description="Welcome to the asset dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight">Asset Management Dashboard</h2>
                    <p class="text-sm text-gray-600 mt-1">Comprehensive overview of your asset portfolio</p>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Last Updated</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatDate(new Date()) }}</p>
                    </div>
                    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center items-center h-64">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-500">Loading asset data...</p>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div v-else class="space-y-8">
                <!-- Welcome Section -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl shadow-xl overflow-hidden">
                    <div class="px-8 py-6">
                        <div class="flex items-center justify-between">
                            <div class="text-white">
                                <h3 class="text-2xl font-bold mb-2">Welcome to Asset Management</h3>
                                <p class="text-blue-100 text-lg">Monitor, track, and manage your assets efficiently</p>
                            </div>
                            <div class="hidden md:block">
                                <div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                 <!-- Statistics Cards -->
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Total Assets Card -->
                    <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border-l-4 border-blue-500">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Total Assets</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ assetStatsData.total_assets || 0 }}</p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Assets Card -->
                    <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 border-l-4 border-green-500">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Active Assets</p>
                                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ assetStatsData.active_assets || 0 }}</p>
                                </div>
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>

                <!-- Asset Status Overview and Categories -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Asset Status Overview Chart -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-100">
                            <h3 class="text-xl font-semibold text-gray-900">Asset Status Overview</h3>
                            <p class="text-sm text-gray-600 mt-1">Assets by current status</p>
                        </div>
                        <div class="p-6">
                            <div class="h-80 flex items-center justify-center">
                                <canvas ref="statusChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Asset Categories -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-gray-100">
                            <h3 class="text-xl font-semibold text-gray-900">Asset Categories</h3>
                            <p class="text-sm text-gray-600 mt-1">Distribution by category</p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div v-for="category in assetCategories" :key="category.name" class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 hover:shadow-md transition-all duration-200">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center" :class="getCategoryColor(category.name)">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-2xl font-bold text-gray-900">{{ category.count }}</p>
                                            <p class="text-sm text-gray-600">{{ category.name }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Assets and Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Recent Assets -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-semibold text-gray-900">Recent Assets</h3>
                                <Link :href="route('assets.index')" class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-700 text-sm font-medium rounded-full hover:bg-purple-200 transition-colors">
                                    View All
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </Link>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div v-for="asset in recentAssets" :key="asset.id" class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl hover:shadow-md transition-all duration-200">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ asset.asset_name }}</p>
                                            <p class="text-xs text-gray-500 font-mono">{{ asset.asset_tag }}</p>
                                            <p class="text-xs text-gray-400">{{ formatDate(asset.created_at) }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" :class="getStatusClass(asset.status)">
                                            <div class="w-2 h-2 rounded-full mr-2" :class="getStatusDotClass(asset.status)"></div>
                                            {{ asset.status }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="!recentAssets || recentAssets.length === 0" class="text-center py-8">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 text-sm">No recent assets found</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-orange-50 to-red-50 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-semibold text-gray-900">Recent Activity</h3>
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></div>
                                    <span class="text-sm text-gray-600">Live Feed</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div v-for="activity in recentActivity" :key="activity.id" class="flex items-start space-x-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center shadow-md">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ activity.description }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ formatDate(activity.created_at) }}</p>
                                    </div>
                                </div>
                                <div v-if="!recentActivity || recentActivity.length === 0" class="text-center py-8">
                                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-gray-500 text-sm">No recent activity found</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-blue-50 border-b border-gray-100">
                        <h3 class="text-xl font-semibold text-gray-900">Quick Actions</h3>
                        <p class="text-sm text-gray-600 mt-1">Common tasks and shortcuts</p>
                    </div>
                    <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Link 
                                v-if="userPermissions.includes('asset-create')"
                                :href="route('assets.get-create')" 
                                class="group relative overflow-hidden bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:scale-105 hover:shadow-xl"
                            >
                                <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                                <div class="relative">
                                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2">Create Asset</h4>
                                    <p class="text-blue-100 text-sm">Add a new asset to your inventory</p>
                                </div>
                            </Link>

                            <Link 
                                v-if="userPermissions.includes('asset-view')"
                                :href="route('assets.index')" 
                                class="group relative overflow-hidden bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105 hover:shadow-xl"
                            >
                                <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                                <div class="relative">
                                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-semibold mb-2">View Assets</h4>
                                    <p class="text-green-100 text-sm">Browse and manage all assets</p>
                                </div>
                            </Link>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    assetStats: {
        type: Object,
        required: true,
    },
    userPermissions: {
        type: Array,
        required: true,
    },
    recentAssets: {
        type: Array,
        default: () => [],
    },
    recentActivity: {
        type: Array,
        default: () => [],
    },
});

const loading = ref(false);
const assetStatsData = ref(props.assetStats);
const statusChart = ref(null);

// Asset categories data
const assetCategories = computed(() => {
    return props.assetStats?.asset_categories || [];
});

// Asset status data for chart
const assetStatusData = computed(() => {
    return props.assetStats?.asset_status_data || {
        'In Use': 0,
        'Active': 0,
        'Functioning': 0,
        'Not functioning': 0,
        'Needs Maintenance': 0,
        'Pending Approval': 0,
        'Retired': 0,
        'Disposed': 0
    };
});




// Get status class for styling
function getStatusClass(status) {
    const statusClasses = {
        'active': 'bg-green-100 text-green-800',
        'inactive': 'bg-gray-100 text-gray-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'disposed': 'bg-red-100 text-red-800'
    };
    return statusClasses[status?.toLowerCase()] || 'bg-gray-100 text-gray-800';
}

// Get status dot class for styling
function getStatusDotClass(status) {
    const statusDotClasses = {
        'active': 'bg-green-500',
        'inactive': 'bg-gray-500',
        'pending': 'bg-yellow-500',
        'disposed': 'bg-red-500'
    };
    return statusDotClasses[status?.toLowerCase()] || 'bg-gray-500';
}

// Format date for display
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Get category color for styling
function getCategoryColor(categoryName) {
    const colors = {
        'Furniture': 'bg-blue-500',
        'IT Equipment': 'bg-purple-500',
        'Medical Equipment': 'bg-green-500',
        'Vehicles': 'bg-orange-500',
        'Others': 'bg-gray-500'
    };
    return colors[categoryName] || 'bg-gray-500';
}

// Initialize status chart
function initStatusChart() {
    if (!statusChart.value) return;
    
    const ctx = statusChart.value.getContext('2d');
    const statusData = assetStatusData.value;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(statusData),
            datasets: [{
                label: 'Asset Count',
                data: Object.values(statusData),
                backgroundColor: [
                    '#3B82F6', // Blue for In Use
                    '#10B981', // Green for Active
                    '#F59E0B', // Yellow for Needs Maintenance
                    '#EF4444', // Red for Pending Approval
                    '#6B7280', // Gray for Retired
                    '#DC2626'  // Dark red for Disposed
                ],
                borderColor: [
                    '#2563EB',
                    '#059669',
                    '#D97706',
                    '#DC2626',
                    '#4B5563',
                    '#B91C1C'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#F3F4F6'
                    },
                    ticks: {
                        color: '#6B7280'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#6B7280',
                        maxRotation: 45
                    }
                }
            }
        }
    });
}

onMounted(() => {
    nextTick(() => {
        initStatusChart();
    });
});
</script>
