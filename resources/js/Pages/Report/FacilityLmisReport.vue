<template>
    <AuthenticatedLayout 
        title="LMIS Report"
        description="Manage and track facility LMIS reports including stock movements, adjustments, and closing balances"
        img="/assets/images/report.png"
    >
        <Head title="LMIS Report" />
        
        <div class="mb-[80px]">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="text-gray-900">
                    <!-- Header with Actions -->
                    <div class="flex justify-between items-center mb-4 px-4 py-3">
                        <h2 class="text-lg font-bold text-gray-900">LMIS Report</h2>
                        <div class="flex space-x-2">
                            <button 
                                @click="exportData" 
                                :disabled="isExporting || isLoading"
                                class="inline-flex items-center px-3 py-1 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                <svg v-if="!isExporting" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <svg v-else class="animate-spin w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isExporting ? 'Exporting...' : 'Export Excel' }}
                            </button>
                            <button
                                @click="generateReportsFromMovements"
                                :disabled="isGenerating || isLoading || !hasAllRequiredFilters"
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                            >
                                <svg v-if="!isGenerating" class="-ml-0.5 mr-1.5 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <svg v-else class="animate-spin -ml-0.5 mr-1.5 h-3 w-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isGenerating ? 'Creating...' : 'Create Report' }}
                            </button>


                        </div>
                    </div>

                    <!-- Filters: Region → District → Facility → Report Period (all required) -->
                    <div class="bg-gray-50 p-3 mx-4 rounded-lg mb-4">
                        <p class="text-xs text-amber-700 mb-3 font-medium">All filters are required. Select in order: Region → District → Facility → Report Period.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-3">
                            <!-- Region (required) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Region <span class="text-red-500">*</span></label>
                                <select
                                    v-model="region_id"
                                    class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <option :value="null">Select Region</option>
                                    <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </div>

                            <!-- District (required, depends on region) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">District <span class="text-red-500">*</span></label>
                                <select
                                    v-model="district_id"
                                    class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="!region_id"
                                >
                                    <option :value="null">Select District</option>
                                    <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </select>
                            </div>

                            <!-- Facility (required, depends on district) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Facility <span class="text-red-500">*</span></label>
                                <select
                                    v-model="facility_id"
                                    class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="!district_id || !facilities.length"
                                >
                                    <option :value="null">Select Facility</option>
                                    <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                            </div>

                            <!-- Report Period (required) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Report Period <span class="text-red-500">*</span></label>
                                <input
                                    v-model="month_year"
                                    type="month"
                                    class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                />
                            </div>

                            <!-- Product (optional filter when report loaded) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Item</label>
                                <Multiselect
                                    v-model="product_id"
                                    :options="products"
                                    :multiple="false"
                                    :close-on-select="true"
                                    :clear-on-select="false"
                                    :preserve-search="true"
                                    placeholder="Select item"
                                    label="name"
                                    track-by="id"
                                    :preselect-first="false"
                                    :disabled="isLoading || !products.length"
                                    class="text-xs"
                                />
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Approval Workflow Header Bar -->
                <div v-if="reports && reports.id" class="mx-4 mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-700">Approval Workflow:</span>
                    <span
                        :class="{
                            'bg-amber-100 text-amber-800': reports.status === 'draft',
                            'bg-blue-100 text-blue-800': reports.status === 'submitted',
                            'bg-indigo-100 text-indigo-800': reports.status === 'reviewed',
                            'bg-green-100 text-green-800': reports.status === 'approved',
                            'bg-red-100 text-red-800': reports.status === 'rejected',
                        }"
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                    >
                        {{ formatStatus(reports.status) }}
                    </span>
                    <span v-if="reports.status === 'rejected' && reports.comments" class="ml-2 flex items-center gap-2">
                        <span class="text-sm text-gray-500">Rejection reason:</span>
                        <span class="text-sm text-gray-700">{{ reports.comments }}</span>
                    </span>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <button
                            v-if="reports.status === 'draft' && reports.items && reports.items.length > 0"
                            type="button"
                            class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                            :disabled="isSubmitting || workflowActionLoading"
                            @click="submitReport"
                        >
                            {{ isSubmitting ? 'Submitting...' : 'Submit for review' }}
                        </button>
                        <button
                            v-if="reports.status === 'submitted'"
                            type="button"
                            class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                            :disabled="workflowActionLoading"
                            @click="workflowAction('review')"
                        >
                            Mark under review
                        </button>
                        <button
                            v-if="reports.status === 'reviewed'"
                            type="button"
                            class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                            :disabled="workflowActionLoading"
                            @click="workflowAction('approve')"
                        >
                            Approve
                        </button>
                        <button
                            v-if="reports.status === 'submitted' || reports.status === 'reviewed'"
                            type="button"
                            class="rounded border border-red-600 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                            :disabled="workflowActionLoading"
                            @click="showRejectModal = true"
                        >
                            Reject
                        </button>
                    </div>
                </div>
                <!-- Reject reason modal -->
                <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showRejectModal = false">
                    <div class="w-full max-w-md rounded-lg bg-white p-4 shadow-xl">
                        <h3 class="text-lg font-semibold text-gray-900">Reject report</h3>
                        <p class="mt-1 text-sm text-gray-500">Optionally provide a reason for rejection (shown to the report owner).</p>
                        <textarea v-model="rejectReason" rows="3" class="mt-3 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" placeholder="Reason for rejection..."></textarea>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showRejectModal = false">Cancel</button>
                            <button type="button" class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700" :disabled="workflowActionLoading" @click="workflowAction('reject')">Reject report</button>
                        </div>
                    </div>
                </div>

                <!-- Report Header (facility_monthly_reports fields) -->
                <div v-if="reports && reports.id" class="mx-4 mb-6">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-gray-900 mb-3">
                                    LMIS Report
                                </h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 text-sm mb-4">
                                    <div>
                                        <span class="text-gray-600">ID:</span>
                                        <p class="font-medium text-gray-900">{{ reports.id }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Facility:</span>
                                        <p class="font-medium text-gray-900">{{ reports.facility?.name }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Report Period:</span>
                                        <p class="font-medium text-gray-900">{{ formatReportPeriod(reports.report_period) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Status:</span>
                                        <span :class="`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(reports.status)}`">
                                            {{ formatStatus(reports.status) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Total Items:</span>
                                        <p class="font-medium text-gray-900">{{ filteredItems.length || 0 }}</p>
                                    </div>
                                </div>
                                <div v-if="reports.comments" class="mb-3">
                                    <span class="text-gray-600">Comments:</span>
                                    <p class="font-medium text-gray-900 mt-0.5">{{ reports.comments }}</p>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs text-gray-600 border-t border-blue-200 pt-3 mt-3">
                                    <div v-if="reports.submitted_at">
                                        <span class="text-gray-500">Submitted:</span>
                                        <p class="font-medium text-gray-900">{{ formatDateTime(reports.submitted_at) }}{{ (reports.submitted_by || reports.submittedBy) ? ` by ${(reports.submitted_by || reports.submittedBy)?.name || '—'}` : '' }}</p>
                                    </div>
                                    <div v-if="reports.reviewed_at">
                                        <span class="text-gray-500">Reviewed:</span>
                                        <p class="font-medium text-gray-900">{{ formatDateTime(reports.reviewed_at) }}{{ (reports.reviewed_by || reports.reviewedBy) ? ` by ${(reports.reviewed_by || reports.reviewedBy)?.name || '—'}` : '' }}</p>
                                    </div>
                                    <div v-if="reports.rejected_at">
                                        <span class="text-gray-500">Rejected:</span>
                                        <p class="font-medium text-gray-900">{{ formatDateTime(reports.rejected_at) }}{{ (reports.rejected_by || reports.rejectedBy) ? ` by ${(reports.rejected_by || reports.rejectedBy)?.name || '—'}` : '' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button 
                                    @click="exportToExcel" 
                                    :disabled="isExporting"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                >
                                    <svg v-if="!isExporting" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ isExporting ? 'Exporting...' : 'Export Excel' }}
                                </button>
                                
                                <!-- Submit Report Button -->
                                <button 
                                    v-if="reports && reports.status === 'draft' && reports.items && reports.items.length > 0"
                                    @click="submitReport" 
                                    :disabled="isSubmitting || isLoading"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                >
                                    <svg v-if="!isSubmitting" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                    <svg v-else class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ isSubmitting ? 'Submitting...' : 'Submit Report' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Table -->
                <div v-if="reports && reports.items && filteredItems.length > 0" class="mx-4">
                    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Details</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opening Balance</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Received</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Issued</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Positive Adj.</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Negative Adj.</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closing Balance</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stockout Days</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AMC</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <div class="text-sm font-medium text-gray-900">{{ item.product?.name || 'N/A' }}</div>
                                                <div class="text-xs text-gray-500">
                                                    <span class="font-medium">Category:</span> {{ item.product?.category?.name || 'N/A' }}
                                                </div>
                                                <div v-if="item.product?.dosage" class="text-xs text-gray-500">
                                                    <span class="font-medium">Dosage:</span> {{ item.product.dosage.name }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ formatNumber(item.opening_balance) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-green-600">{{ formatNumber(item.stock_received) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-red-600">{{ formatNumber(item.stock_issued) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div v-if="reports.status === 'draft'" class="relative">
                                                <input 
                                                    v-model.number="item.positive_adjustments"
                                                    @input="saveFieldChange(item, 'positive_adjustments')"
                                                    type="number" 
                                                    step="0.01" 
                                                    min="0"
                                                    class="w-32 text-sm border-gray-300 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500 pr-8"
                                                />
                                                <div v-if="item.saving_positive" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                                    <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                                <div v-else-if="item.saved_positive" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                                    <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div v-else class="text-sm font-medium text-green-600">{{ formatNumber(item.positive_adjustments) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div v-if="reports.status === 'draft'" class="relative">
                                                <input 
                                                    v-model.number="item.negative_adjustments"
                                                    @input="saveFieldChange(item, 'negative_adjustments')"
                                                    type="number" 
                                                    step="0.01" 
                                                    min="0"
                                                    class="w-32 text-sm border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 pr-8"
                                                />
                                                <div v-if="item.saving_negative" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                                    <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                                <div v-else-if="item.saved_negative" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                                    <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div v-else class="text-sm font-medium text-red-600">{{ formatNumber(item.negative_adjustments) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-blue-600">{{ formatNumber(item.closing_balance) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div v-if="reports.status === 'draft'" class="relative">
                                                <input 
                                                    v-model.number="item.stockout_days"
                                                    @input="saveFieldChange(item, 'stockout_days')"
                                                    type="number" 
                                                    min="0"
                                                    class="w-24 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-8"
                                                />
                                                <div v-if="item.saving_stockout" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                                    <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                                <div v-else-if="item.saved_stockout" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                                    <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div v-else class="flex items-center">
                                                <span :class="`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${item.stockout_days > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}`">
                                                    {{ item.stockout_days || 0 }} days
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatNumber(item.amc) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- No Data Message -->
                <div v-else-if="hasAllRequiredFilters && reports === null" class="mx-4 py-12">
                    <div class="text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-lg font-medium text-gray-900 mb-2">No report found for {{ formatReportPeriod(month_year) }}</p>
                        <p class="text-sm text-gray-600">Try generating a new report for this period</p>
                    </div>
                </div>

                <!-- Required filters message -->
                <div v-else class="mx-4 py-12">
                    <div class="text-center">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Select required filters</h3>
                        <p class="text-gray-600 mb-4">Please select Region, District, Facility, and Report Period (Year + Month) to view LMIS reports.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";

import axios from 'axios';
import Swal from 'sweetalert2';
import { ref, reactive, computed, onMounted, watch } from 'vue'
import * as XLSX from 'xlsx'

// Debounce utility
function debounce(func, delay) {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}

const props = defineProps({
    reports: {
        type: Object,
        default: () => null
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    products: {
        type: Array,
        default: () => []
    },
    facilities: {
        type: Array,
        default: () => []
    },
    regions: {
        type: Array,
        default: () => []
    },
    districts: {
        type: Array,
        default: () => []
    },
    months: {
        type: Object,
        default: () => ({})
    },
    facilityType: {
        type: String,
        required: false
    }
})
    
const isLoading = ref(false)
const isGenerating = ref(false)
const isExporting = ref(false)
const isFiltering = ref(false)
const isSubmitting = ref(false)
const workflowActionLoading = ref(false)
const showRejectModal = ref(false)
const rejectReason = ref('')

const month_year = ref(props.filters?.month_year || '')
const region_id = ref(props.filters?.region_id ?? null)
const district_id = ref(props.filters?.district_id ?? null)
const facility_id = ref(props.filters?.facility_id ?? null)
const product_id = ref(null)

const filteredDistricts = computed(() => {
    const list = props.districts || [];
    if (!region_id.value) return [];
    const regionName = props.regions?.find(r => r.id == region_id.value)?.name;
    if (!regionName) return [];
    return list.filter(d => (d.region || '') === regionName);
});

const hasAllRequiredFilters = computed(() =>
    Boolean(region_id.value && district_id.value && facility_id.value && month_year.value)
);

// Initialize product filter from props
onMounted(() => {
    if (props.filters?.product_id && props.products?.length > 0) {
        const foundProduct = props.products.find(p => p.id == props.filters.product_id);
        if (foundProduct) {
            product_id.value = foundProduct;
        }
    }
});

// Computed property to filter items based on selected product
const filteredItems = computed(() => {
    if (!props.reports?.items) return [];
    
    if (!product_id.value) {
        return props.reports.items; // Show all items if no filter
    }
    const pid = product_id.value?.id ?? product_id.value;
    return props.reports.items.filter(item => item.product_id == pid);
});

// Reset district when region changes; reset facility when district changes
watch(region_id, () => {
    district_id.value = null;
    facility_id.value = null;
});
watch(district_id, () => {
    facility_id.value = null;
});

watch([
    () => month_year.value,
    () => region_id.value,
    () => district_id.value,
    () => facility_id.value
], () => {
    applyFilters();
});

function applyFilters() {
    const query = {};
    if (region_id.value) query.region_id = region_id.value;
    if (district_id.value) query.district_id = district_id.value;
    if (facility_id.value) query.facility_id = facility_id.value;
    if (month_year.value) query.month_year = month_year.value;
    if (product_id.value) query.product_id = product_id.value?.id ?? product_id.value;
    router.get(route('reports.facility-lmis-report'), query, {
        preserveState: true,
        preserveScroll: true,
        only: ['reports', 'facilities', 'products', 'filters']
    });
}

function formatNumber(value) {
    return parseFloat(value || 0).toLocaleString();
}

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    return isNaN(d.getTime()) ? '—' : d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
}

function formatStatus(status) {
    const statusMap = {
        'draft': 'Draft',
        'submitted': 'Submitted',
        'reviewed': 'Reviewed',
        'approved': 'Approved',
        'rejected': 'Rejected'
    };
    return statusMap[status] || status;
}

function getStatusClass(status) {
    const classMap = {
        'draft': 'bg-gray-100 text-gray-800',
        'submitted': 'bg-yellow-100 text-yellow-800',
        'reviewed': 'bg-blue-100 text-blue-800',
        'approved': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800'
    };
    return classMap[status] || 'bg-gray-100 text-gray-800';
}

function getMonthName(month) {
    const monthNames = {
        1: 'January', 2: 'February', 3: 'March', 4: 'April',
        5: 'May', 6: 'June', 7: 'July', 8: 'August',
        9: 'September', 10: 'October', 11: 'November', 12: 'December'
    };
    return monthNames[month] || month;
}

function formatReportPeriod(period) {
    if (!period) return 'N/A';
    const [year, month] = period.split('-');
    const monthNames = {
        '01': 'January', '02': 'February', '03': 'March', '04': 'April',
        '05': 'May', '06': 'June', '07': 'July', '08': 'August',
        '09': 'September', '10': 'October', '11': 'November', '12': 'December'
    };
    return `${monthNames[month] || month} ${year}`;
}

async function exportToExcel() {
    if (!props.reports || !props.reports.items) {
        Swal.fire({
            title: 'No Data',
            text: 'No report data available to export',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }

    isExporting.value = true;

    try {
        // Prepare data for Excel using filtered items
        const itemsToExport = product_id.value ? filteredItems.value : props.reports.items;
        
        // Create workbook
        const wb = XLSX.utils.book_new();
        
        // Create header information first (facility_monthly_reports fields)
        const headerInfo = [
            ['LMIS Report'],
            [''],
            ['Report ID:', props.reports.id || 'N/A'],
            ['Facility:', props.reports.facility?.name || 'N/A'],
            ['Report Period:', formatReportPeriod(props.reports.report_period)],
            ['Status:', formatStatus(props.reports.status)],
            ...(props.reports.comments ? [['Comments:', props.reports.comments]] : []),
            ['Submitted:', props.reports.submitted_at ? `${formatDateTime(props.reports.submitted_at)}${(props.reports.submitted_by || props.reports.submittedBy) ? ` by ${(props.reports.submitted_by || props.reports.submittedBy)?.name || ''}` : ''}` : '—'],
            ['Total Items:', itemsToExport.length || 0],
            [''],
            [''], // Extra spacing before data
            // Column headers (facility_monthly_report_items fields)
            ['S/N', 'Product Name', 'Category', 'Dosage Form', 'Opening Balance', 'Stock Received', 'Stock Issued', 'Positive Adjustments', 'Negative Adjustments', 'Closing Balance', 'Stockout Days', 'AMC']
        ];
        
        // Prepare data rows (facility_monthly_report_items fields)
        const dataRows = itemsToExport.map((item, index) => [
            index + 1, // S/N
            item.product?.name || 'N/A',
            item.product?.category?.name || 'N/A',
            item.product?.dosage?.name || 'N/A',
            parseFloat(item.opening_balance || 0),
            parseFloat(item.stock_received || 0),
            parseFloat(item.stock_issued || 0),
            parseFloat(item.positive_adjustments || 0),
            parseFloat(item.negative_adjustments || 0),
            parseFloat(item.closing_balance || 0),
            parseInt(item.stockout_days || 0),
            parseFloat(item.amc || 0)
        ]);
        
        // Combine header and data
        const allData = [...headerInfo, ...dataRows];
        
        // Create worksheet from the combined data
        const ws = XLSX.utils.aoa_to_sheet(allData);

        // Set column widths
        const colWidths = [
            { wch: 6 },  // S/N
            { wch: 40 }, // Product Name
            { wch: 20 }, // Category
            { wch: 15 }, // Dosage Form
            { wch: 15 }, // Opening Balance
            { wch: 15 }, // Stock Received
            { wch: 15 }, // Stock Issued
            { wch: 20 }, // Positive Adjustments
            { wch: 20 }, // Negative Adjustments
            { wch: 15 }, // Closing Balance
            { wch: 12 }, // Stockout Days
            { wch: 12 }  // AMC
        ];
        ws['!cols'] = colWidths;

        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(wb, ws, 'LMIS Report');

        // Generate filename
        const facilityName = props.reports.facility?.name?.replace(/[^a-zA-Z0-9]/g, '_') || 'Facility';
        const period = props.reports.report_period?.replace('-', '_') || 'Report';
        const filename = `LMIS_Report_${facilityName}_${period}.xlsx`;

        // Save the file
        XLSX.writeFile(wb, filename);

        Swal.fire({
            title: 'Export Successful!',
            text: `Report exported as ${filename}`,
            icon: 'success',
            confirmButtonText: 'OK'
        });

    } catch (error) {
        console.error('Export error:', error);
        Swal.fire({
            title: 'Export Failed',
            text: 'Failed to export report. Please try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
    } finally {
        isExporting.value = false;
    }
}

// Use exportToExcel function instead of duplicate exportData
const exportData = exportToExcel;

async function submitReport() {
    if (!hasAllRequiredFilters.value) {
        Swal.fire({
            title: 'Missing Information',
            text: 'Please select Region, District, Facility, and Report Period first',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Confirm submission
    const result = await Swal.fire({
        title: 'Submit Report for Approval?',
        text: 'Once submitted, you will not be able to edit the report until it is reviewed. Are you sure you want to continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Submit',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280'
    });

    if (!result.isConfirmed) return;

    isSubmitting.value = true;
    
    try {
        const [year, month] = month_year.value.split('-');
        
        const response = await axios.post(route('reports.facility-lmis-report.submit'), {
            year: parseInt(year),
            month: parseInt(month),
            facility_id: facility_id.value
        });

        Swal.fire({
            title: 'Report Submitted Successfully!',
            text: 'The LMIS report has been submitted for review and approval.',
            icon: 'success',
            confirmButtonText: 'OK'
        });

        // Refresh the page to show updated status
        router.reload({ only: ['reports'] });

    } catch (error) {
        console.error('Submit error:', error);
        
        let errorMessage = 'Failed to submit report. Please try again.';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        }
        
        Swal.fire({
            title: 'Submission Failed',
            text: errorMessage,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    } finally {
        isSubmitting.value = false;
    }
}

async function workflowAction(action) {
    if (!hasAllRequiredFilters.value) return;
    if (action === 'reject') showRejectModal.value = false;
    workflowActionLoading.value = true;
    try {
        const [year, month] = month_year.value.split('-');
        let url, payload = { year: parseInt(year), month: parseInt(month), facility_id: facility_id.value };
        if (action === 'review') url = route('reports.facility-lmis-report.review');
        else if (action === 'approve') url = route('reports.facility-lmis-report.approve');
        else if (action === 'reject') { url = route('reports.facility-lmis-report.reject'); payload.rejection_reason = rejectReason.value; }
        else return;
        const res = await axios.post(url, payload);
        if (res.data?.success) {
            Swal.fire({ title: 'Success', text: res.data.message || 'Action completed.', icon: 'success', confirmButtonText: 'OK' });
            router.reload({ only: ['reports'] });
        } else throw new Error(res.data?.message || 'Action failed');
    } catch (err) {
        Swal.fire({ title: 'Error', text: err.response?.data?.message || err.message || 'Action failed', icon: 'error', confirmButtonText: 'OK' });
    } finally {
        workflowActionLoading.value = false;
        rejectReason.value = '';
    }
}

function calculateClosingBalance(item) {
    try {
        const opening = parseFloat(item.opening_balance) || 0;
        const received = parseFloat(item.stock_received) || 0;
        const issued = parseFloat(item.stock_issued) || 0;
        const positiveAdj = parseFloat(item.positive_adjustments) || 0;
        const negativeAdj = parseFloat(item.negative_adjustments) || 0;
        
        // Calculate closing balance using LMIS formula
        item.closing_balance = opening + received - issued + positiveAdj - negativeAdj;
    } catch (error) {
        console.error('Calculation error:', error);
    }
}

// Create debounced version of the save function
const debouncedSaveFieldChange = debounce(async (item, fieldName) => {
    // Calculate closing balance first
    calculateClosingBalance(item);
    
    // Set loading state for the specific field
    const loadingKey = `saving_${fieldName === 'positive_adjustments' ? 'positive' : fieldName === 'negative_adjustments' ? 'negative' : 'stockout'}`;
    const savedKey = `saved_${fieldName === 'positive_adjustments' ? 'positive' : fieldName === 'negative_adjustments' ? 'negative' : 'stockout'}`;
    
    item[loadingKey] = true;
    item[savedKey] = false;

    try {
        // Prepare data for this specific item
        const itemData = {
            id: item.id,
            product_id: item.product_id,
            opening_balance: parseFloat(item.opening_balance) || 0,
            stock_received: parseFloat(item.stock_received) || 0,
            stock_issued: parseFloat(item.stock_issued) || 0,
            positive_adjustments: parseFloat(item.positive_adjustments) || 0,
            negative_adjustments: parseFloat(item.negative_adjustments) || 0,
            stockout_days: parseInt(item.stockout_days) || 0,
            closing_balance: parseFloat(item.closing_balance) || 0,
        };

        const [year, month] = month_year.value.split('-');
        
        const requestData = {
            year: parseInt(year),
            month: parseInt(month),
            facility_id: facility_id.value,
            reports: [itemData], // Save only this item
        };
        
        console.log('Saving field:', fieldName, 'Data:', requestData);
        
        await axios.post(route('reports.facility-lmis-report.store'), requestData, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        // Show success indicator
        item[loadingKey] = false;
        item[savedKey] = true;
        
        // Hide success indicator after 2 seconds
        setTimeout(() => {
            item[savedKey] = false;
        }, 2000);

    } catch (error) {
        console.error('Save error:', error);
        item[loadingKey] = false;
        
        // Get more detailed error message
        let errorMessage = `Failed to save ${fieldName.replace('_', ' ')}.`;
        if (error.response && error.response.data) {
            if (error.response.data.message) {
                errorMessage = error.response.data.message;
            } else if (error.response.data.errors) {
                errorMessage = Object.values(error.response.data.errors).flat().join(', ');
            }
        }
        
        // Show error indicator briefly
        Swal.fire({
            title: 'Auto-save Failed',
            text: errorMessage,
            icon: 'error',
            timer: 5000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}, 500);

function saveFieldChange(item, fieldName) {
    debouncedSaveFieldChange(item, fieldName);
}

async function generateReportsFromMovements() {
    isGenerating.value = true;
    try {
        if (!hasAllRequiredFilters.value) {
            Swal.fire({
                title: 'Missing Information',
                text: 'Please select Region, District, Facility, and Report Period first',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const [year, month] = month_year.value.split('-');
        const response = await axios.post(route('reports.facility-lmis-report.generate-from-movements'), {
            year: parseInt(year),
            month: parseInt(month),
            facility_id: facility_id.value
        });
        
        Swal.fire({
            title: 'Report Created Successfully!',
            html: `
                <div class="text-left">
                    <p><strong>LMIS report created from facility movements</strong></p>
                    <div class="mt-2 text-sm text-gray-600">
                        <p>• ${response.data.data.created_count} new items created</p>
                        <p>• ${response.data.data.updated_count} existing items updated</p>
                        <p>• ${response.data.data.total_products} total products processed</p>
                        <p>• ${response.data.data.movements_processed} movement records analyzed</p>
                    </div>
                </div>
            `,
            icon: 'success',
            showCancelButton: true,
            confirmButtonText: 'View Report',
            cancelButtonText: 'Edit Report'
        }).then((result) => {
            const query = {
                region_id: region_id.value,
                district_id: district_id.value,
                facility_id: facility_id.value,
                month_year: `${year}-${month.toString().padStart(2, '0')}`,
            };
            if (result.isConfirmed) {
                router.get(route('reports.facility-lmis-report'), query);
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                router.get(route('reports.facility-lmis-report.create'), {
                    year: parseInt(year),
                    month: parseInt(month),
                    facility_id: facility_id.value
                });
            }
        });
        
    } catch (error) {
        console.error('Generate reports from movements error:', error);
        
        let errorMessage = error.response?.data?.message || 'Failed to generate reports from movements. Please try again.';
        let errorTitle = 'Generation Failed';
        let errorIcon = 'error';
        
        // Check if it's an existing report error
        if (error.response?.data?.message && error.response.data.message.includes('already exists')) {
            errorTitle = 'Report Already Exists';
            errorIcon = 'warning';
        }
        
        Swal.fire({
            title: errorTitle,
            text: errorMessage,
            icon: errorIcon,
            confirmButtonText: 'OK'
        });
    } finally {
        isGenerating.value = false;
    }
}

</script>