<template>
    <AuthenticatedLayout :title="computedPageTitle" :description="computedPageDescription" img="/assets/images/asset-header.png">
        <div v-if="props.error">
            {{ props.error }}
        </div>
        <div v-else-if="!props.asset">
            <div class="text-center py-12">
                <div class="text-gray-500 text-lg">Asset not found or has been deleted.</div>
                <div class="text-sm text-gray-400 mt-2">Debug: Asset ID from URL: {{ page.url.split('/').pop() }}</div>
                <div class="text-sm text-gray-400 mt-2">Debug: Props received: {{ JSON.stringify(props) }}</div>
                <Link :href="route('assets.index')" class="mt-4 inline-block text-blue-600 hover:text-blue-800">
                    Back to Assets
                </Link>
            </div>
        </div>
        <div v-else>
            <!-- Header Section -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <Link :href="route('assets.index')" class="text-blue-600 hover:text-blue-800">Back to assets</Link>
                        <h1 class="text-xs font-semibold text-gray-900">
                            Asset ID: {{ props.asset.asset_number }}
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span :class="[
                            statusClasses[props.asset.status] ||
                            statusClasses.default,
                        ]" class="flex items-center text-xs font-bold px-4 py-2">
                            <!-- Status Icon -->
                            <span class="mr-3">
                                <!-- Pending Approval Icon -->
                                <img v-if="props.asset.status === 'pending_approval'" src="/assets/images/pending.png"
                                    class="w-4 h-4" alt="Pending Approval" />
                                <!-- Approved Icon -->
                                <img v-else-if="props.asset.status === 'approved'" src="/assets/images/approved.png"
                                    class="w-4 h-4" alt="Approved" />
                                <!-- Rejected Icon -->
                                <img v-else-if="props.asset.status === 'rejected'" src="/assets/images/rejected.png"
                                    class="w-4 h-4" alt="Rejected" />

                            </span>
                            {{ props.asset.status.replace('_', ' ').toUpperCase() }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Asset Information Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                <!-- Basic Asset Information -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <h2 class="text-lg font-medium text-gray-900">
            Asset Details
        </h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Asset Number</p>
                            <p class="text-xs text-gray-900 font-semibold">{{ props.asset.asset_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Acquisition Date</p>
                            <p class="text-xs text-gray-900">{{ formatDate(props.asset.acquisition_date) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Fund Source</p>
                            <p class="text-xs text-gray-900">{{ props.asset.fund_source.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Region</p>
                            <p class="text-xs text-gray-900">{{ props.asset.region?.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">District</p>
                            <p class="text-xs text-gray-900">{{ props.asset.district?.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Asset Location</p>
                            <p class="text-xs text-gray-900">{{ props.asset.facility?.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Sub Location</p>
                            <p class="text-xs text-gray-900">{{ props.asset.sub_location?.name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Submission Information -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <h2 class="text-xs font-medium text-gray-900">
                        Submission Details
                    </h2>
                    <div class="space-y-2">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Submitted By</p>
                            <p class="text-xs text-gray-900">{{ props.asset.submitted_by.name }}</p>
                            <p class="text-xs text-gray-600">{{ props.asset.submitted_by.title }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Submitted At</p>
                            <p class="text-xs text-gray-900">{{ formatDateTime(props.asset.submitted_at) }}</p>
                        </div>
                        <div v-if="props.asset.reviewed_by">
                            <p class="text-xs font-medium text-gray-500">Reviewed By</p>
                            <p class="text-xs text-gray-900">{{ props.asset.reviewed_by.name }}</p>
                            <p class="text-xs text-gray-600">{{ formatDateTime(props.asset.reviewed_at) }}</p>
                        </div>
                        <div v-if="props.asset.approved_by">
                            <p class="text-xs font-medium text-gray-500">Approved By</p>
                            <p class="text-xs text-gray-900">{{ props.asset.approved_by.name }}</p>
                            <p class="text-xs text-gray-600">{{ formatDateTime(props.asset.approved_at) }}</p>
                        </div>
                        <div v-if="props.asset.rejected_by">
                            <p class="text-xs font-medium text-gray-500">Rejected By</p>
                            <p class="text-xs text-gray-900">{{ props.asset.rejected_by.name }}</p>
                            <p class="text-xs text-gray-600">{{ formatDateTime(props.asset.rejected_at) }}</p>
                            <p v-if="props.asset.rejection_reason" class="text-xs text-red-600 mt-1">{{ props.asset.rejection_reason }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Status Timeline -->
            <div v-if="props.asset.status === 'rejected'" class="flex flex-col items-center mb-6">
                <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10 bg-white border-red-500">
                    <img src="/assets/images/rejected.png" class="w-7 h-7" alt="Rejected" />
                </div>
                <h1 class="mt-3 text-2xl text-red-600 font-bold">Rejected</h1>
            </div>
            <div v-else class="col-span-2 mb-6">
                <div class="relative">
                    <!-- Timeline Track Background -->
                    <div class="absolute top-7 left-0 right-0 h-2 bg-gray-200 z-0"></div>

                    <!-- Timeline Progress -->
                    <div class="absolute top-7 left-0 h-2 bg-green-500 z-0 transition-all duration-500 ease-in-out"
                        :style="{
                            width: `${(assetStatusOrder.indexOf(props.asset.status) /
                                (assetStatusOrder.length - 1)) *
                                100
                                }%`,
                        }"></div>

                    <!-- Timeline Steps -->
                    <div class="relative flex justify-between">
                        <!-- Submitted -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                assetStatusOrder.indexOf(props.asset.status) >=
                                    assetStatusOrder.indexOf('pending_approval')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/pending.png" class="w-7 h-7" alt="Submitted" :class="assetStatusOrder.indexOf(props.asset.status) >=
                                    assetStatusOrder.indexOf('pending_approval')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="assetStatusOrder.indexOf(props.asset.status) >=
                                assetStatusOrder.indexOf('pending_approval')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Submitted</span>
                        </div>

                        <!-- Reviewed -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                assetStatusOrder.indexOf(props.asset.status) >=
                                    assetStatusOrder.indexOf('reviewed')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/review.png" class="w-7 h-7" alt="Reviewed" :class="assetStatusOrder.indexOf(props.asset.status) >=
                                    assetStatusOrder.indexOf('reviewed')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="assetStatusOrder.indexOf(props.asset.status) >=
                                assetStatusOrder.indexOf('reviewed')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Reviewed</span>
                        </div>

                        <!-- Approved -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                assetStatusOrder.indexOf(props.asset.status) >=
                                    assetStatusOrder.indexOf('approved')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/approved.png" class="w-7 h-7" alt="Approved" :class="assetStatusOrder.indexOf(props.asset.status) >=
                                    assetStatusOrder.indexOf('approved')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="assetStatusOrder.indexOf(props.asset.status) >=
                                assetStatusOrder.indexOf('approved')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Approved</span>
                        </div>


                    </div>
                </div>
            </div>

            <!-- Asset Items Table -->
            <h2 class="text-xs text-gray-900 mb-4 px-6">Asset Items</h2>
            <div class="px-6">
                <table class="w-full overflow-hidden text-sm text-left table-sm rounded-t-lg">
                    <thead>
                        <tr style="background-color: #F4F7FB;">
                            <th class="px-3 py-2 text-xs font-bold rounded-tl-lg" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Asset Tag</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Asset Name</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Serial Number</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Category</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Type</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Status</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Assignee</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Value</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="props.asset.asset_items.length === 0">
                            <td colspan="9" class="px-3 py-3 text-center text-gray-500 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                No asset items found.
                            </td>
                        </tr>
                        <tr v-for="item in props.asset.asset_items" :key="item.id" 
                            class="hover:bg-gray-50 transition-colors duration-150 border-b"
                            style="border-bottom: 1px solid #F4F7FB;">
                            <td class="px-3 py-3 text-xs text-gray-900">
                                <span class="font-semibold">{{ item.asset_tag }}</span>
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-900">{{ item.asset_name }}</td>
                            <td class="px-3 py-3 text-xs text-gray-900">{{ item.serial_number }}</td>
                            <td class="px-3 py-3 text-xs text-gray-900">{{ item.category.name }}</td>
                            <td class="px-3 py-3 text-xs text-gray-900">{{ item.type.name }}</td>
                            <td class="px-3 py-3 text-xs text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                    :class="getStatusClasses(item.status)">
                                    {{ item.status.replace('_', ' ').toUpperCase() }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-900">{{ item.assignee.name }}</td>
                            <td class="px-3 py-3 text-xs text-gray-900">${{ item.original_value }}</td>
                            <td class="px-3 py-3 text-xs text-center">
                                <button @click="openHistoryModal(item)" 
                                    class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors duration-150">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    History
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Asset Item History Modal -->
            <div v-if="showHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            Asset Item History - {{ selectedItem?.asset_tag }}
                        </h3>
                        <button @click="closeHistoryModal" class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[70vh]">
                        <div v-if="selectedItem?.asset_history && selectedItem.asset_history.length > 0" class="space-y-4">
                            <div v-for="(history, index) in selectedItem.asset_history" :key="index" 
                                class="border-l-4 border-blue-500 pl-4 py-2">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <span class="text-sm font-semibold text-gray-900">{{ history.action }}</span>
                                            <span class="text-xs text-gray-500">•</span>
                                            <span class="text-xs text-gray-500">{{ formatDateTime(history.performed_at) }}</span>
                                        </div>
                                        <p v-if="history.notes" class="text-sm text-gray-700 mb-2">{{ history.notes }}</p>
                                        <div class="flex items-center space-x-2 text-xs text-gray-500">
                                            <span>By: {{ history.performer?.name || 'Unknown' }}</span>
                                            <span v-if="history.action_type" class="px-2 py-1 bg-gray-100 rounded-full">
                                                {{ history.action_type }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No history found</h3>
                            <p class="mt-1 text-sm text-gray-500">This asset item doesn't have any history records yet.</p>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-200 flex justify-end">
                        <button @click="closeHistoryModal" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors duration-150">
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <div class="px-6">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <h3 class="text-lg font-semibold text-gray-800">Documents</h3>
                    </div>
                </div>
            </div>

            <!-- Asset Status Actions -->
            <div class="mt-8 mb-6 bg-white rounded-lg shadow-sm mx-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">
                    Asset Status Actions
                </h3>
                <div class="flex items-start mb-6">
                    <div class="flex flex-wrap items-center justify-center gap-4 px-1 py-2">
                        <!-- Review button - shows when actionable or completed -->
                        <div class="relative" v-if="props.asset.status === 'pending_approval' || assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf('reviewed')">
                            <div class="flex flex-col">
                                <button @click="changeAssetStatus('reviewed')" 
                                    :disabled="isReviewing || assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf('reviewed') || !(page.props.auth.can.asset_review || page.props.auth.isAdmin)"
                                    :class="[
                                        assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf('reviewed')
                                            ? 'bg-green-500'
                                            : !(page.props.auth.can.asset_review || page.props.auth.isAdmin)
                                                ? 'bg-gray-400 cursor-not-allowed'
                                                : 'bg-yellow-500 hover:bg-yellow-600'
                                    ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <svg v-if="isReviewing" class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <img v-else src="/assets/images/review.png" class="w-5 h-5 mr-2" alt="Review" />
                                    <span class="text-sm font-bold text-white">{{
                                        assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf("reviewed")
                                            ? "Reviewed"
                                            : isReviewing
                                                ? "Please Wait..."
                                                : "Review"
                                    }}</span>
                                </button>
                                <span v-show="props.asset?.reviewed_at" class="text-sm text-gray-600">
                                    {{ formatDateTime(props.asset?.reviewed_at) }}
                                </span>
                                <span v-show="props.asset?.reviewed_by" class="text-sm text-gray-600">
                                    By {{ props.asset?.reviewed_by?.name }}
                                </span>
                                <span v-show="!(page.props.auth.can.asset_review || page.props.auth.isAdmin)" class="text-xs text-gray-500 italic">
                                    Requires review permission
                                </span>
                            </div>
                            <div v-if="props.asset.status === 'pending_approval'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Approve button - shows when actionable or completed -->
                        <div class="relative" v-if="props.asset.status === 'reviewed' || assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf('approved')">
                            <div class="flex flex-col">
                                <button @click="changeAssetStatus('approved')" 
                                    :disabled="isApproving || assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf('approved') || !(page.props.auth.can.asset_approve || page.props.auth.isAdmin)"
                                    :class="[
                                        assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf('approved')
                                            ? 'bg-green-500'
                                            : !(page.props.auth.can.asset_approve || page.props.auth.isAdmin)
                                                ? 'bg-gray-400 cursor-not-allowed'
                                                : 'bg-yellow-500 hover:bg-yellow-600'
                                    ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <svg v-if="isApproving" class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <img v-else src="/assets/images/approved.png" class="w-5 h-5 mr-2" alt="Approve" />
                                    <span class="text-sm font-bold text-white">{{
                                        assetStatusOrder.indexOf(props.asset.status) >= assetStatusOrder.indexOf("approved")
                                            ? "Approved"
                                            : isApproving ? "Please Wait..." : "Approve"
                                    }}</span>
                                </button>
                                <span v-show="props.asset?.approved_at" class="text-sm text-gray-600">
                                    {{ formatDateTime(props.asset?.approved_at) }}
                                </span>
                                <span v-show="props.asset?.approved_by" class="text-sm text-gray-600">
                                    By {{ props.asset?.approved_by?.name }}
                                </span>
                                <span v-show="!(page.props.auth.can.asset_approve || page.props.auth.isAdmin)" class="text-xs text-gray-500 italic">
                                    Requires approval permission
                                </span>
                            </div>
                            <div v-if="props.asset.status === 'reviewed'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Reject button - only shows when actionable -->
                        <div class="relative" v-if="(props.asset.status === 'pending_approval' || props.asset.status === 'reviewed') && props.asset.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button @click="changeAssetStatus('rejected')" 
                                    :disabled="isRejecting || !(page.props.auth.can.asset_approve || page.props.auth.isAdmin)"
                                    :class="[
                                        !(page.props.auth.can.asset_approve || page.props.auth.isAdmin)
                                            ? 'bg-gray-400 cursor-not-allowed'
                                            : 'bg-red-500 hover:bg-red-600'
                                    ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <svg v-if="isRejecting" class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <img v-else src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Reject" />
                                    <span class="text-sm font-bold text-white">{{ isRejecting ? 'Please Wait...' : 'Reject' }}</span>
                                </button>
                                <span v-show="!(page.props.auth.can.asset_approve || page.props.auth.isAdmin)" class="text-xs text-gray-500 italic text-center">
                                    Requires approval permission
                                </span>
                            </div>
                        </div>

                        <!-- Rejected status display - shows when rejected -->
                        <div class="relative" v-if="props.asset.status === 'rejected'">
                            <div class="flex flex-col">
                                <button disabled
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] bg-red-500">
                                    <img src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Rejected" />
                                    <span class="text-sm font-bold text-white">Rejected</span>
                                </button>
                                <span v-show="props.asset?.rejected_at" class="text-sm text-gray-600">
                                    {{ formatDateTime(props.asset?.rejected_at) }}
                                </span>
                                <span v-show="props.asset?.rejected_by" class="text-sm text-gray-600">
                                    By {{ props.asset?.rejected_by?.name }}
                                </span>
                            </div>
                        </div>

                        <!-- Restore button - shows when rejected -->
                        <div class="relative" v-if="props.asset.status === 'rejected'">
                            <div class="flex flex-col">
                                <button @click="changeAssetStatus('pending_approval')" 
                                    :disabled="isRestoring || !(page.props.auth.can.asset_approve || page.props.auth.isAdmin)"
                                    :class="[
                                        !(page.props.auth.can.asset_approve || page.props.auth.isAdmin)
                                            ? 'bg-gray-400 cursor-not-allowed'
                                            : 'bg-green-500 hover:bg-green-600'
                                    ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <svg v-if="isRestoring" class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <img v-else src="/assets/images/restore.jpg" class="w-5 h-5 mr-2" alt="Restore" />
                                    <span class="text-sm font-bold text-white">{{ isRestoring ? 'Restoring...' : 'Restore' }}</span>
                                </button>
                                <span v-show="!(page.props.auth.can.asset_approve || page.props.auth.isAdmin)" class="text-xs text-gray-500 italic text-center">
                                    Requires approval permission
                                </span>
                            </div>
                            <div v-if="props.asset.status === 'rejected'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Documents Section -->
            <div class="px-6 py-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-blue-600">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Asset Documents</h3>
                                    <p class="text-sm text-gray-600">Manage and view asset-related documents</p>
                                </div>
                            </div>
                            <button v-if="page.props.auth.can.asset_edit || page.props.auth.isAdmin" @click="showUploadModal = true" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                Upload Document
                            </button>
                        </div>
                    </div>

                    <!-- Documents List -->
                    <div class="p-6">
                        <div v-if="!props.asset.documents || props.asset.documents.length === 0" class="text-center py-12">
                            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-8 h-8 text-gray-400">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No documents uploaded</h3>
                            <p class="text-gray-500 mb-4">Upload documents related to this asset to keep them organized and accessible.</p>
                            <button v-if="page.props.auth.can.asset_edit || page.props.auth.isAdmin" @click="showUploadModal = true" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                Upload First Document
                            </button>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div v-for="document in props.asset.documents" :key="document.id" 
                                class="bg-gray-50 rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center space-x-3">
                                        <!-- Document Type Icon -->
                                        <div class="p-2 rounded-lg" :class="getDocumentIconClass(document)">
                                            <svg v-if="document.mime_type && document.mime_type.startsWith('image/')" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else-if="(document.mime_type && document.mime_type === 'application/pdf') || (document.file_name && document.file_name.toLowerCase().endsWith('.pdf'))" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else-if="(document.mime_type && (document.mime_type.startsWith('text/') || document.mime_type.includes('document'))) || (document.file_name && document.file_name.toLowerCase().match(/\.(doc|docx|txt|rtf)$/))" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900 truncate">{{ document.file_name }}</h4>
                                            <p class="text-xs text-gray-500">{{ document.document_type || 'Document' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <button @click="previewDocument(document)" 
                                            class="p-1 text-gray-400 hover:text-blue-600 transition-colors" 
                                            title="Preview">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <a :href="route('asset.documents.download', document.id)" 
                                            class="p-1 text-gray-400 hover:text-green-600 transition-colors" 
                                            title="Download">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        <button v-if="page.props.auth.can.asset_edit || page.props.auth.isAdmin" @click="deleteDocument(document.id)" 
                                            class="p-1 text-gray-400 hover:text-red-600 transition-colors" 
                                            title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <span>{{ formatFileSize(document.file_size) }}</span>
                                        <span>{{ formatDate(document.created_at) }}</span>
                                    </div>
                                    <p v-if="document.description" class="text-xs text-gray-600 line-clamp-2">{{ document.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Upload Modal -->
            <div v-if="showUploadModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" @click="closeUploadModal">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4" @click.stop>
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Upload Document</h3>
                        <button @click="closeUploadModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form @submit.prevent="uploadDocument" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                            <input v-model="uploadForm.document_type" type="text" required 
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" 
                                placeholder="e.g., Invoice, Warranty, Manual, Contract, Receipt, etc." />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea v-model="uploadForm.description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Describe the document..."></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File</label>
                            <input type="file" @change="handleFileSelect" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" />
                            <p class="text-xs text-gray-500 mt-1">Supported formats: PDF, Word, Excel, PowerPoint, Images</p>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" @click="closeUploadModal" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Cancel
                            </button>
                            <button type="submit" :disabled="uploading" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50">
                                <svg v-if="uploading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ uploading ? 'Uploading...' : 'Upload' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Asset Maintenance Section -->
        <div class="bg-white shadow-sm rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Asset Maintenance</h3>
                    <button v-if="page.props.auth.can.asset_edit || page.props.auth.isAdmin" @click="showMaintenanceModal = true" 
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create Maintenance
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Loading State -->
                <div v-if="loadingMaintenance" class="text-center py-8">
                    <div class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading maintenance records...
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="!maintenanceRecords || maintenanceRecords.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No maintenance records</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating maintenance for this asset.</p>
                    <div class="mt-6">
                        <button v-if="page.props.auth.can.asset_edit || page.props.auth.isAdmin" @click="showMaintenanceModal = true" 
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-2 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Create Maintenance
                        </button>
                    </div>
                </div>

                <!-- Maintenance Records -->
                <div v-else class="space-y-4">
                    <div v-for="record in maintenanceRecords" :key="record.id" 
                         class="bg-gray-50 rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center space-x-3">
                                <!-- Maintenance Icon -->
                                <div class="p-2 rounded-lg bg-blue-100 text-blue-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900">{{ record.maintenance_type }}</h4>
                                    <p class="text-xs text-gray-500">Range: {{ record.maintenance_range > 0 ? `Every ${record.maintenance_range} Month(s)` : 'One-time' }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div v-if="record.completed_date" class="text-xs text-green-600 font-medium">
                                    Completed: {{ formatDate(record.completed_date) }}
                                </div>
                                <div v-else class="text-xs text-orange-600 font-medium">
                                    Pending
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-xs text-gray-600">
                            <div>
                                <span class="font-medium">Created:</span>
                                <p>{{ formatDate(record.created_at) }}</p>
                            </div>
                            <div>
                                <span class="font-medium">Created By:</span>
                                <p>{{ record.created_by?.name || 'Unknown' }}</p>
                            </div>
                            <div v-if="record.completed_date && record.maintenance_range > 0">
                                <span class="font-medium">Next Due:</span>
                                <p>{{ getNextMaintenanceDate(record) ? formatDate(getNextMaintenanceDate(record)) : 'N/A' }}</p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-3 pt-3 border-t border-gray-200 flex justify-end space-x-2">
                            <button v-if="!record.completed_date && (page.props.auth.can.asset_edit || page.props.auth.isAdmin)" 
                                    @click="markMaintenanceCompleted(record)"
                                    class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Mark Completed
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Preview Modal -->
        <div v-if="showPreviewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" @click="closePreviewModal">
            <div class="bg-white w-full h-full overflow-hidden" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">{{ selectedDocument?.file_name }}</h3>
                        <p class="text-sm text-gray-500">{{ selectedDocument?.document_type }} • {{ formatFileSize(selectedDocument?.file_size) }}</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a :href="route('asset.documents.download', selectedDocument?.id)" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download
                        </a>
                        <button @click="closePreviewModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="p-6 overflow-auto h-full">
                    <!-- Image Preview -->
                    <div v-if="isImageFile(selectedDocument)" class="text-center">
                        <img :src="route('asset.documents.preview', selectedDocument.id)" 
                             :alt="selectedDocument.file_name"
                             class="max-w-full max-h-[70vh] object-contain mx-auto rounded-lg shadow-lg" />
                    </div>
                    
                    <!-- PDF Preview -->
                    <div v-else-if="isPdfFile(selectedDocument)" class="w-full h-full">
                        <iframe :src="route('asset.documents.preview', selectedDocument.id)" 
                                class="w-full h-full border border-gray-200"
                                frameborder="0">
                            <p>Your browser does not support PDF preview. 
                                <a :href="route('asset.documents.download', selectedDocument.id)" class="text-blue-600 hover:text-blue-800 underline">
                                    Click here to download the PDF
                                </a>
                            </p>
                        </iframe>
                    </div>
                    

                    
                    <!-- Other Document Types -->
                    <div v-else class="bg-gray-50 p-4 rounded-lg text-center">
                        <div class="text-gray-500 mb-4">
                            <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-lg font-medium">{{ selectedDocument?.file_name }}</p>
                            <p class="text-sm">{{ selectedDocument?.document_type || 'Document' }}</p>
                        </div>
                        <div class="text-sm text-gray-600 space-y-2">
                            <p><strong>File Type:</strong> {{ selectedDocument?.mime_type || 'Unknown' }}</p>
                            <p><strong>File Size:</strong> {{ formatFileSize(selectedDocument?.file_size) }}</p>
                            <p v-if="selectedDocument?.description"><strong>Description:</strong> {{ selectedDocument.description }}</p>
                            <p class="text-gray-500 mt-4">This file type cannot be previewed directly. Please download it to view the contents.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Modal -->
        <div v-if="showMaintenanceModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" @click="closeMaintenanceModal">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden" @click.stop>
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">Create Maintenance</h3>
                    <button @click="closeMaintenanceModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form @submit.prevent="createMaintenance" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maintenance Type</label>
                            <input v-model="maintenanceForm.maintenance_type" type="text" required 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" 
                                   placeholder="e.g., Preventive, Corrective, Emergency, Predictive" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maintenance Range</label>
                            <select v-model="maintenanceForm.maintenance_range" required 
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">One-time</option>
                                <option value="1">Every 1 Month</option>
                                <option value="2">Every 2 Months</option>
                                <option value="3">Every 3 Months</option>
                                <option value="6">Every 6 Months</option>
                                <option value="12">Every 12 Months</option>
                            </select>
                        </div>
                    </div>
                    

                    

                    



                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" @click="closeMaintenanceModal" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                            Cancel
                        </button>
                        <button type="submit" :disabled="creatingMaintenance" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50">
                            <svg v-if="creatingMaintenance" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ creatingMaintenance ? 'Creating...' : 'Create Maintenance' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, usePage } from "@inertiajs/vue3";
import { ref, computed, onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import Swal from "sweetalert2";

const page = usePage();
const props = defineProps({
    asset: Object,
    pageTitle: String,
    pageDescription: String,
    error: String,
});

// Computed properties for page title and description
const computedPageTitle = computed(() => {
    return props.pageTitle || (props.asset ? `Asset Details - ${props.asset.asset_number}` : 'Asset Details');
});

const computedPageDescription = computed(() => {
    return props.pageDescription || (props.asset ? `View detailed information for asset: ${props.asset.asset_number}` : 'View detailed information for asset');
});

// Loading states for different actions
const isReviewing = ref(false);
const isApproving = ref(false);
const isRejecting = ref(false);
const isRestoring = ref(false);


// Status classes for styling
const statusClasses = {
    pending_approval: "bg-yellow-100 text-yellow-800 rounded-full font-bold",
    reviewed: "bg-green-100 text-green-800 rounded-full font-bold",
    approved: "bg-green-100 text-green-800 rounded-full font-bold",
    rejected: "bg-red-100 text-red-800 rounded-full font-bold",
    default: "bg-gray-100 text-gray-800 rounded-full font-bold",
};

// Asset status order for timeline
const assetStatusOrder = [
    "pending_approval",
    "reviewed",
    "approved",
];

// State for history modal
const showHistoryModal = ref(false);
const selectedItem = ref(null);

// State for document management
const showUploadModal = ref(false);
const showPreviewModal = ref(false);
const selectedDocument = ref(null);
const uploading = ref(false);
const uploadForm = ref({
    document_type: '',
    description: '',
    file: null
});

// State for maintenance management
const showMaintenanceModal = ref(false);
const loadingMaintenance = ref(false);
const creatingMaintenance = ref(false);
const maintenanceRecords = ref([]);
const maintenanceForm = ref({
    maintenance_type: '',
    maintenance_range: 0
});

// Helper function to format dates
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Helper function to format date and time
const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Helper function to format file size
const formatFileSize = (bytes) => {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Helper function to calculate next maintenance date
const getNextMaintenanceDate = (record) => {
    if (!record.completed_date || record.maintenance_range === 0) {
        return null;
    }
    
    try {
        const completedDate = new Date(record.completed_date);
        const nextDate = new Date(completedDate);
        nextDate.setMonth(nextDate.getMonth() + record.maintenance_range);
        return nextDate;
    } catch (error) {
        console.error('Error calculating next maintenance date:', error);
        return null;
    }
};

// Helper functions to detect file types
const isImageFile = (document) => {
    if (!document?.mime_type) return false;
    return document.mime_type.startsWith('image/');
};

const isPdfFile = (document) => {
    if (!document) return false;
    return (document.mime_type === 'application/pdf') || 
           (document.file_name && document.file_name.toLowerCase().endsWith('.pdf'));
};



// Get status classes for asset items
const getStatusClasses = (status) => {
    const statusClassMap = {
        'in_use': 'bg-green-100 text-green-800',
        'available': 'bg-blue-100 text-blue-800',
        'maintenance': 'bg-yellow-100 text-yellow-800',
        'retired': 'bg-gray-100 text-gray-800',
        'lost': 'bg-red-100 text-red-800',
    };
    return statusClassMap[status] || 'bg-gray-100 text-gray-800';
};

// Open history modal
const openHistoryModal = (item) => {
    selectedItem.value = item;
    showHistoryModal.value = true;
};

// Close history modal
const closeHistoryModal = () => {
    showHistoryModal.value = false;
    selectedItem.value = null;
};

// Document management functions
const handleFileSelect = (event) => {
    uploadForm.value.file = event.target.files[0];
};

const getDocumentIconClass = (document) => {
    const mimeType = document.mime_type || '';
    const fileName = document.file_name || '';
    
    if (mimeType.startsWith('image/')) {
        return 'bg-green-100 text-green-600';
    } else if (mimeType === 'application/pdf' || fileName.toLowerCase().endsWith('.pdf')) {
        return 'bg-red-100 text-red-600';
    } else if (mimeType.startsWith('text/') || 
               mimeType.includes('document') || 
               fileName.toLowerCase().match(/\.(doc|docx|txt|rtf)$/)) {
        return 'bg-blue-100 text-blue-600';
    } else {
        return 'bg-gray-100 text-gray-600';
    }
};

const uploadDocument = async () => {
    if (!uploadForm.value.file || !uploadForm.value.document_type) {
        return;
    }

    uploading.value = true;
    const formData = new FormData();
    formData.append('file', uploadForm.value.file);
    formData.append('document_type', uploadForm.value.document_type);
    formData.append('description', uploadForm.value.description);
    formData.append('asset_id', props.asset.id);

    try {
        const response = await axios.post(route('asset.documents.store', props.asset.id), formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        if (response.data.success) {
            // Refresh the page to show new document
            router.get(route('assets.show', props.asset.id));
        }
    } catch (error) {
        console.error('Upload error:', error);
        // Handle error (you can add a toast notification here)
    } finally {
        uploading.value = false;
        closeUploadModal();
    }
};

const previewDocument = (document) => {
    selectedDocument.value = document;
    showPreviewModal.value = true;
};

const closePreviewModal = () => {
    showPreviewModal.value = false;
    selectedDocument.value = null;
};

const deleteDocument = async (documentId) => {
    if (confirm('Are you sure you want to delete this document?')) {
        try {
            const response = await axios.delete(route('asset.documents.destroy', documentId));
            if (response.data.success) {
                // Refresh the page to update documents list
                router.get(route('assets.show', props.asset.id));
            }
        } catch (error) {
            console.error('Delete error:', error);
            // Handle error
        }
    }
};

// Maintenance functions
const loadMaintenanceRecords = async () => {
    if (!props.asset?.id) return;
    
    loadingMaintenance.value = true;
    try {
        const response = await axios.get(route('asset.maintenance.list', props.asset.id));
        if (response.data.success) {
            maintenanceRecords.value = response.data.maintenance;
        }
    } catch (error) {
        console.error('Failed to load maintenance records:', error);
    } finally {
        loadingMaintenance.value = false;
    }
};



const closeMaintenanceModal = () => {
    showMaintenanceModal.value = false;
    maintenanceForm.value = {
        maintenance_type: '',
        maintenance_range: 0
    };
};

const createMaintenance = async () => {
    creatingMaintenance.value = true;
    
    try {
        const formData = {
            maintenance_type: maintenanceForm.value.maintenance_type,
            maintenance_range: maintenanceForm.value.maintenance_range,
        };
        
        const response = await axios.post(route('asset.maintenance.store', props.asset.id), formData);
        
        if (response.data.success) {
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Maintenance record created successfully.',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Close modal and reload maintenance records
            closeMaintenanceModal();
            await loadMaintenanceRecords();
        }
    } catch (error) {
        console.error('Create maintenance error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to create maintenance record. Please try again.'
        });
    } finally {
        creatingMaintenance.value = false;
    }
};

const markMaintenanceCompleted = async (maintenance) => {
    try {
        const response = await axios.post(route('asset.maintenance.mark-completed', maintenance.id), {
            completed_date: new Date().toISOString().split('T')[0]
        });
        
        if (response.data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: response.data.message,
                confirmButtonText: 'OK'
            });
            
            await loadMaintenanceRecords();
        }
    } catch (error) {
        console.error('Error marking maintenance as completed:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to mark maintenance as completed. Please try again.',
            confirmButtonText: 'OK'
        });
    }
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    // Reset form
    uploadForm.value = {
        document_type: '',
        description: '',
        file: null
    };
    // Reset file input
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.value = '';
    }
};

// Review asset function with Swal confirmation
const reviewAsset = async () => {
    const result = await Swal.fire({
        title: 'Review Asset',
        text: 'Are you sure you want to review this asset?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Review it!',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            isReviewing.value = true;
            const response = await axios.post(route('assets.review', props.asset.id));
            
            if (response.data.success) {
                Swal.fire(
                    'Reviewed!',
                    'Asset has been reviewed successfully.',
                    'success'
                ).then(() => {
                    // Refresh the page to show updated status
                    router.get(route('assets.show', props.asset.id));
                });
            } else {
                Swal.fire(
                    'Error!',
                    response.data.message || 'Failed to review asset',
                    'error'
                );
            }
        } catch (error) {
            console.error('Review error:', error);
            Swal.fire(
                'Error!',
                error.response?.data?.message || 'Failed to review asset',
                'error'
            );
        } finally {
            isReviewing.value = false;
        }
    }
};

// Approve asset function with Swal confirmation
const approveAsset = async () => {
    const result = await Swal.fire({
        title: 'Approve Asset',
        text: 'Are you sure you want to approve this asset?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Approve it!',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            isApproving.value = true;
            const response = await axios.post(route('assets.approve', props.asset.id));
            
            if (response.data.success) {
                Swal.fire(
                    'Approved!',
                    'Asset has been approved successfully.',
                    'success'
                ).then(() => {
                    // Refresh the page to show updated status
                    router.get(route('assets.show', props.asset.id));
                });
            } else {
                Swal.fire(
                    'Error!',
                    response.data.message || 'Failed to approve asset',
                    'error'
                );
            }
        } catch (error) {
            console.error('Approve error:', error);
            Swal.fire(
                'Error!',
                error.response?.data?.message || 'Failed to approve asset',
                'error'
            );
        } finally {
            isApproving.value = false;
        }
    }
};

// Reject asset function with Swal confirmation
const rejectAsset = async () => {
    const { value: rejectionReason } = await Swal.fire({
        title: 'Reject Asset',
        text: 'Please provide a reason for rejection:',
        input: 'textarea',
        inputPlaceholder: 'Enter rejection reason...',
        inputAttributes: {
            'aria-label': 'Type your rejection reason here'
        },
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Reject Asset',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to provide a rejection reason!';
            }
        }
    });

    if (rejectionReason) {
        try {
            isRejecting.value = true;
            const response = await axios.post(route('assets.reject', props.asset.id), {
                rejection_reason: rejectionReason
            });
            
            if (response.data.success) {
                Swal.fire(
                    'Rejected!',
                    'Asset has been rejected successfully.',
                    'success'
                ).then(() => {
                    // Refresh the page to show updated status
                    router.get(route('assets.show', props.asset.id));
                });
            } else {
                Swal.fire(
                    'Error!',
                    response.data.message || 'Failed to reject asset',
                    'error'
                );
            }
        } catch (error) {
            console.error('Reject error:', error);
            Swal.fire(
                'Error!',
                error.response?.data?.message || 'Failed to reject asset',
                'error'
            );
        } finally {
            isRejecting.value = false;
        }
    }
};

// Restore asset function with Swal confirmation
const restoreAsset = async () => {
    const result = await Swal.fire({
        title: 'Restore Asset',
        text: 'Are you sure you want to restore this asset to pending approval?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Restore it!',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            isRestoring.value = true;
            const response = await axios.post(route('assets.restore', props.asset.id));
            
            if (response.data.success) {
                Swal.fire(
                    'Restored!',
                    'Asset has been restored successfully.',
                    'success'
                ).then(() => {
                    // Refresh the page to show updated status
                    router.get(route('assets.show', props.asset.id));
                });
            } else {
                Swal.fire(
                    'Error!',
                    response.data.message || 'Failed to restore asset',
                    'error'
                );
            }
        } catch (error) {
            console.error('Restore error:', error);
            Swal.fire(
                'Error!',
                error.response?.data?.message || 'Failed to restore asset',
                'error'
            );
        } finally {
            isRestoring.value = false;
        }
    }
};

// Activate asset function with Swal confirmation
const activateAsset = async () => {
    const result = await Swal.fire({
        title: 'Activate Asset',
        text: 'Are you sure you want to activate this asset and put it in use?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Activate it!',
        cancelButtonText: 'Cancel'
    });

    if (result.isConfirmed) {
        try {
            isActivating.value = true;
            // You might need to create a custom endpoint for this or use a general update method
            const response = await axios.patch(route('assets.update', props.asset.id), {
                status: 'in_use',
                activated_by: auth()?.id(),
                activated_at: new Date().toISOString()
            });
            
            if (response.data.success) {
                Swal.fire(
                    'Activated!',
                    'Asset has been activated successfully.',
                    'success'
                ).then(() => {
                    // Refresh the page to show updated status
                    router.get(route('assets.show', props.asset.id));
                });
            } else {
                Swal.fire(
                    'Error!',
                    response.data.message || 'Failed to activate asset',
                    'error'
                );
            }
        } catch (error) {
            console.error('Activate error:', error);
            Swal.fire(
                'Error!',
                error.response?.data?.message || 'Failed to activate asset',
                'error'
            );
        } finally {
            isActivating.value = false;
        }
    }
};

// Change asset status function - now properly calls the specific functions
const changeAssetStatus = (newStatus) => {
    switch (newStatus) {
        case 'reviewed':
            reviewAsset();
            break;
        case 'approved':
            approveAsset();
            break;
        case 'rejected':
            rejectAsset();
            break;
        case 'pending_approval':
            restoreAsset();
            break;

        default:
            console.log(`Unknown status: ${newStatus}`);
    }
};

// Load asset history and maintenance data when component mounts
onMounted(() => {
    if (props.asset?.asset_items) {
        props.asset.asset_items.forEach(item => {
            if (item.asset_history && item.asset_history.length > 0) {
                // Sort history by created_at in descending order
                item.asset_history.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            }
        });
    }
    
    // Load maintenance records
    loadMaintenanceRecords();
});
</script>