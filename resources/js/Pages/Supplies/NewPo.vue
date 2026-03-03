<template>
    <Head title="New Purchase Order" />
    <AuthenticatedLayout title="New Purchase Order" description="Create a new purchase order and add items" img="/assets/images/orders.png">
        <!-- Back Navigation -->
        <Link :href="route('supplies.index')" class="inline-flex items-center text-slate-600 hover:text-slate-900 transition-colors duration-200 group mb-6">
            <svg class="w-5 h-5 mr-2 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            <span class="text-sm font-medium">Back to Purchase Orders</span>
        </Link>

        <!-- Header Section -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-5 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">New Purchase Order</h1>
                    <p class="text-slate-500 text-sm mt-0.5">Create a new purchase order and add items</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Draft</span>
            </div>
        </div>

        <!-- Supplier Selection Card (overflow-visible so Multiselect dropdown is not clipped) -->
        <div class="bg-white rounded-xl shadow-sm overflow-visible mb-6">
            <div class="px-6 py-5 border-b border-slate-100">
                <h2 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </span>
                    Supplier Information
                </h2>
            </div>
            <div class="px-6 py-5">
                <label class="block text-sm font-medium text-slate-700 mb-2">Select Supplier</label>
                <div class="max-w-md">
                    <Multiselect
                        v-model="form.supplier"
                        :options="props.suppliers || []"
                        :searchable="true"
                        :close-on-select="true"
                        :show-labels="false"
                        :allow-empty="true"
                        :max-height="300"
                        no-options-text="No suppliers found."
                        no-results-text="No suppliers match your search."
                        placeholder="Search and select supplier..."
                        track-by="id"
                        label="name"
                        class="multiselect-modern"
                        @select="handleSupplierSelect"
                    />
                </div>
            </div>
            <!-- Supplier Details -->
            <div v-if="isLoading" class="px-6 pb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="animate-pulse space-y-4">
                    <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                    <div class="h-4 bg-slate-200 rounded w-1/2"></div>
                    <div class="h-4 bg-slate-200 rounded w-2/3"></div>
                </div>
                <div class="animate-pulse space-y-4">
                    <div class="h-4 bg-slate-200 rounded w-1/2"></div>
                    <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                    <div class="h-4 bg-slate-200 rounded w-2/3"></div>
                </div>
            </div>
            <div v-else-if="selectedSupplier" class="px-6 pb-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-slate-100 pt-5">
                <div class="space-y-5">
                    <div>
                        <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider">Company Details</h3>
                        <p class="text-base font-semibold text-slate-900 mt-1">{{ selectedSupplier.name }}</p>
                        <p class="text-sm text-slate-600 mt-0.5">{{ selectedSupplier.contact_person }}</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider">Contact Information</h3>
                        <div class="mt-2 space-y-2.5">
                            <div class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                <span>{{ selectedSupplier.email }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <span>{{ selectedSupplier.phone }}</span>
                            </div>
                            <div class="flex items-start gap-2 text-sm text-slate-600">
                                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span>{{ selectedSupplier.address }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-5">
                    <div>
                        <h3 class="text-xs font-medium text-slate-500 uppercase tracking-wider">Order Information</h3>
                        <div class="mt-3 space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-500 min-w-[90px]">P.O Number</span>
                                <span class="text-sm font-semibold text-slate-900">{{ form.po_number }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-500 min-w-[90px]">Reference No</label>
                                <input type="text" v-model="form.original_po_no" :disabled="form.approved_at"
                                    class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                                    placeholder="Enter reference" />
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-500 min-w-[90px]">Date</label>
                                <input type="date" v-model="form.po_date" :disabled="form.approved_at"
                                    class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400" />
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-slate-500 min-w-[90px]">Expected Date <span class="text-red-500">*</span></label>
                                <input type="date" v-model="form.expected_date" :disabled="form.approved_at"
                                    :min="form.po_date"
                                    required
                                    class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400" />
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <input type="file" ref="fileInput" @change="handleFileUpload" accept=".xlsx,.xls" class="hidden" />
                        <button type="button" @click="$refs.fileInput.click()"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg text-white bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Upload Excel
                        </button>
                        <button type="button" @click="downloadTemplate"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Download Template
                        </button>
                        <span v-if="uploadStatus" class="text-sm text-slate-500 animate-pulse">{{ uploadStatus }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <form @submit.prevent="submitForm" novalidate>
            <div class="bg-white rounded-xl shadow-sm overflow-visible mb-6 order-items-section">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">Order Items</h3>
                                <p class="text-sm text-slate-500 mt-0.5">Add items to your purchase order</p>
                            </div>
                        </div>
                        <div class="text-sm text-slate-600">
                            Total: <span class="font-semibold text-slate-900">{{ formatCurrency(subtotal) }}</span>
                        </div>
                    </div>
                </div>
                <div class="min-w-0">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80">
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider" style="width: 350px;">Item</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-[300px]">UoM</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Unit Cost</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(item, index) in form.items" :key="index"
                                class="order-item-row hover:bg-slate-50/50 transition-colors"
                                :data-item-index="index">
                                <td class="px-4 py-3 text-sm text-slate-500">{{ index + 1 }}</td>
                                <td class="px-4 py-3 relative z-[1]" style="width: 350px;">
                                    <Multiselect
                                        v-model="item.product"
                                        :options="props.products || []"
                                        :searchable="true"
                                        :close-on-select="true"
                                        :show-labels="false"
                                        :allow-empty="true"
                                        :max-height="240"
                                        no-options-text="No items found."
                                        no-results-text="No items match your search."
                                        placeholder="Search and select item..."
                                        track-by="id"
                                        label="name"
                                        class="multiselect-modern"
                                        @select="(val) => hadleProductSelect(index, val)"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" v-model="item.quantity" @input="calculateTotal(index)" required
                                        class="w-full text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 px-3 py-2"
                                        min="1" placeholder="Qty">
                                </td>
                                <td class="px-4 py-3 w-[300px] relative z-[1]">
                                    <Multiselect
                                        :model-value="getUomModel(item.uom)"
                                        :options="uomOptions"
                                        :searchable="true"
                                        :close-on-select="true"
                                        :show-labels="false"
                                        :max-height="240"
                                        no-options-text="No UoM found. Add one with + Add new UOM."
                                        no-results-text="No UoM match your search."
                                        placeholder="Search and select UoM..."
                                        track-by="id"
                                        label="name"
                                        class="multiselect-modern"
                                        @select="(val) => onUomSelect(index, val)"
                                        @open="loadUomList"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" v-model="item.unit_cost" @input="calculateTotal(index)" required
                                        class="w-full text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 px-3 py-2"
                                        step="0.01" min="0" placeholder="0.00">
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-medium text-slate-900">{{ formatCurrency(item.total_cost) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" @click="removeItem(index)"
                                        class="inline-flex items-center justify-center w-8 h-8 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="form.items.length === 0">
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center max-w-sm mx-auto">
                                        <span class="flex items-center justify-center w-14 h-14 rounded-full bg-slate-100 text-slate-400 mb-4">
                                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                            </svg>
                                        </span>
                                        <h3 class="text-sm font-medium text-slate-900">No items added</h3>
                                        <p class="mt-1 text-sm text-slate-500">Add items below to build your purchase order.</p>
                                        <button type="button" @click="addItem"
                                            class="mt-5 inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors">
                                            <PlusIcon class="h-5 w-5" />
                                            Add Item
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Table Footer -->
                <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100">
                    <div class="flex flex-wrap justify-between items-center gap-4">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="addItem"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400/50 transition-colors">
                                <PlusIcon class="h-5 w-5 text-slate-500" />
                                Add Item
                            </button>
                            <button type="button" @click="form.items = []"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400/50 transition-colors">
                                <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Clear All
                            </button>
                        </div>
                        <div class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-lg">
                            <span class="text-sm text-slate-500">Subtotal</span>
                            <span class="text-lg font-semibold text-slate-900">{{ formatCurrency(subtotal) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </span>
                        Additional Notes
                    </h3>
                </div>
                <div class="px-6 py-4">
                    <textarea v-model="form.notes" rows="3"
                        class="w-full text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 resize-none px-3 py-2"
                        placeholder="Enter any additional notes or special instructions for this purchase order..."></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm px-6 py-4 mb-[80px] flex flex-wrap justify-end gap-3">
                <button type="button" @click="router.visit(route('supplies.index'))" :disabled="isSubmitting"
                    class="inline-flex items-center px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400/50 transition-colors disabled:opacity-50">
                    Exit
                </button>
                <button type="submit" :disabled="isSubmitting || !$page.props.auth.can.purchase_order_create || !form.supplier_id || !form.expected_date"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg v-if="isSubmitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ isSubmitting ? "Saving..." : "Save Purchase Order" }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>

    <!-- UOM Creation Modal -->
    <Teleport to="body">
        <div v-if="showUomModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" @click.self="closeUomModal">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl border border-slate-200/80 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="uom-modal-title">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 id="uom-modal-title" class="text-base font-semibold text-slate-900">Create New UOM</h3>
                    <button type="button" @click="closeUomModal" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="createUom" class="p-6 space-y-4">
                    <div>
                        <label for="uom-name" class="block text-sm font-medium text-slate-700 mb-1.5">UOM Name</label>
                        <input
                            id="uom-name"
                            type="text"
                            v-model="uomForm.name"
                            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                            placeholder="Enter UOM name"
                            required
                            autofocus
                        />
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="closeUomModal"
                            :disabled="isUomSubmitting"
                            class="px-4 py-2.5 text-sm font-medium rounded-lg text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400/50 disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isUomSubmitting"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 disabled:opacity-50"
                        >
                            <svg v-if="isUomSubmitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isUomSubmitting ? 'Creating...' : 'Create UOM' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* Row with open dropdown stacks above next rows so dropdown doesn't overlap them */
.order-items-section tbody tr.order-item-row {
    position: relative;
    z-index: 0;
}
/* When any Multiselect (Item or UoM) in the row is open, raise the row so its dropdown draws on top */
.order-items-section tbody tr.order-item-row:has(.multiselect--active) {
    z-index: 10;
}
/* Ensure vue-multiselect dropdown content is on top within the row */
.order-items-section :deep(.multiselect__content-wrapper) {
    z-index: 9999 !important;
}
.order-items-section :deep([role="listbox"]) {
    z-index: 2147483647 !important;
}
</style>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import * as XLSX from 'xlsx';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import moment from 'moment';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    products: Array,
    suppliers: Array,
    po_number: [String, Number],
    uom: Array
});

const selectedSupplier = ref(null);
// UOM list loaded on first focus of UOM input; new UOMs from modal are pushed here
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
const form = ref({
    id: null,
    supplier_id: "",
    supplier: null,
    original_po_no: "",
    notes: "",
    po_number: props.po_number,
    po_date: new Date().toISOString().split('T')[0],
    expected_date: null,
    // Preload one empty item row
    items: [
        { product_id: null, product: null, uom: "", quantity: 1, unit_cost: 0, total_cost: 0 }
    ]
});

const fileInput = ref(null);
const uploadStatus = ref('');

watch(
    () => form.value.supplier,
    (val) => {
        form.value.supplier_id = val?.id ?? null;
        if (!val) selectedSupplier.value = null;
    },
    { immediate: true }
);

async function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (!form.value.supplier_id) {
        uploadStatus.value = 'Please select a supplier first';
        event.target.value = '';
        return;
    }

    uploadStatus.value = 'Creating purchase order...';

    try {
        // Step 1: Create PO via Inertia (or Axios if needed)
        const poPayload = {
            supplier_id: form.value.supplier_id,
            po_date: moment(form.value.po_date).format('YYYY-MM-DD'),
            po_number: form.value.po_number,
            expected_date: form.value.expected_date ? moment(form.value.expected_date).format('YYYY-MM-DD') : null
        };

        const poResponse = await axios.post(route('purchase-orders.store'), poPayload);
        const purchaseOrderId = poResponse.data.id;
        form.value.id = purchaseOrderId;

        // Step 2: Upload Excel file via Inertia
        uploadStatus.value = 'Uploading items...';

        const formData = new FormData();
        formData.append('file', file);
        formData.append('purchase_order_id', purchaseOrderId);

        router.post(route('purchase-orders.import'), formData, {
            forceFormData: true,
            onSuccess: () => {
                toast.success('Items imported successfully');
            },
            onError: (errors) => {
                console.error(errors);
                uploadStatus.value = 'Upload failed.';
            }
        });
    } catch (error) {
        console.error('Error during file upload:', error);
        uploadStatus.value = 'Upload failed: ' + (error.response?.data?.message || error.message);
    } finally {
        event.target.value = '';
    }
}

// Function to check import progress
async function checkImportProgress(importId) {
    try {
        const response = await axios.get(route('purchase-orders.import.progress'), {
            params: { import_id: importId }
        });
        
        if (response.data.progress > 0) {
            uploadStatus.value = `Import progress: ${response.data.progress} items processed`;
        }
        
        // Continue checking if still processing
        if (response.data.status === 'processing') {
            setTimeout(() => checkImportProgress(importId), 2000); // Check every 2 seconds
        } else {
            uploadStatus.value = 'Import completed successfully!';
            
            // Redirect to the EditPo page
            setTimeout(() => {
                if (form.value.id) {
                    router.visit(route('supplies.editPO', form.value.id));
                }
            }, 1500); // Wait 1.5 seconds to show success message
        }
    } catch (error) {
        console.error('Error checking import progress:', error);
        uploadStatus.value = 'Error checking import progress';
    }
}

function hadleProductSelect(index, selected){
    form.value.items[index].product_id = selected.id;
    form.value.items[index].product = selected;
    addItem();
}

function handleSupplierSelect(selected){
    form.value.supplier_id = selected.id;
    form.value.supplier = selected;
    onSupplierChange(selected);
    addItem();
}

function addItem() {
    // Only add a new row if the last row has a valid product_id
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

const UOM_ADD_OPTION_ID = '+ Add new UOM';
const uomOptions = computed(() => [
    { id: UOM_ADD_OPTION_ID, name: '+ Add new UOM' },
    ...uomList.value.map((s) => ({ id: s, name: s })),
]);

function getUomModel(uomStr) {
    if (!uomStr) return null;
    return { id: uomStr, name: uomStr };
}

function onUomSelect(index, val) {
    const name = val?.name ?? val ?? '';
    if (name === '+ Add new UOM' || name === UOM_ADD_OPTION_ID) {
        form.value.items[index].uom = '';
        showUomModal.value = true;
        currentUomIndex.value = index;
    } else {
        form.value.items[index].uom = name;
    }
}

const isSubmitting = ref(false);

// UOM Modal state
const showUomModal = ref(false);
const currentUomIndex = ref(null);
const uomForm = ref({
    name: ''
});
const isUomSubmitting = ref(false);

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

    Swal.fire({
        title: "Confirm Creation",
        text: "Are you sure you want to create this purchase order?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, create it!"
    }).then((result) => {
        if (result.isConfirmed) {
            isSubmitting.value = true;
            axios.post(route('supplies.storePO'), { ...form.value, items: filteredItems }, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            })
            .then((response) => {
                isSubmitting.value = false;
                Swal.fire({
                    title: "Success!",
                    text: response.data,
                    icon: "success"
                }).then(() => {
                    router.visit(route('supplies.index'));
                });
            })
            .catch((error) => {
                console.log(error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.response?.data || 'Failed to create purchase order'
                });
                isSubmitting.value = false;
            });
        }
    });
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value);
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

const isLoading = ref(false);
async function onSupplierChange(selected) {
    isLoading.value = true;
    let value = selected.id;
    if (!value) {
        selectedSupplier.value = null;
        form.value.supplier_id = null;
        isLoading.value = false;
        return;
    }

    form.value.supplier_id = value;
    const supplier = props.suppliers.find(s => s.id == value);
    selectedSupplier.value = supplier;
    setTimeout(() => isLoading.value = false, 1000);
}

function downloadTemplate() {
    // Define the template headers
    const headers = ['Item Description', 'UoM', 'Quantity', 'Category', 'Dosage Form', 'Unit Cost', 'Total Cost'];
    
    // Create workbook and worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet([headers]);
    
    // Auto-size columns
    const colWidths = headers.map(header => ({ wch: header.length + 5 }));
    ws['!cols'] = colWidths;
    
    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Template');
    
    // Generate and download file
    XLSX.writeFile(wb, 'supplies_import_template.xlsx');
}

// const po_date = ref(moment().format('YYYY-MM-DD'));
</script>
