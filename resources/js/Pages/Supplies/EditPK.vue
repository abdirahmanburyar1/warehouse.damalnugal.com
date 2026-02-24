<template>
    <AuthenticatedLayout title="Edit Packing List" description="Edit your packing list" img="/assets/images/orders.png">
        <Head>
            <title>Edit Packing List</title>
        </Head>
        <!-- Back Navigation -->
        <Link :href="route('supplies.packing-list.showPK')"
            class="inline-flex items-center text-gray-600 hover:text-indigo-600 transition-colors duration-200 group mb-6">
        <svg class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform duration-200" fill="none"
            stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to Packing List
        </Link>

        <!-- Header Section -->
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-2xl p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Edit Packing List</h1>
                    <p class="text-gray-600 mt-1">Edit and manage packing lists for received items</p>
                </div>
                <div class="flex items-center space-x-3">
                    <div v-if="form.status === 'approved'"
                        class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">Approved</div>
                    <div v-else-if="form.status === 'reviewed'"
                        class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">Reviewed</div>
                    <div v-else-if="form.status === 'rejected'"
                        class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">Rejected</div>
                    <div v-else class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">Draft
                    </div>
                </div>
            </div>
        </div>

        <!-- Supplier Information Card -->
        <div v-if="form" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Supplier Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-3">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Company Details</h3>
                    <p class="text-base font-semibold text-gray-900">{{ form.purchase_order?.supplier?.name }}</p>
                    <p class="text-sm text-gray-600">{{ form.purchase_order?.supplier?.contact_person }}</p>
                </div>
                <div class="space-y-3">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Contact Information</h3>
                    <div class="space-y-2">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            {{ form.purchase_order?.supplier?.email }}
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                </path>
                            </svg>
                            {{ form.purchase_order?.supplier?.phone }}
                        </div>
                        <div class="flex items-start text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2 text-gray-400 mt-0.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                </path>
                            </svg>
                            {{ form.purchase_order?.supplier?.address }}
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Packing List Details</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">PL Number:</span>
                            <input type="text" v-model="form.packing_list_number"
                                class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                placeholder="Enter PL number" />
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">Reference No:</span>
                            <input type="text" v-model="form.ref_no"
                                class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                placeholder="Enter reference" />
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">P.O Date:</span>
                            <span class="text-sm font-semibold text-gray-900">{{
                                moment(form.purchase_order?.po_date).format("DD/MM/YYYY") }}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 mr-2">PL Date:</span>
                            <input type="date" v-model="form.pk_date"
                                class="text-sm border-0 bg-transparent focus:ring-0 focus:border-b-2 focus:border-indigo-500"
                                :min="form.purchase_order?.po_date" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <div v-if="form" class="mt-4 w-full">
            <table class="min-w-full divide-y divide-gray-200 table-auto">
                <colgroup>
                    <col class="w-8" />
                    <col class="w-48" />
                    <col class="w-[120px]" />
                    <col class="w-[150px]" />
                    <col class="w-64" />
                    <col class="w-56" />
                    <col class="w-28" />
                </colgroup>
                <thead class="bg-gray-50 border border-black">
                    <tr>
                        <th class="px-3 py-2 text-xs font-bold rounded-tl-lg sticky left-0 z-10 w-8 text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">#</th>
                        <th class="px-3 py-2 text-xs font-bold sticky left-8 z-10 w-[200px] text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Item</th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">UOM</th>
                        <th class="px-3 py-2 text-xs font-bold w-[150px] text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">QTY</th>
                        <th class="px-3 py-2 text-xs font-bold w-64 text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Warehouse</th>
                        <th class="px-3 py-2 text-xs font-bold w-56 text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Location</th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Item Detail</th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Unit Cost</th>
                        <th class="px-3 py-2 text-xs font-bold text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Total Cost</th>
                        <th class="px-3 py-2 text-xs font-bold rounded-tr-lg w-20 text-left" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Fulfillment</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in form.items" :key="index"
                        :class="{
                            'hover:bg-gray-50 transition-colors duration-150': true,
                            'bg-red-50': hasIncompleteBackOrder(item),
                            'border-red-500 border-2': item.hasError,
                            'bg-red-50/20': item.hasError,
                        }"
                        style="border-bottom: 1px solid #B7C6E6;"
                        :data-row="index + 1"
                    >
                        <td class="px-3 py-2 text-xs text-gray-900 sticky left-0 z-10 bg-white w-8"
                            style="border-bottom: 1px solid #B7C6E6;">
                            {{ index + 1 }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 sticky left-8 z-10 bg-white w-[200px]"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <p class="text-xs text-break">
                                {{ item.product?.name }}
                            </p>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <span class="font-bold text-xs text-gray-500">{{ item.uom }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 w-[150px]"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <div class="flex flex-col">
                                <div>
                                    <input type="number" v-model="item.purchase_order_item.quantity" readonly
                                        class="block w-full text-left text-black focus:ring-0 sm:text-sm border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <div>
                                    <label for="received_quantity text-xs" class="text-xs">Received QTY</label>
                                    <input type="number" v-model="item.quantity" required min="1"
                                        :disabled="props.packing_list.status === 'approved'"
                                        class="block w-full text-left text-black focus:ring-0 sm:text-sm border-0 bg-transparent border-b border-gray-300"
                                        @input="handleReceivedQuantityChange(index)" />
                                </div>
                                <div>
                                    <label for="mismatches" class="text-xs">Mismatches</label>
                                    <input type="text" :value="calculateMismatches(item)" readonly
                                        class="block w-full text-left text-black focus:ring-0 sm:text-sm border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <button v-if="calculateFulfillmentRate(item) < 100" @click="openBackOrderModal(index)"
                                    class="mt-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                    Back Order
                                </button>

                                <!-- Add tooltip for incomplete back orders -->
                                <div v-if="calculateFulfillmentRate(item) < 100 || !hasRequiredFields(item)" 
                                    :class="{
                                        'mt-2 text-xs px-2 py-1 rounded': true,
                                        'bg-red-100 text-red-800': !hasRequiredFields(item) || getMismatchStatus(item).status === 'unrecorded' || getMismatchStatus(item).status === 'partial',
                                        'bg-yellow-100 text-yellow-800': getMismatchStatus(item).status === 'excess',
                                        'bg-green-100 text-green-800': hasRequiredFields(item) && getMismatchStatus(item).status === 'complete'
                                    }">
                                    {{ !hasRequiredFields(item) ? 'Missing required fields' : getMismatchStatus(item).message }}
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 w-64"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <Multiselect v-model="item.warehouse" :value="item.warehouse_id" :options="props.warehouses"
                                :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true"
                                placeholder="Select Warehouse" required track-by="id" :disabled="props.packing_list.status === 'approved'"
                                :append-to-body="true" label="name" @select="handleWarehouseSelect(index, $event)">
                            </Multiselect>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 w-56"
                            style="border-bottom: 1px solid #B7C6E6;">
                            <Multiselect v-model="item.location" required
                                :disabled="props.packing_list.status === 'approved' || !item.warehouse_id"
                                :options="['Add new location', ...loadedLocation]" :searchable="true"
                                :close-on-select="true" :show-labels="false" :allow-empty="true"
                                placeholder="Select Location" @select="hadleLocationSelect(index, $event)"
                                track-by="location" label="location"
                                :custom-label="(option) => typeof option === 'string' ? option : (option && option.location ? option.location : '')">
                                <template v-slot:option="{ option }">
                                    <div :class="{ 'add-new-option': typeof option === 'string' }">
                                        <span v-if="typeof option === 'string'" class="text-indigo-600 font-medium">+ {{ option }}</span>
                                        <span v-else-if="option && option.location">{{ option.location }}</span>
                                        <span v-else>Select Location</span>
                                    </div>
                                </template>
                            </Multiselect>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="space-y-1">
                                <div>
                                    <label class="text-[10px] text-block">Batch</label>
                                    <input type="text" v-model="item.batch_number" required
                                        :disabled="props.packing_list.status === 'approved'"
                                        class="block w-full text-xs text-black focus:ring-0 p-1 border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <div>
                                    <label class="text-[10px] text-block">Expiry</label>
                                    <input type="date" :value="formatDateForInput(item.expire_date)" required
                                        @input="item.expire_date = $event.target.value"
                                        :min="moment().add(6, 'months').format('YYYY-MM-DD')"
                                        :disabled="props.packing_list.status === 'approved'"
                                        class="block w-full text-xs text-black focus:ring-0 p-1 border-0 bg-transparent border-b border-gray-300" />
                                </div>
                                <div>
                                    <label class="text-[10px] text-block">Barcode</label>
                                    <input type="text" v-model="item.barcode" required
                                        :disabled="props.packing_list.status === 'approved'"
                                        class="block w-full text-xs text-black focus:ring-0 p-1 border-0 bg-transparent border-b border-gray-300" />
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="text-sm">
                                {{ Number(item.unit_cost).toLocaleString("en-US", { style: "currency", currency: "USD" }) }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="text-sm">
                                {{ Number(item.total_cost).toLocaleString("en-US", { style: "currency", currency: "USD" }) }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-900 text-center w-20"
                        style="border-bottom: 1px solid #B7C6E6;">
                            <div class="text-sm">
                                <span>{{ calculateFulfillmentRate(item) }}%</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="form?.items?.length === 0">
                        <td colspan="7" class="px-3 py-4 text-center text-sm text-gray-500">
                            No items added. Click "Add Item" to start creating your purchase order.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Memo Field -->
        <div v-if="form" class="mt-4 bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Memo</h3>
            <textarea v-model="form.notes" rows="3"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                placeholder="Enter memo or additional notes here..."></textarea>
        </div>
        <div v-else>
            <span>No P.O Data found</span>
        </div>

        <!-- Packing List Status Actions -->
        <div v-if="form" class="mt-8 mb-6 px-6 py-6 bg-white rounded-lg shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">
                Packing List Status Actions
            </h3>
            <div class="flex justify-start items-center mb-6">
                <!-- Status Action Buttons -->
                <div class="flex flex-wrap items-center justify-start gap-4">
                    <!-- Review button -->
                    <div class="relative">
                        <div class="flex flex-col">
                            <button @click="reviewPackingList"
                                :class="[
                                    form.reviewed_at
                                        ? 'bg-green-500'
                                        : form.approved_at || form.rejected_at
                                        ? 'bg-gray-300 cursor-not-allowed'
                                        : 'bg-yellow-500 hover:bg-yellow-600'
                                ]"
                                :disabled="
                                    isReviewing ||
                                    form.reviewed_at ||
                                    form.approved_at ||
                                    form.rejected_at ||
                                    !$page.props.auth.can.packing_list_review
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
                            <button @click="approvePackingList"
                                :class="[
                                    form.approved_at
                                        ? 'bg-green-500'
                                        : !form.reviewed_at || form.rejected_at
                                        ? 'bg-gray-300 cursor-not-allowed'
                                        : 'bg-green-500 hover:bg-green-600'
                                ]"
                                :disabled="form.approved_at || isApproving || !form.reviewed_at || !$page.props.auth.can.packing_list_approve"
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
                            <button @click="rejectPackingList"
                                :class="[
                                    form.rejected_at
                                        ? 'bg-red-500'
                                        : !form.reviewed_at
                                        ? 'bg-gray-300 cursor-not-allowed'
                                        : 'bg-red-500 hover:bg-red-600'
                                ]"
                                :disabled="
                                    isReviewing ||
                                    isApproving ||
                                    isRejecting ||
                                    !form.reviewed_at ||
                                    form.rejected_at ||
                                    form.approved_at ||
                                    !$page.props.auth.can.packing_list_reject
                                "
                                class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-50 disabled:cursor-not-allowed">
                                <img src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Reject" />
                                <span class="text-sm font-bold text-white">{{ form.rejected_at ? 'Rejected' : 'Reject' }}</span>
                            </button>
                            <div v-if="form.rejected_at" class="mt-2 text-center max-w-[200px]">
                                <div class="text-xs text-gray-600">{{ moment(form.rejected_at).format('DD/MM/YYYY HH:mm') }}</div>
                                <div class="text-xs font-medium text-gray-700">By {{ form.rejected_by?.name }}</div>
                                <p v-if="form.rejection_reason" class="text-xs text-gray-600 mt-1 italic">"{{ form.rejection_reason }}"</p>
                            </div>
                        </div>
                        <div v-if="form.reviewed_at && !form.approved_at && !form.rejected_at"
                            class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3">
                <Link :href="route('supplies.index')" :disabled="isSubmitting"
                    class="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Exit
                </Link>
                <button v-if="!hasAllApproved" @click="submit" :disabled="isSubmitting || isApproving || isReviewing || isRejecting || !canSubmit || !$page.props.auth.can.packing_list_update" :title="submitButtonTitle"
                    class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ isSubmitting ? "Updating..." : "Update Changes" }}
                </button>
            </div>
        </div>

        <!-- Back Order Modal -->
        <Modal :show="showBackOrderModal" @close="attemptCloseModal" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Back Order Details</h2>
                    <button @click="attemptCloseModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <div class="mb-6 bg-gradient-to-r from-yellow-50 to-orange-50 p-4 rounded-lg border border-yellow-200">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 font-medium">Product:</span>
                            <p class="text-gray-900 font-semibold">{{ selectedItem?.product?.name }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600 font-medium">Expected:</span>
                            <p class="text-gray-900 font-semibold">{{ selectedItem?.purchase_order_item?.quantity }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600 font-medium">Received:</span>
                            <p class="text-gray-900 font-semibold">{{ selectedItem?.quantity || 0 }}</p>
                        </div>
                        <div>
                            <span class="text-gray-600 font-medium">Mismatches:</span>
                            <p class="text-yellow-800 font-semibold">{{ actualMismatches }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantity</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Note
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(row, index) in backOrderRows" :key="index" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="number" v-model="row.quantity" :disabled="row.finalized != null || form.status === 'approved'"
                                        class="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 disabled:opacity-60 disabled:cursor-not-allowed"
                                        min="0" />
                                </td>
                                <td class="px-4 py-3">
                                    <select v-model="row.status" :disabled="form.status === 'approved'"
                                        class="mt-1 block w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 disabled:opacity-60 disabled:cursor-not-allowed">
                                        <option
                                            v-for="status in [row.status, ...availableStatuses.filter((s) => s !== row.status)]"
                                            :key="status" :value="status">
                                            {{ status }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" v-model="row.notes" :disabled="form.status === 'approved'"
                                        class="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 disabled:opacity-60 disabled:cursor-not-allowed"
                                        placeholder="Enter note..." />
                                </td>
                                <td class="px-4 py-3">
                                    <button @click="removeBackOrderRow(index, row)" :disabled="form.status === 'approved'"
                                        class="text-red-600 hover:text-red-800 transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:text-red-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <button @click="addBackOrderRow"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                            :disabled="!canAddMoreRows || form.status === 'approved'">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Row
                        </button>
                        <div class="text-sm">
                            <span :class="{ 'text-green-600': isValidForSave, 'text-red-600': !isValidForSave }">
                                {{ totalBackOrderQuantity }}
                            </span>
                            <span class="text-gray-600">
                                / {{ selectedItem?.purchase_order_item?.quantity - (selectedItem?.quantity || 0) }}
                                items
                                recorded
                            </span>
                        </div>
                    </div>
                    <PrimaryButton @click="attemptCloseModal" :disabled="form.status === 'approved'">Save and Exit</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Location Modal -->
        <Modal :show="showLocationModal" @close="showLocationModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Add New Location</h2>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="new_location" value="Location Name" />
                        <input id="new_location" type="text" class="mt-1 block w-full" v-model="newLocation"
                            required />
                    </div>
                    <div>
                        <InputLabel for="warehouse_id" value="Warehouse" />
                        <Multiselect v-model="selectedWarehouse" :options="props.warehouses" :searchable="true"
                            :close-on-select="true" :show-labels="false" :allow-empty="false"
                            placeholder="Select Warehouse" track-by="id" label="name" required
                            class="multiselect-modern">
                        </Multiselect>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showLocationModal = false" :disabled="isNewLocation">Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="isNewLocation || !selectedWarehouse" @click="createLocation">
                        {{ isNewLocation ? "Creating..." : "Create Location" }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, nextTick, watch } from 'vue';
import axios from 'axios';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import moment from 'moment';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

import { useToast } from 'vue-toastification';

const toast = useToast();
const page = usePage();

const props = defineProps({
    warehouses: {
        required: true,
        type: Array
    },
    locations: {
        required: true,
        type: Array
    },
    packing_list: {
        required: true,
        type: Object
    }
})

const form = ref(props.packing_list || {});
const processing = ref(false);
const isSubmitting = ref(false);
const showBackOrderModal = ref(false);
const showLocationModal = ref(false);
const isLoading = ref(false);
const selectedItemIndex = ref(null);
const error = ref("");
const loadedLocation = ref([]);
const newLocation = ref('');
const showIncompleteBackOrderModal = ref(false);
const selectedItem = ref(null);
const backOrderRows = ref([]);
const isNewLocation = ref(false);
const selectedWarehouse = ref(null);

const hasIncompleteBackOrder = (item) => {
    if (!item?.quantity || item.quantity === item?.purchase_order_item?.quantity) return false;
    const mismatches = item.purchase_order_item.quantity - item.quantity;
    const totalDifferences = (item.differences || []).reduce(
        (total, diff) => total + (parseInt(diff?.quantity) || 0), 0
    );
    return totalDifferences !== mismatches;
};

// Add New Location option constant
const ADD_NEW_LOCATION_OPTION = {
    isAddNew: true,
    location: 'Add New Location'
};

const hasNotApprovedItems = computed(() => {
    return form.value?.items?.some(item => item.status != 'approved') ?? false;
});

const subTotal = computed(() => {
    return form.value?.items?.reduce((sum, i) => sum + i.total_cost || 0, 0) || 0;
});

const totalExistingDifferences = computed(() => {
    if (!selectedItem.value?.differences) return 0;
    return selectedItem.value.differences.reduce((total, diff) => total + (parseInt(diff.quantity) || 0), 0);
});

const actualMismatches = computed(() => {
    if (!selectedItem.value) return 0;
    return selectedItem.value.purchase_order_item.quantity - (selectedItem.value.quantity || 0);
});

const missingQuantity = computed(() => {
    if (!selectedItem.value) return 0;
    return selectedItem.value.purchase_order_item.quantity - (selectedItem.value.quantity || 0);
});

const allocatedQuantity = computed(() => {
    return backOrderRows.value.reduce((total, row) => total + (parseInt(row.quantity) || 0), 0);
});

const remainingToAllocate = computed(() => {
    if (!selectedItem.value) return 0;
    const total = selectedItem.value.purchase_order_item.quantity - (selectedItem.value.quantity || 0);
    return total - allocatedQuantity.value;
});

const totalBackOrderQuantity = computed(() => {
    return backOrderRows.value.reduce((total, row) => total + (parseInt(row.quantity) || 0), 0);
});

const isValidForSave = computed(() => {
    if (!selectedItem.value) return false;
    const missingQty = selectedItem.value.quantity - (selectedItem.value.quantity || 0);
    return totalBackOrderQuantity.value <= missingQty;
});

const canAddMoreRows = computed(() => {
    return remainingToAllocate.value > 0;
});

const hasAllApproved = computed(() => {
    return form.value?.status === 'approved';
});

const hasPendingItems = computed(() => {
    return form.value?.status === 'pending';
});

const hasReviewedItems = computed(() => {
    return form.value?.status === 'reviewed';
});

const hasRejected = computed(() => {
    return form.value?.status === 'rejected';
});

onMounted(async () => {
    // First create a reactive form object
    form.value = props.packing_list;
    form.value.pk_date = moment(form.value.pk_date).format('YYYY-MM-DD');

    // Load locations for existing items that have warehouses selected
    const existingWarehouses = new Set();

    form.value.items?.forEach(item => {
        if (item.warehouse?.name) {
            existingWarehouses.add(item.warehouse.name);
        }
    });

    // Load locations for all existing warehouses
    for (const warehouseName of existingWarehouses) {
        await loadLocationsByWarehouse(warehouseName);
    }
});

function handleWarehouseSelect(index, selected) {
    form.value.items[index].warehouse_id = selected.id;
    form.value.items[index].warehouse = selected;

    // Reset location when warehouse changes
    form.value.items[index].location = null;

    // Load locations for the selected warehouse
    if (selected && selected.name) {
        loadLocationsByWarehouse(selected.name);
    }
}

function hadleLocationSelect(index, selected) {
    console.log(selected);
    if (selected === 'Add new location') {
        // Check if warehouse is selected
        if (!form.value.items[index].warehouse_id) {
            toast.error('Please select a warehouse first');
            return;
        }

        selectedItemIndex.value = index;
        // Pre-select the warehouse in the modal based on the item's warehouse
        selectedWarehouse.value = form.value.items[index].warehouse;
        showLocationModal.value = true;
        return;
    }
    // Set the location name for backend (packing list items use location name, not location_id)
    form.value.items[index].location = selected;
}

function closeLocationModal() {
    showLocationModal.value = false;
    newLocation.value = '';
    selectedWarehouse.value = null;
}

async function createLocation() {
    if (!newLocation.value) {
        toast.error('Please enter a location name');
        return;
    }

    if (!selectedWarehouse.value) {
        toast.error('Please select a warehouse');
        return;
    }

    isNewLocation.value = true;

    await axios.post(route('supplies.store-location'), {
        location: newLocation.value,
        warehouse: selectedWarehouse.value.name
    })
        .then((response) => {
            isNewLocation.value = false;
            const formattedLocation = {
                id: response.data.location.id,
                location: response.data.location.location,
                warehouse: response.data.location.warehouse
            };

            // Add to locations array
            props.locations.push(formattedLocation);

            // Update the selected item's location (store location name, not ID)
            if (selectedItemIndex.value !== null) {
                form.value.items[selectedItemIndex.value].location = formattedLocation.location;
            }
            toast.success(response.data.message);
            closeLocationModal();
        })
        .catch((error) => {
            isNewLocation.value = false;
            toast.error(error.response?.data || 'An error occurred while adding the location');
        });
}

function handleReceivedQuantityChange(index) {
    const item = form.value.items[index];
    // Ensure received quantity doesn't exceed total quantity
    if (item.quantity > item.purchase_order_item?.quantity) {
        item.quantity = item.purchase_order_item?.quantity;
    }
    calculateTotal(index);
}

function calculateTotal(index) {
    const item = form.value.items[index];
    item.total_cost = item.quantity * item.unit_cost;
}

function calculateMismatches(item) {
    if (!item.purchase_order_item?.quantity || !item.quantity) return 0;
    return item.purchase_order_item?.quantity - item.quantity;
}

function calculateFulfillmentRate(item) {
    if (!item.purchase_order_item?.quantity || !item.quantity) return 0;
    const rate = (item.quantity / item.purchase_order_item?.quantity) * 100;
    return rate.toFixed(2);
}

// Format date to YYYY-MM-DD for HTML date inputs
function formatDateForInput(dateString) {
    if (!dateString) return "";

    // If it's already in YYYY-MM-DD format, return as is
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
        return dateString;
    }

    // Parse the date and format it as YYYY-MM-DD
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return ""; // Invalid date

    return date.toISOString().split("T")[0];
}

function openBackOrderModal(index) {
    const item = form.value.items[index];
    if (!item) return;

    selectedItem.value = item;

    // Initialize backOrderRows with existing differences or a new row
    backOrderRows.value = item.differences?.length > 0
        ? [...item.differences] // Create a copy of existing differences
        : [{
            id: null,
            quantity: 0,
            status: 'Missing',
            notes: ''
        }];

    showBackOrderModal.value = true;
}

async function loadLocationsByWarehouse(warehouseName) {
    try {
        const response = await axios.get(route('inventories.getLocations'), {
            params: { warehouse: warehouseName }
        });

        loadedLocation.value = response.data;
        // Normalize existing form locations after new locations are loaded
        normalizeFormLocations();
    } catch (error) {
        console.error('Error loading locations:', error);
        toast.error('Failed to load locations');
        loadedLocation.value = [];
    }
}

const syncBackOrdersWithDifferences = () => {
    if (!selectedItem.value) return;

    // Filter out rows with zero quantity
    const validRows = backOrderRows.value.filter(row => parseInt(row.quantity) > 0);

    // Update the differences array
    selectedItem.value.differences = validRows.map(row => ({
        id: row.id,
        quantity: parseInt(row.quantity),
        status: row.status,
        notes: row.notes
    }));

    const itemIndex = form.value.items.findIndex(item => item === selectedItem.value);
    if (itemIndex === -1) return;

    // Update the differences array with current back orders
    form.value.items[itemIndex].differences = backOrderRows.value.map(row => ({
        id: row.id ?? null,
        quantity: parseInt(row.quantity) || 0,
        status: row.status,
        notes: row.notes
    }));
};

const validateBackOrderQuantities = () => {
    error.value = "";

    // First pass: validate all quantities
    const invalidRow = backOrderRows.value.find(row => {
        const qty = parseFloat(row.quantity);
        return qty !== null && (qty <= 0 || isNaN(qty));
    });

    if (invalidRow) {
        error.value = "Back order quantities must be greater than zero";
        return false;
    }

    // Check if first row has a valid quantity
    if (!backOrderRows.value[0]?.quantity || parseFloat(backOrderRows.value[0].quantity) <= 0) {
        error.value = "The first back order row must have a valid quantity";
        return false;
    }

    // Calculate total differences and validate against mismatches
    const totalDifferences = backOrderRows.value.reduce(
        (total, row) => total + (parseFloat(row.quantity) || 0),
        0
    );

    if (totalDifferences > actualMismatches.value) {
        error.value = `Total back order quantities (${totalDifferences}) cannot exceed the actual mismatches (${actualMismatches.value})`;
        return false;
    }

    return true;

    // // Second pass: clean up empty rows except the last one
    // const newRows = backOrderRows.value.filter((row, index) => {
    //     if (index === backOrderRows.value.length - 1) return true; // Always keep last row
    //     return parseInt(row.quantity) > 0; // Remove other empty rows
    // });

    // // Update the array if rows were removed
    // if (newRows.length !== backOrderRows.value.length) {
    //     backOrderRows.value = newRows;
    // }

    // // Ensure at least one row exists
    // if (backOrderRows.value.length === 0 && remaining > 0) {
    //     addBackOrderRow();
    // }

    // After validation, sync with differences array
    syncBackOrdersWithDifferences();
};


const attemptCloseModal = () => {
    if (!selectedItem.value) {
        error.value = "";
        closeBackOrderModal();
        return;
    }

    // Check for any zero or invalid quantities
    const invalidRow = backOrderRows.value.find(row => {
        const qty = parseFloat(row.quantity);
        return qty !== null && (qty <= 0 || isNaN(qty));
    });

    if (invalidRow) {
        error.value = "All back order quantities must be greater than zero";
        return;
    }

    // Check if first row has a valid quantity
    const firstRow = backOrderRows.value[0];
    if (!firstRow || !firstRow.quantity || parseFloat(firstRow.quantity) <= 0) {
        error.value = 'Please enter a valid quantity for the first back order item';
        return;
    }

    const totalDifferences = backOrderRows.value.reduce((total, row) => total + (parseFloat(row.quantity) || 0), 0);
    const expectedMismatches = selectedItem.value.purchase_order_item?.quantity - (selectedItem.value.quantity || 0);

    if (totalDifferences !== expectedMismatches) {
        showIncompleteBackOrderModal.value = true;
        error.value = 'Please record all mismatched quantities before closing';
        return;
    }

    // Sync differences before closing
    syncBackOrdersWithDifferences();
    toast.success('All mismatches have been recorded');
    closeBackOrderModal();
};

const closeBackOrderModal = () => {
    showBackOrderModal.value = false;

    // If we have a selected item and it has differences, remove it from pending items
    if (selectedItem.value?.differences?.length > 0) {
        const itemIndex = pendingIncompleteItems.value.findIndex(i => i.id === selectedItem.value.id);
        if (itemIndex !== -1) {
            pendingIncompleteItems.value.splice(itemIndex, 1);
            // If there are more pending items, show the dialog again after a short delay
            if (pendingIncompleteItems.value.length > 0) {
                setTimeout(() => {
                    checkAndHandleIncompleteItems();
                }, 500);
            }
        }
    }

    selectedItem.value = null;
    backOrderRows.value = [];
}

const onBackOrderSaved = (item) => {
    // Remove the item from pending incomplete items if it now has differences
    if (item.differences && item.differences.length > 0) {
        const itemIndex = pendingIncompleteItems.value.findIndex(i => i.id === item.id);
        if (itemIndex !== -1) {
            pendingIncompleteItems.value.splice(itemIndex, 1);
        }
    }

    // If there are more pending items, show the dialog again
    if (pendingIncompleteItems.value.length > 0) {
        checkAndHandleIncompleteItems();
    }
};

// Track incomplete items that need back orders
const pendingIncompleteItems = ref([]);

const checkAndHandleIncompleteItems = async () => {
    // Check for incomplete items (received quantity less than expected)
    const incompleteItems = form.value.items.filter(item => {
        if (!item.quantity || item.quantity == item.purchase_order_item.quantity) return false;
        const mismatches = item.purchase_order_item.quantity - item.quantity;
        const totalDifferences = (item.differences || []).reduce(
            (total, diff) => total + (parseInt(diff?.quantity) || 0), 0
        );
        return totalDifferences !== mismatches;
    });

    if (incompleteItems.length > 0) {
        // Initialize pending items if not already set
        if (pendingIncompleteItems.value.length === 0) {
            pendingIncompleteItems.value = [...incompleteItems];
        }

        const itemsList = pendingIncompleteItems.value.map(item =>
            `${item.product.name} (Expected: ${item.purchase_order_item.quantity}, Received: ${item.quantity})`
        ).join('\n');

        const result = await Swal.fire({
            title: 'Incomplete Back Orders',
            html: `The following items still need back orders:<br><br><pre>${itemsList}</pre><br>Please record back orders for these items before proceeding.`,
            icon: 'warning',
            confirmButtonText: 'Continue Recording',
            showCancelButton: true,
            cancelButtonText: 'Cancel'
        });

        if (result.isConfirmed) {
            // Find the next incomplete item that hasn't been handled
            const nextIncompleteIndex = form.value.items.findIndex(item =>
                pendingIncompleteItems.value.includes(item)
            );
            if (nextIncompleteIndex !== -1) {
                showIncompleteBackOrderModal.value = true;
                openBackOrderModal(nextIncompleteIndex);
            }
        }
        return false;
    }
    return true;
};

function formatDate(date) {
    return moment(date).format("DD/MM/YYYY");
}
const submit = async () => {
    if (!form.value?.items?.length) {
        toast.error('No items to submit');
        return;
    }

    // Prepare form data - ensure location is a string
    const preparedForm = {
        ...form.value,
        items: form.value.items.map(item => ({
            ...item,
            location: item.location && typeof item.location === 'object' ? item.location.location : item.location
        }))
    };

    console.log(preparedForm);

    // Check for incomplete back orders with enhanced validation
    const incompleteItems = preparedForm.items.filter(item => !validateMismatchRecording(item));
    if (incompleteItems.length > 0) {
        const itemNames = incompleteItems.map(item => item.product?.name || 'Unknown Item').join(', ');
        toast.error(
            `Please record all mismatches for: ${itemNames}`
        );
        return;
    }

    // Check for incomplete items first
    const canProceed = await checkAndHandleIncompleteItems();
    if (!canProceed) return;

    // Reset pending items since we can proceed
    pendingIncompleteItems.value = [];

    // Show confirmation dialog
    const confirm = await Swal.fire({
        title: 'Are you sure?',
        text: "You want to update this packing list?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    });

    if (!confirm.isConfirmed) return;

    isSubmitting.value = true;

    // Format dates properly for all items
    preparedForm.items.forEach((item) => {
        if (item.expire_date) {
            item.expire_date = formatDateForInput(item.expire_date);
        }
    });

    console.log(preparedForm);

    await axios.post(route('supplies.packing-list.update'), preparedForm)
        .then((response) => {
            isSubmitting.value = false;
            console.log(response.data);
            Swal.fire({
                title: 'Success!',
                text: response.data,
                icon: 'success',
                confirmButtonColor: '#10B981',
            })
                .then(() => {
                    router.get(route('supplies.packing-list.edit', form.value.id));
                });
        })
        .catch((error) => {
            isSubmitting.value = false;
            console.error('Failed to update packing list:', error);
            toast.error(error.response?.data || 'Failed to update packing list');
        });
}

const addBackOrderRow = () => {
    backOrderRows.value.push({
        id: null,
        quantity: 0,
        status: 'Missing',
        notes: ''
    });
};

const removeBackOrderRow = async (index, item) => {
    try {
        if (item.id) {
            // If item has an ID, delete it from the server first
            const response = await axios.get(route('supplies.deletePackingListDiff', item.id));
            if (!response.data.message) {
                throw new Error(response.data || 'Failed to delete back order');
            }
        }

        // Remove from local array
        const newRows = [...backOrderRows.value];
        if (newRows[index]) {
            newRows.splice(index, 1);
            backOrderRows.value = newRows;
        }

        // After successful removal, sync the differences
        await nextTick();
        syncBackOrdersWithDifferences();

        // Check if we need to add a new row
        await nextTick();
        const remaining = remainingToAllocate.value;
        if (remaining > 0 && backOrderRows.value.length === 0) {
            addBackOrderRow();
        }
    } catch (error) {
        console.log(error);
        toast.error(error.response?.data || 'Error removing back order');
    }
};

const allStatuses = ['Missing', 'Damaged', 'Lost', 'Expired', 'Low quality'];

const availableStatuses = computed(() => {
    const usedStatuses = new Set(backOrderRows.value.map(row => row.status));
    return allStatuses.filter(status => !usedStatuses.has(status));
});

const isReviewing = ref(false);

async function reviewPackingList() {
    
    const confirm = await Swal.fire({
        title: 'Review Packing List',
        text: 'Are you sure you want to mark these items as reviewed?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, review it!'
    });

    if (confirm.isConfirmed) {
        console.log('User confirmed review');
        
        // Check if all required fields are present
        const incompleteItems = form.value.items?.filter(item => !hasRequiredFields(item)) || [];
        if (incompleteItems.length > 0) {
            const itemNames = incompleteItems.map(item => item.product?.name || 'Unknown Item').join(', ');
            toast.error(`Please complete all required fields for: ${itemNames}`);
            return;
        }
        
        isReviewing.value = true;
        try {
            console.log('Sending review request to:', route('supplies.reviewPK'));
            console.log('Form data being sent:', {
                id: form.value.id,
                status: 'reviewed',
                items: form.value.items
            });
            const response = await axios.post(route('supplies.reviewPK'), {
                id: form.value.id,
                status: 'reviewed',
            });
            if (response.data.reviewed_at) {
                form.value.reviewed_at = response.data.reviewed_at;
                form.value.reviewed_by = response.data.reviewed_by;
                form.value.status = 'reviewed';
            }

            await Swal.fire({
                title: 'Success!',
                text: 'Packing list has been marked for review',
                icon: 'success',
                confirmButtonColor: '#10B981',
            });

            router.get(route('supplies.packing-list.edit', form.value.id), {}, {
                preserveScroll: false,
                preserveState: false,
                only: ['packing_list', 'warehouses', 'locations'],
            });

        } catch (error) {
            console.error('Review error:', error);
            console.error('Error response:', error.response);
            console.error('Error message:', error.message);
            
            if (error.response?.data?.message) {
                toast.error(error.response.data.message);
            } else if (error.response?.data) {
                toast.error(error.response.data);
            } else if (error.message) {
                toast.error(error.message);
            } else {
                toast.error('An error occurred while reviewing the items');
            }
        } finally {
            isReviewing.value = false;
        }
    }
}

const isApproving = ref(false);

async function approvePackingList() {    
    const confirm = await Swal.fire({
        title: 'Approve Packing List',
        text: 'Are you sure you want to approve these items?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    });

    if (confirm.isConfirmed) {
        console.log('User confirmed approve');
        isApproving.value = true;
        try {
            console.log('Sending approve request to:', route('supplies.approvePK'));
            const response = await axios.post(route('supplies.approvePK'), {
                id: form.value.id,
                status: 'approved',
                items: form.value.items
            });
            
            console.log('Approve response:', response.data);

            await Swal.fire({
                title: 'Success!',
                text: 'Items have been approved',
                icon: 'success',
                confirmButtonColor: '#10B981',
            });

            // Refresh the page with updated data
            router.get(route('supplies.packing-list.edit', form.value.id), {}, {
                preserveScroll: false,
                preserveState: false,
                only: ['packing_list', 'warehouses', 'locations']
            });


        } catch (error) {
            console.error('Approve error:', error);
            toast.error(error.response?.data || 'An error occurred while approving the items');
        } finally {
            isApproving.value = false;
        }
    }
}

const isRejecting = ref(false);

async function rejectPackingList() {
    if (isRejecting.value) return;

    const { value: reason } = await Swal.fire({
        title: 'Rejection Reason',
        input: 'textarea',
        inputLabel: 'Please provide a reason for rejection',
        inputPlaceholder: 'Type your reason here...',
        inputAttributes: { 'aria-label': 'Type your reason here' },
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Reject',
        inputValidator: (value) => {
            if (!value || !String(value).trim()) {
                return 'You need to provide a reason!';
            }
        },
    });

    if (!reason) return;

    isRejecting.value = true;
    try {
        const response = await axios.post(route('supplies.rejectPK'), {
            id: form.value.id,
            status: 'rejected',
            rejection_reason: reason.trim(),
        });
        form.value.rejected_at = response.data.rejected_at;
        form.value.rejected_by = response.data.rejected_by;
        form.value.rejection_reason = reason.trim();
        form.value.status = 'rejected';

        await Swal.fire({
            title: 'Success!',
            text: 'Packing list has been rejected',
            icon: 'success',
            confirmButtonColor: '#10B981',
        });

        router.get(route('supplies.packing-list.edit', form.value.id), {}, {
            preserveScroll: false,
            preserveState: false,
            only: ['packing_list', 'warehouses', 'locations'],
        });
    } catch (error) {
        console.error('Reject error:', error);
        toast.error(error.response?.data || 'An error occurred while rejecting the packing list');
    } finally {
        isRejecting.value = false;
    }
}

function getLocationForItem(item) {
    // If item.location is a string, find the matching location object
    if (typeof item.location === 'string' && item.location) {
        const location = loadedLocation.value.find(loc => loc.location === item.location);
        if (location) {
            return location;
        }
        // If not found in loadedLocation, create a temporary object for display
        return {
            location: item.location,
            warehouse: item.warehouse?.name || '',
            id: 'temp-' + item.location
        };
    }

    // If item.location is already an object, return it
    if (item.location && typeof item.location === 'object') {
        return item.location;
    }

    return null;
}

// Function to check if item has all required fields
const hasRequiredFields = (item) => {
    return item.quantity && 
           item.warehouse_id && 
           item.location && 
           item.batch_number && 
           item.expire_date && 
           item.uom;
};

// Enhanced validation to check if all mismatches are properly recorded
const validateMismatchRecording = (item) => {
    if (!item?.quantity || item.quantity === item?.purchase_order_item?.quantity)
        return true; // No mismatch, so it's valid

    const mismatchQuantity = item.purchase_order_item.quantity - item.quantity;
    const recordedBackOrderQuantity = (item.differences || []).reduce(
        (total, diff) => total + (parseInt(diff?.quantity) || 0),
        0
    );

    return recordedBackOrderQuantity === mismatchQuantity;
};

// Get mismatch status for display
const getMismatchStatus = (item) => {
    if (!item?.quantity || item.quantity === item?.purchase_order_item?.quantity)
        return { status: 'none', message: 'No mismatch' };

    const mismatchQuantity = item.purchase_order_item.quantity - item.quantity;
    const recordedBackOrderQuantity = (item.differences || []).reduce(
        (total, diff) => total + (parseInt(diff?.quantity) || 0),
        0
    );

    if (recordedBackOrderQuantity === 0) {
        return { status: 'unrecorded', message: `${mismatchQuantity} mismatch not recorded` };
    } else if (recordedBackOrderQuantity < mismatchQuantity) {
        return { status: 'partial', message: `${mismatchQuantity - recordedBackOrderQuantity} remaining` };
    } else if (recordedBackOrderQuantity === mismatchQuantity) {
        return { status: 'complete', message: 'All mismatches recorded' };
    } else {
        return { status: 'excess', message: `${recordedBackOrderQuantity - mismatchQuantity} excess recorded` };
    }
};

// Enhanced canSubmit computed property
const canSubmit = computed(() => {
    if (!form.value?.items?.length) return false;
    
    // Check if all items have basic required fields
    const hasAllRequiredFields = form.value.items.every(hasRequiredFields);
    
    if (!hasAllRequiredFields) return false;
    
    // Check if all items have their mismatches properly recorded
    return form.value.items.every(validateMismatchRecording);
});

// Enhanced submitButtonTitle computed property
const submitButtonTitle = computed(() => {
    if (!form.value?.items?.length) return "No items to submit";
    
    // Check for missing required fields
    const itemsWithMissingFields = form.value.items.filter(item => !hasRequiredFields(item));
    if (itemsWithMissingFields.length > 0) {
        const itemNames = itemsWithMissingFields.map(item => item.product?.name || 'Unknown Item').join(', ');
        return `Please complete all required fields for: ${itemNames}`;
    }
    
    // Check for incomplete mismatch recording
    const incompleteItems = form.value.items.filter(item => !validateMismatchRecording(item));
    if (incompleteItems.length > 0) {
        const itemNames = incompleteItems.map(item => item.product?.name || 'Unknown Item').join(', ');
        return `Please record all mismatches for: ${itemNames}`;
    }
    
    return "";
});

// Function to normalize location data for multiselect
const normalizeLocationData = (locationData) => {
    if (!locationData) return null;
    if (typeof locationData === 'string') {
        // If it's a string, find the corresponding object in loadedLocation
        const locationObj = loadedLocation.value.find(loc => loc.location === locationData);
        return locationObj || { location: locationData };
    }
    return locationData;
};

// Function to normalize all items' location data
const normalizeFormLocations = () => {
    if (!form.value?.items) return;
    form.value.items.forEach(item => {
        if (item.location) {
            item.location = normalizeLocationData(item.location);
        }
    });
};

// Watch for changes in loadedLocation and normalize form locations
watch(loadedLocation, () => {
    normalizeFormLocations();
}, { deep: true });

// Watch for form changes to update validation status in real-time
watch(() => form.value?.items, () => {
    if (form.value?.items) {
        form.value.items.forEach(item => {
            // Update validation status in real-time
            item.hasValidationError = !hasRequiredFields(item) || !validateMismatchRecording(item);
        });
    }
}, { deep: true });
</script>