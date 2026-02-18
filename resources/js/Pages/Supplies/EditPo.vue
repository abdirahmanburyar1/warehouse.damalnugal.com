<template>
    <Head title="Edit Purchase Order" />
    <AuthenticatedLayout
        title="Edit Purchase Order"
        description="Update purchase order details and manage approval workflow"
        img="/assets/images/supplies.png"
    >
        <div v-if="props.error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-red-800">{{ props.error }}</span>
            </div>
        </div>
        
        <div v-else class="space-y-6">
            <!-- Back Navigation -->
            <Link
                :href="route('supplies.index')"
                class="inline-flex items-center text-gray-600 hover:text-indigo-600 transition-colors duration-200 group"
            >
                <svg
                    class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform duration-200"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Purchase Orders
            </Link>

            <!-- Header Section -->
            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-2xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            Edit Purchase Order
                            <span v-if="props.po?.po_number" class="text-indigo-600">#{{ props.po.po_number }}</span>
                        </h1>
                        <p class="text-gray-600 mt-1">Update order details and manage approval workflow</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div v-if="form.approved_at" class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                            Approved
                        </div>
                        <div v-else-if="form.rejected_at" class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                            Rejected
                        </div>
                        <div v-else-if="form.reviewed_at" class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                            Reviewed
                        </div>
                        <div v-else class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                            Draft
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supplier Selection -->
            <div class="bg-white rounded-xl p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Supplier Information
                </h2>
                
                <!-- Supplier Selection Row -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Select Supplier
                    </label>
                    <div class="max-w-md">
                        <Multiselect
                            v-model="form.supplier"
                            :value="form.supplier_id"
                            :options="props.suppliers"
                            :searchable="true"
                            :close-on-select="true"
                            :show-labels="false"
                            :allow-empty="true"
                            placeholder="Search and select supplier..."
                            track-by="id"
                            label="name"
                            class="multiselect-modern"
                            @select="handleSupplierSelect"
                            :disabled="form.approved_at"
                        />
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="animate-pulse space-y-4">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                    </div>
                    <div class="animate-pulse space-y-4">
                        <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                    </div>
                </div>

                <!-- Supplier Details -->
                <div v-else-if="form.supplier" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Company Details</h3>
                            <p class="text-base font-semibold text-gray-900 mt-1">{{ form.supplier?.name }}</p>
                            <p class="text-sm text-gray-600">{{ form.supplier?.contact_person }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Contact Information</h3>
                            <div class="mt-2 space-y-2">
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ form.supplier?.email }}
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    {{ form.supplier?.phone }}
                                </div>
                                <div class="flex items-start text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ form.supplier?.address }}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Order Information</h3>
                            <div class="mt-3 space-y-3">
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-600 mr-2">P.O Number:</span>
                                    <span class="text-sm font-semibold text-gray-900">{{ form.po_number }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-600 mr-2">Reference No:</span>
                                    <input
                                        type="text"
                                        v-model="form.original_po_no"
                                        :disabled="form.approved_at"
                                        class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                        placeholder="Enter reference"
                                    />
                                </div>
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-600 mr-2">Date:</span>
                                    <input
                                        type="date"
                                        v-model="form.po_date"
                                        :disabled="form.approved_at"
                                        class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="bg-white ">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                Order Items
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Manage items in your purchase order</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="text-sm text-gray-600">
                                Total: <span class="font-semibold text-indigo-600">{{ formatCurrency(subtotal) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200" style="width: 350px;">Item</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Quantity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200 w-[300px]">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Unit Cost</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Total</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(item, index) in form.items" :key="index" 
                                class="hover:bg-gray-50 transition-colors duration-150"
                                :class="{ 'opacity-75': form.approved_at }">
                                <td class="px-4 py-3 text-sm text-gray-500 border-r border-gray-200">{{ index + 1 }}</td>
                                <td class="px-4 py-3 border-r border-gray-200" style="width: 350px;">
                                    <Multiselect 
                                        v-model="item.product" 
                                        :value="item.product_id"
                                        :options="props.products"
                                        :searchable="true" 
                                        :close-on-select="true" 
                                        :show-labels="false" 
                                        required
                                        :allow-empty="true" 
                                        placeholder="Search and select item..." 
                                        track-by="id" 
                                        label="name"
                                        class="multiselect-modern"
                                        @select="hadleProductSelect(index, $event)"
                                        :disabled="form.approved_at"
                                    />
                                    <div v-if="item.edited" class="text-xs mt-1 text-red-500 flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                        Edited by: {{ item.edited.name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    <input 
                                        type="number" 
                                        v-model="item.quantity" 
                                        @input="calculateTotal(index)" 
                                        required
                                        :disabled="form.approved_at"
                                        class="w-full text-sm border-gray-300 rounded-lg focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition-all duration-200 px-3 py-2 disabled:bg-gray-50 disabled:cursor-not-allowed"
                                        min="1" 
                                        placeholder="Qty"
                                    />
                                    <div v-if="item.original_quantity" class="text-xs mt-1 text-red-500 line-through">
                                        Original: {{ item.original_quantity }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200 w-20">
                                    <SearchableSelect
                                        :model-value="getUomModel(item.uom)"
                                        :options="uomOptions"
                                        placeholder="Search and select UoM..."
                                        options-max-height-class="max-h-24"
                                        keep-first-option-in-filter
                                        :option-id-to-hide-from-input="UOM_ADD_OPTION_ID"
                                        @focus="loadUomList"
                                        @update:model-value="(val) => onUomSelect(index, val)"
                                    />
                                    <div v-if="item.original_uom" class="text-xs mt-1 text-red-500 line-through">
                                        Original: {{ item.original_uom }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    <input 
                                        type="number" 
                                        v-model="item.unit_cost" 
                                        @input="calculateTotal(index)" 
                                        required
                                        :disabled="form.approved_at"
                                        class="w-full text-sm border-gray-300 rounded-lg focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition-all duration-200 px-3 py-2 disabled:bg-gray-50 disabled:cursor-not-allowed"
                                        step="0.01" 
                                        min="0" 
                                        placeholder="0.00"
                                    />
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ formatCurrency(item.total_cost) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button 
                                        v-if="!form.approved_at" 
                                        type="button" 
                                        @click="removeItem(index)"
                                        class="text-gray-400 hover:text-red-600 transition-colors duration-200 p-1 rounded-lg hover:bg-red-50"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Empty State -->
                            <tr v-if="form.items.length === 0">
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No items added</h3>
                                        <p class="mt-1 text-sm text-gray-500">Get started by adding items to your purchase order.</p>
                                        <div class="mt-6">
                                            <button 
                                                type="button" 
                                                @click="addItem"
                                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                            >
                                                <PlusIcon class="h-5 w-5 mr-2" />
                                                Add Item
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="flex space-x-3">
                            <button 
                                v-if="!form.approved_at" 
                                type="button" 
                                @click="addItem"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                            >
                                <PlusIcon class="h-5 w-5 mr-2 text-gray-400" />
                                Add Item
                            </button>
                            <button 
                                v-if="!form.approved_at" 
                                type="button" 
                                @click="form.items = []"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                            >
                                <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Clear All
                            </button>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                             <div class="flex justify-between items-center">
                                 <span class="text-lg font-bold text-indigo-600">{{ formatCurrency(subtotal) }}</span>
                             </div>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Additional Notes
                </h3>
                <textarea 
                    v-model="form.notes" 
                    :disabled="form.approved_at"
                    rows="3" 
                    class="w-full border-gray-300 rounded-lg focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition-all duration-200 resize-none"
                    placeholder="Enter any additional notes or special instructions for this purchase order..."
                ></textarea>
            </div>

            <!-- Purchase Order Status Actions -->
            <div class="mt-8 mb-6 px-6 py-6 bg-white rounded-lg shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">
                    Purchase Order Status Actions
                </h3>
                <div class="flex justify-start items-center mb-6">
                    <!-- {{ $page.props.auth.can }} -->
                    <!-- Status Action Buttons -->
                    <div class="flex flex-wrap items-center justify-start gap-4">
                        <!-- Review button -->
                        <div class="relative">
                            <div class="flex flex-col">
                                <button @click="reviewPO"
                                    :class="[
                                        form.reviewed_at
                                            ? 'bg-green-500'
                                            : form.approved_at || form.rejected_at
                                            ? 'bg-gray-300 cursor-not-allowed'
                                            : 'bg-yellow-500 hover:bg-yellow-600'
                                    ]"
                                    :disabled="
                                        isProcessing.review ||
                                        isProcessing.approve ||
                                        isProcessing.reject ||
                                        form.reviewed_at ||
                                        form.approved_at ||
                                        form.rejected_at ||
                                        !$page.props.auth.can.purchase_order_review
                                    "
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-50 disabled:cursor-not-allowed">
                                    <img src="/assets/images/review.png" class="w-5 h-5 mr-2" alt="Review" />
                                    <span class="text-sm font-bold text-white">{{ form.reviewed_at ? 'Reviewed' : 'Review' }}</span>
                                </button>
                                <div v-if="form.reviewed_at" class="mt-2 text-center">
                                    <div class="text-xs text-gray-600">{{ moment(form.reviewed_at).format('DD/MM/YYYY HH:mm') }}</div>
                                    <div class="text-xs font-medium text-gray-700">By {{ form.reviewed_by?.name }}</div>
                                </div>
                            </div>
                            <div v-if="!form.reviewed_at && !form.approved_at && !form.rejected_at"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Approve button -->
                        <div class="relative">
                            <div class="flex flex-col">
                                <button @click="approvePO"
                                    :class="[
                                        form.approved_at
                                            ? 'bg-green-500'
                                            : !form.reviewed_at || form.rejected_at
                                            ? 'bg-gray-300 cursor-not-allowed'
                                            : 'bg-green-500 hover:bg-green-600'
                                    ]"
                                    :disabled="form.approved_at || isProcessing.approve || !form.reviewed_at || !$page.props.auth.can.purchase_order_approve"
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-50 disabled:cursor-not-allowed">
                                    <img src="/assets/images/approved.png" class="w-5 h-5 mr-2" alt="Approve" />
                                    <span class="text-sm font-bold text-white">{{ form.approved_at ? 'Approved' : 'Approve' }}</span>
                                </button>
                                <div v-if="form.approved_at" class="mt-2 text-center">
                                    <div class="text-xs text-gray-600">{{ moment(form.approved_at).format('DD/MM/YYYY HH:mm') }}</div>
                                    <div class="text-xs font-medium text-gray-700">By {{ form.approved_by?.name }}</div>
                                </div>
                            </div>
                            <div v-if="form.reviewed_at && !form.approved_at && !form.rejected_at"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Reject button -->
                        <div class="relative" v-if="!form.approved_at">
                            <div class="flex flex-col">
                                <button @click="rejectPO"
                                    :class="[
                                        form.rejected_at
                                            ? 'bg-red-500'
                                            : !form.reviewed_at
                                            ? 'bg-gray-300 cursor-not-allowed'
                                            : 'bg-red-500 hover:bg-red-600'
                                    ]"
                                    :disabled="
                                        isProcessing.review ||
                                        isProcessing.approve ||
                                        isProcessing.reject ||
                                        !form.reviewed_at ||
                                        form.rejected_at ||
                                        form.approved_at ||
                                        !$page.props.auth.can.purchase_order_reject
                                    "
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-50 disabled:cursor-not-allowed">
                                    <img src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Reject" />
                                    <span class="text-sm font-bold text-white">{{ form.rejected_at ? 'Rejected' : 'Reject' }}</span>
                                </button>
                                <div v-if="form.rejected_at" class="mt-2 text-center">
                                    <div class="text-xs text-gray-600">{{ moment(form.rejected_at).format('DD/MM/YYYY HH:mm') }}</div>
                                    <div class="text-xs font-medium text-gray-700">By {{ form.rejected_by?.name }}</div>
                                </div>
                            </div>
                            <div v-if="form.reviewed_at && !form.approved_at && !form.rejected_at"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3">
                    <button 
                        type="button" 
                        @click="router.visit(route('supplies.index'))" 
                        :disabled="isSubmitting"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                    >
                        Exit
                    </button>
                    <button 
                        v-if="!form.approved_at" 
                        type="button" 
                        @click="submitForm" 
                        :disabled="isSubmitting || !$page.props.auth.can.purchase_order_edit"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isSubmitting ? "Updating..." : "Update Changes" }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- UOM Creation Modal -->
    <div v-if="showUomModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Create New UOM</h3>
                    <button @click="closeUomModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form @submit.prevent="createUom" class="space-y-4">
                    <div>
                        <label for="uom-name" class="block text-sm font-medium text-gray-700 mb-2">UOM Name</label>
                        <input
                            id="uom-name"
                            type="text"
                            v-model="uomForm.name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200"
                            placeholder="Enter UOM name"
                            required
                            autofocus
                        />
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            @click="closeUomModal"
                            :disabled="isUomSubmitting"
                            class="px-4 py-2 border border-gray-300 rounded-lg font-medium text-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isUomSubmitting"
                            class="px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                        >
                            <svg v-if="isUomSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isUomSubmitting ? 'Creating...' : 'Create UOM' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import axios from "axios";
import { PlusIcon, TrashIcon, CheckCircleIcon, ArrowPathIcon } from "@heroicons/vue/24/outline";
import moment from "moment";
import Swal from "sweetalert2";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import SearchableSelect from "@/Components/SearchableSelect.vue";

import { useToast } from "vue-toastification";

const toast = useToast();

const props = defineProps({
    products: Array,
    suppliers: Array,
    po: Object,
    users: Array,
    error: String,
    uom: Array
});

// UOM list loaded when UOM input is focused; new UOMs from modal are pushed here
const uomList = ref([]);
const uomLoading = ref(false);
async function loadUomList() {
    if (uomLoading.value) return;
    uomLoading.value = true;
    try {
        const { data } = await axios.get(route('products.uom.list'));
        uomList.value = Array.isArray(data) ? data : [];
    } catch (_) {
        uomList.value = [];
    } finally {
        uomLoading.value = false;
    }
}
const UOM_ADD_OPTION_ID = '+ Add new UOM';
const uomOptions = computed(() => [
    { id: UOM_ADD_OPTION_ID, name: '+ Add new UOM' },
    ...uomList.value.map((s) => ({ id: s, name: s })),
]);
function getUomModel(uomStr) {
    if (!uomStr) return null;
    return { id: uomStr, name: uomStr };
}
const form = ref({
    id: props.po?.id,
    supplier_id: props.po?.supplier_id,
    supplier: props.po?.supplier,
    original_po_no: props.po?.original_po_no,
    notes: props.po?.notes,
    po_number: props.po?.po_number,
    po_date: props.po?.po_date ? new Date(props.po.po_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    items: props.po?.items || [],
    reviewed_at: props.po?.reviewed_at,
    reviewed_by: props.po?.reviewed_by,
    approved_at: props.po?.approved_at,
    approved_by: props.po?.approved_by,
    rejected_at: props.po?.rejected_at,
    rejected_by: props.po?.rejected_by
});

const isLoading = ref(false);
const isSubmitting = ref(false);
const isProcessing = ref({
    review: false,
    approve: false,
    reject: false
});

// UOM Modal state
const showUomModal = ref(false);
const currentUomIndex = ref(null);
const uomForm = ref({
    name: ''
});
const isUomSubmitting = ref(false);

function onUomSelect(index, selected) {
    const name = selected?.name ?? selected;
    if (name === '+ Add new UOM' || name === UOM_ADD_OPTION_ID) {
        form.value.items[index].uom = '';
        showUomModal.value = true;
        currentUomIndex.value = index;
    } else {
        form.value.items[index].uom = name;
    }
}

function addItem() {
    // Check if there are existing items and if the last item has no product_id
    if (form.value.items.length > 0) {
        const lastItem = form.value.items[form.value.items.length - 1];
        if (!lastItem.product_id) {
            return;
        }
    }

    form.value.items.push({
        product_id: null,
        product: null,
        uom: "",
        quantity: 1,
        unit_cost: 0,
        total_cost: 0
    });
}

function removeItem(index) {
    form.value.items.splice(index, 1);

    // If all items are removed, add one back
    if (form.value.items.length === 0) {
        addItem();
    }
}

function calculateTotal(index) {
    const item = form.value.items[index];
    item.total_cost = item.quantity * item.unit_cost;
}

const subtotal = computed(() => {
    return form.value.items.reduce((sum, item) => sum + (item.total_cost || 0), 0);
});

function handleSupplierSelect(selected) {
    form.value.supplier_id = selected.id;
    form.value.supplier = selected;
    addItem();
}

function hadleProductSelect(index, selected) {
    form.value.items[index].product_id = selected.id;
    form.value.items[index].product = selected;
    addItem();
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value || 0);
}

// UOM Modal functions
async function createUom() {
    if (!uomForm.value.name.trim()) {
        toast.error('Please enter a UOM name');
        return;
    }

    try {
        isUomSubmitting.value = true;
        const response = await axios.post(route('products.uom.store'), uomForm.value);
        
        // Update the form item with the new UOM name
        form.value.items[currentUomIndex.value].uom = response.data;
        // Add to preloaded UOM list so it appears in the dropdown
        uomList.value.push(response.data);
        
        // Close modal and reset form
        showUomModal.value = false;
        uomForm.value.name = '';
        currentUomIndex.value = null;
        
        toast.success('UOM created successfully');
    } catch (error) {
        console.error('Error creating UOM:', error);
        toast.error(error.response?.data || 'Failed to create UOM');
    } finally {
        isUomSubmitting.value = false;
    }
}

function closeUomModal() {
    showUomModal.value = false;
    uomForm.value.name = '';
    currentUomIndex.value = null;
}

const formatDate = (dateString) => {
    if (!dateString) return '';
    return moment(dateString).format('MM/DD/YYYY hh:mm A');
};

async function reviewPO() {
    if (isProcessing.value.review) return;

    const result = await Swal.fire({
        title: 'Review Purchase Order',
        text: 'Are you sure you want to review this purchase order?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, review it!'
    });

    if (!result.isConfirmed) return;

    try {
        isProcessing.value.review = true;
        const response = await axios.post(route('supplies.reviewPO', form.value.id));
        form.value.reviewed_at = response.data.reviewed_at;
        form.value.reviewed_by = response.data.reviewed_by;
        
        await Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Purchase order has been reviewed successfully',
            confirmButtonColor: '#3085d6'
        });
        
        // Force a complete page reload to get fresh data from the server
        router.visit(route('supplies.editPO', form.value.id), { 
            method: 'get',
            preserveState: false,
            preserveScroll: false,
            replace: true
        });
    } catch (error) {
        console.error('Error reviewing PO:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data || 'Failed to review purchase order'
        });
    } finally {
        isProcessing.value.review = false;
    }
}

async function approvePO() {
    if (isProcessing.value.approve) return;

    const result = await Swal.fire({
        title: 'Approve Purchase Order',
        text: 'Are you sure you want to approve this purchase order?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    });

    if (!result.isConfirmed) return;

    try {
        isProcessing.value.approve = true;
        const response = await axios.post(route('supplies.approvePO', form.value.id));
        form.value.approved_at = response.data.approved_at;
        form.value.approved_by = response.data.approved_by;
        form.value.status = 'approved';
        
        await Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Purchase order has been approved successfully',
            confirmButtonColor: '#3085d6'
        });
        
        // Force a complete page reload to get fresh data from the server
        router.visit(route('supplies.editPO', form.value.id), { 
            method: 'get',
            preserveState: false,
            preserveScroll: false,
            replace: true
        });
    } catch (error) {
        console.error('Error approving PO:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data || 'Failed to approve purchase order'
        });
    } finally {
        isProcessing.value.approve = false;
    }
}

async function rejectPO() {
    if (isProcessing.value.reject) return;

    const { value: reason } = await Swal.fire({
        title: 'Rejection Reason',
        input: 'textarea',
        inputLabel: 'Please provide a reason for rejection',
        inputPlaceholder: 'Type your reason here...',
        inputAttributes: {
            'aria-label': 'Type your reason here'
        },
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Reject',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to provide a reason!';
            }
        }
    });

    if (!reason) return;

    try {
        isProcessing.value.reject = true;
        const response = await axios.post(route('purchase-orders.reject', form.value.id), {
            reason: reason
        });
        form.value.rejected_at = response.data.rejected_at;
        form.value.rejected_by = response.data.rejected_by;
        
        await Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Purchase order has been rejected successfully',
            confirmButtonColor: '#3085d6'
        });
        
        // Force a complete page reload to get fresh data from the server
        router.visit(route('supplies.editPO', form.value.id), { 
            method: 'get',
            preserveState: false,
            preserveScroll: false,
            replace: true
        });
    } catch (error) {
        console.error('Error rejecting PO:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to reject purchase order'
        });
    } finally {
        isProcessing.value.reject = false;
    }
}

async function submitForm() {
    if (!form.value.supplier_id) {
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: 'Please select a supplier'
        });
        return;
    }

    // Filter out items with no product_id
    const filteredItems = form.value.items.filter(item => item.product_id);
    if (filteredItems.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: 'Please add at least one item'
        });
        return;
    }

    // Validate only items with product_id
    const invalidItems = filteredItems.filter(item => !item.uom || !item.quantity || !item.unit_cost);
    if (invalidItems.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: 'Please fill in all required fields for each item (Product, Quantity, and Unit Cost)'
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Update Purchase Order',
        text: 'Are you sure you want to update this purchase order?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    });

    if (!result.isConfirmed) return;

    isSubmitting.value = true;

    try {
        // Send only filtered items with properly formatted date
        const payload = { 
            ...form.value, 
            items: filteredItems,
            po_date: moment(form.value.po_date).format('YYYY-MM-DD')
        };
        const response = await axios.put(route('supplies.updatePO', form.value.id), payload);
        await Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Purchase order has been updated successfully',
            confirmButtonColor: '#3085d6'
        });
        // Force a complete page reload to get fresh data from the server
        router.visit(route('supplies.editPO', form.value.id), { 
            method: 'get',
            preserveState: false,
            preserveScroll: false,
            replace: true
        });
    } catch (error) {
        console.error('Error updating PO:', error);
        let errorMessage = 'Failed to update purchase order';
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data) {
            // Handle validation errors
            const validationErrors = error.response.data;
            if (typeof validationErrors === 'object') {
                errorMessage = Object.values(validationErrors).flat().join('\n');
            } else {
                errorMessage = validationErrors;
            }
        }
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMessage,
            confirmButtonColor: '#3085d6'
        });
    } finally {
        isSubmitting.value = false;
    }
}
</script>
