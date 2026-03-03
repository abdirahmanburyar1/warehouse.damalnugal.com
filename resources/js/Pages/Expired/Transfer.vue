<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import axios from "axios";
import { useToast } from "vue-toastification";
import Swal from "sweetalert2";
import moment from "moment";

const toast = useToast();

const props = defineProps({
    inventory: Object,
    warehouses: {
        type: Array,
        default: () => [],
    },
    facilities: {
        type: Array,
        default: () => [],
    },
    transferID: String,
    reasons: {
        type: Array,
        default: () => [],
    },
});

const destinationType = ref("warehouse");
const loading = ref(false);
const selectedDestination = ref(null);
const quantityToTransfer = ref("");
const transfer_date = ref(moment().format("YYYY-MM-DD"));
const transfer_reason = ref(null);

// Modal state for adding new reason
const showAddReasonModal = ref(false);
const newReasonName = ref("");
const addingReason = ref(false);

const form = ref({
    source_type: "warehouse", // Always warehouse for expired items
    source_id: props.inventory?.warehouse_id, // Automatically set from inventory
    destination_type: "warehouse",
    destination_id: null,
    transfer_date: moment().format("YYYY-MM-DD"),
    transferID: props.transferID,
    items: [
        {
            product_id: props.inventory?.product_id,
            quantity: 0,
            details: [
                {
                    id: props.inventory?.id,
                    quantity_to_transfer: 0,
                    transfer_reason: "",
                }
            ],
        },
    ],
});

const errors = ref({});

// Normalize options to { id, name } for Multiselect (track-by="id", label="name")
function toOptionsList(arrOrObj) {
    if (!arrOrObj) return [];
    const list = Array.isArray(arrOrObj) ? arrOrObj : Object.values(arrOrObj);
    return list.map((item) => ({ id: item.id, name: item.name ?? '' }));
}
const normalizedWarehouses = computed(() => toOptionsList(props.warehouses));
const normalizedFacilities = computed(() => toOptionsList(props.facilities));

const destinationOptions = computed(() => {
    return destinationType.value === "warehouse"
        ? normalizedWarehouses.value
        : normalizedFacilities.value;
});

// Filter out the source warehouse only when destination type is warehouse (so we don't exclude facilities whose id happens to match warehouse_id)
const filteredDestinationOptions = computed(() => {
    const options = destinationOptions.value;
    if (destinationType.value === 'warehouse') {
        return options.filter((item) => item.id !== props.inventory?.warehouse_id);
    }
    return options;
});

// Add "Add" option to reasons dropdown (first in the list)
const reasonsWithAddOption = computed(() => {
    return [
        { name: '+ Add New Reason', isAddOption: true },
        ...props.reasons.map(reason => ({ name: reason }))
    ];
});

const handleDestinationSelect = (selected) => {
    form.value.destination_id = selected ? selected.id : null;
    selectedDestination.value = selected;
    errors.value.destination_id = null;
};

const handleReasonSelect = (selected) => {
    if (selected && selected.isAddOption) {
        // Open modal for adding new reason
        openAddReasonModal();
        // Reset the selection since we're opening modal
        transfer_reason.value = null;
    } else {
        // Normal reason selection
        transfer_reason.value = selected;
        errors.value.transfer_reason = null;
    }
};

const validateForm = () => {
    errors.value = {};
    let isValid = true;

    // Validate destination
    if (!form.value.destination_id) {
        errors.value.destination_id = "Please select a destination.";
        isValid = false;
    }

    // Check if source and destination are the same
    if (form.value.source_id === form.value.destination_id) {
        errors.value.destination_id = "Source and destination cannot be the same.";
        isValid = false;
    }

    // Validate quantity
    if (!quantityToTransfer.value || parseInt(quantityToTransfer.value) < 1) {
        errors.value.quantity = "Quantity must be at least 1.";
        isValid = false;
    }

    if (parseInt(quantityToTransfer.value) > props.inventory.quantity) {
        errors.value.quantity = `Maximum available quantity is ${props.inventory.quantity}.`;
        isValid = false;
    }

    // Validate transfer reason
    if (!transfer_reason.value || !transfer_reason.value.name) {
        errors.value.transfer_reason = "Please select a transfer reason.";
        isValid = false;
    }

    return isValid;
};

const formatDate = (date) => {
    return moment(date).format('DD/MM/YYYY');
};

const isExpiringSoon = (date) => {
    const expiryDate = moment(date);
    const now = moment();
    const daysUntilExpiry = expiryDate.diff(now, 'days');
    return daysUntilExpiry <= 180 && daysUntilExpiry > 0;
};

const handleSubmit = async () => {
    if (!validateForm()) {
        return;
    }

    // Update form with current values
    form.value.destination_type = destinationType.value;
    form.value.transfer_date = transfer_date.value;
    form.value.items[0].quantity = parseInt(quantityToTransfer.value);
    form.value.items[0].details[0].quantity_to_transfer = parseInt(quantityToTransfer.value);
    form.value.items[0].details[0].transfer_reason = transfer_reason.value ? transfer_reason.value.name : "";

    Swal.fire({
        title: "Confirm Transfer",
        html: `
            <div class="text-left">
                <p><strong>Item:</strong> ${props.inventory.product.name}</p>
                <p><strong>Quantity:</strong> ${quantityToTransfer.value}</p>
                <p><strong>Destination:</strong> ${selectedDestination.value.name}</p>
            </div>
        `,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, transfer it!",
        cancelButtonText: "Cancel",
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        loading.value = true;

        await axios
            .post(route("transfers.store"), form.value)
            .then((response) => {
                loading.value = false;
                console.log(response.data);
                Swal.fire({
                    title: "Success!",
                    text: "Transfer completed successfully",
                    icon: "success",
                    confirmButtonText: "OK",
                }).then(() => {
                    router.get(route('expired.index'), {}, {preserveScroll: true});
                });
            })
            .catch((error) => {
                loading.value = false;
                console.log(error);
                Swal.fire({
                    title: "Error!",
                    text: error.response?.data || "Failed to transfer",
                    icon: "error",
                    confirmButtonText: "OK",
                });
            });
    });
};

// Function to handle adding new reason
const handleAddReason = async () => {
    if (!newReasonName.value.trim()) {
        toast.error("Please enter a reason name");
        return;
    }

    addingReason.value = true;
    try {
        const response = await axios.post(route('reasons.store'), {
            name: newReasonName.value.trim()
        });
        
        // Set the new reason as selected by creating an object with the name
        transfer_reason.value = { name: response.data };
        
        // Close modal and reset form
        showAddReasonModal.value = false;
        newReasonName.value = "";
        
        toast.success("Reason added successfully");
    } catch (error) {
        console.error('Error adding reason:', error);
        toast.error(error.response?.data || "Failed to add reason");
    } finally {
        addingReason.value = false;
    }
};

// Function to open add reason modal
const openAddReasonModal = () => {
    showAddReasonModal.value = true;
    newReasonName.value = "";
};
</script>

<template>
    <AuthenticatedLayout
        title="Transfer Expired Item"
        description="Transfer expired or soon to be expired items to warehouse or facility"
        img="/assets/images/transfer.png"
    >
        <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <!-- Header -->
                <div class="px-8 py-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Transfer</h1>
                            <p class="mt-1 text-sm text-gray-600">Transfer ID: {{ props.transferID }}</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900">Transfer Date</p>
                                <p class="text-sm text-gray-600">{{ formatDate(transfer_date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="handleSubmit" class="px-8 py-6">
                    <!-- Transfer Information and Destination Section -->
                    <div class="mb-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Transfer Information Section -->
                            <div>
                                <div class="flex items-center space-x-2 mb-6">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Transfer Information</h3>
                                </div>

                                <div class="grid grid-cols-1 gap-6">
                                    <!-- Transfer Date -->
                                    <div>
                                        <InputLabel for="transfer_date" value="Transfer Date" class="text-sm font-medium text-gray-700" />
                                        <input
                                            id="transfer_date"
                                            type="date"
                                            v-model="transfer_date"
                                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Destination Section -->
                            <div>
                                <div class="flex items-center space-x-2 mb-6">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h14m-6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Transfer To</h3>
                                </div>
                        
                        <!-- Destination Type Selection -->
                        <div class="mb-6">
                            <InputLabel value="Destination Type" class="text-sm font-medium text-gray-700 mb-3" />
                            <div class="flex space-x-4">
                                <label class="flex items-center px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                                       :class="destinationType === 'warehouse' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300'">
                                    <input
                                        type="radio"
                                        v-model="destinationType"
                                        value="warehouse"
                                        class="sr-only"
                                    />
                                    <div class="w-4 h-4 rounded-full border-2 mr-2 flex items-center justify-center"
                                         :class="destinationType === 'warehouse' ? 'border-blue-500' : 'border-gray-400'">
                                        <div v-if="destinationType === 'warehouse'" class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    </div>
                                    <span class="font-medium">Warehouse</span>
                                </label>
                                <label class="flex items-center px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                                       :class="destinationType === 'facility' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300'">
                                    <input
                                        type="radio"
                                        v-model="destinationType"
                                        value="facility"
                                        class="sr-only"
                                    />
                                    <div class="w-4 h-4 rounded-full border-2 mr-2 flex items-center justify-center"
                                         :class="destinationType === 'facility' ? 'border-blue-500' : 'border-gray-400'">
                                        <div v-if="destinationType === 'facility'" class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    </div>
                                    <span class="font-medium">Facility</span>
                                </label>
                            </div>
                        </div>

                        <!-- Destination Selection -->
                        <div>
                            <InputLabel value="Select Destination" class="text-sm font-medium text-gray-700 mb-2" />
                            <Multiselect
                                v-model="selectedDestination"
                                :options="filteredDestinationOptions"
                                :searchable="true"
                                :close-on-select="true"
                                :show-labels="false"
                                :allow-empty="true"
                                :placeholder="`Select destination ${destinationType === 'warehouse' ? 'warehouse' : 'facility'}`"
                                track-by="id"
                                label="name"
                                @select="handleDestinationSelect"
                                :class="{ 'border-red-500': errors.destination_id }"
                                @open="errors.destination_id = null"
                            >
                                <template v-slot:option="{ option }">
                                    <span>{{ option.name }}</span>
                                </template>
                            </Multiselect>
                            <InputError :message="errors.destination_id" class="mt-2" />
                        </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table Section -->
                    <div class="mb-4">
                        <table class="w-full table-sm">
                            <thead>
                                <tr style="background-color: #EFF6FF;">
                                    <th class="text-xs min-w-[120px] px-3 py-3 text-left text-xs font-bold uppercase border-b rounded-tl-lg" rowspan="2"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        Item Name
                                    </th>
                                    <th class="text-xs px-3 py-3 text-left text-xs font-bold uppercase border-b" rowspan="2"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        Category
                                    </th>
                                    <th class="text-xs px-3 py-3 text-left text-xs font-bold uppercase border-b" rowspan="2"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        UoM
                                    </th>
                                    <th class="text-xs px-3 py-3 text-center text-xs font-bold uppercase border-b" rowspan="2"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        Total Quantity on Hand Per Unit
                                    </th>
                                    <th class="text-xs px-3 py-3 text-center text-xs font-bold uppercase border-b" colspan="3"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        Item details
                                    </th>
                                    <th class="text-xs min-w-[150px] px-3 py-3 text-left text-xs font-bold uppercase border-b" rowspan="2"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        Reasons for Transfers
                                    </th>
                                    <th class="text-xs min-w-[110px] px-3 py-3 text-left text-xs font-bold uppercase border-b" rowspan="2"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        Quantity to be transferred
                                    </th>
                                    <th class="text-xs px-3 py-3 text-left text-xs font-bold uppercase border-b rounded-tr-lg" rowspan="2"
                                        style="color: #979ECD; border-bottom: 2px solid #B7C6E6;">
                                        Total Quantity to be transferred
                                    </th>
                                </tr>
                                <tr style="background-color: #EFF6FF;">
                                    <th class="text-xs px-3 py-3 text-xs font-bold uppercase border text-center"
                                        style="color: #979ECD; border: 1px solid #B7C6E6;">
                                        QTY
                                    </th>
                                    <th class="text-xs px-3 py-3 text-xs font-bold uppercase border text-center"
                                        style="color: #979ECD; border: 1px solid #B7C6E6;">
                                        Batch Number
                                    </th>
                                    <th class="px-3 py-3 text-xs font-bold uppercase border text-center "
                                        style="color: #979ECD; border: 1px solid #B7C6E6;">
                                        Expiry Date
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200">
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <!-- Item Name -->
                                    <td class="min-w-[200px] px-3 py-3 text-sm font-medium text-gray-900 border-b align-top" style="border-bottom: 1px solid #B7C6E6;">
                                        {{ props.inventory.product.name }}
                                    </td>

                                    <!-- Category -->
                                    <td class="px-3 py-3 text-sm text-gray-700 border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                        {{ props.inventory.product?.category?.name || '' }}
                                    </td>

                                    <!-- UoM -->
                                    <td class="px-3 py-3 text-sm text-gray-700 border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                        {{ props.inventory.uom || '' }}
                                    </td>

                                    <!-- Total Quantity on Hand Per Unit -->
                                    <td class="px-3 py-3 text-sm text-center text-gray-700 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                        {{ (props.inventory.quantity && !isNaN(props.inventory.quantity)) ? props.inventory.quantity : 0 }}
                                    </td>

                                    <!-- Item Details Columns -->
                                    <!-- Quantity -->
                                    <td class="px-3 py-3 text-sm text-center text-gray-700 border" style="border: 1px solid #B7C6E6;">
                                        {{ props.inventory.quantity }}
                                    </td>

                                    <!-- Batch Number -->
                                    <td class="px-3 py-3 text-sm text-center text-gray-700 border" style="border: 1px solid #B7C6E6;">
                                        {{ props.inventory.batch_number || '' }}
                                    </td>

                                    <!-- Expiry Date -->
                                    <td class="px-3 py-3 text-sm text-center border" style="border: 1px solid #B7C6E6;" :class="{ 'text-red-600': isExpiringSoon(props.inventory.expiry_date) }">
                                        {{ props.inventory.expiry_date ? formatDate(props.inventory.expiry_date) : '' }}
                                    </td>

                                    <!-- Reasons for Transfers – full text, dropdown closes on select, Add New Reason option -->
                                    <td class="reason-cell px-3 py-3 w-[220px] min-w-[220px] max-w-[320px] text-sm border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                        <Multiselect
                                            v-model="transfer_reason"
                                            :options="reasonsWithAddOption"
                                            :searchable="true"
                                            :close-on-select="true"
                                            :show-labels="false"
                                            :placeholder="'Select reason'"
                                            track-by="name"
                                            label="name"
                                            @select="handleReasonSelect"
                                        >
                                            <template v-slot:option="{ option }">
                                                <span :class="{ 'text-blue-600 font-semibold': option.isAddOption }">
                                                    {{ option.name }}
                                                </span>
                                            </template>
                                        </Multiselect>
                                        <InputError :message="errors.transfer_reason" class="mt-1" />
                                    </td>

                                    <!-- Quantity to be transferred -->
                                    <td class="px-3 py-3 text-sm border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                        <input
                                            type="number"
                                            v-model="quantityToTransfer"
                                            :max="props.inventory.quantity"
                                            min="0"
                                            class="w-full text-sm border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="0"
                                            :class="{ 'border-red-500': errors.quantity }"
                                        />
                                        <InputError :message="errors.quantity" class="mt-1" />
                                    </td>

                                    <!-- Total Quantity to be transferred -->
                                    <td class="px-3 py-3 text-sm text-center border-b" style="border-bottom: 1px solid #B7C6E6;">
                                        <div class="text-sm font-medium text-blue-600">
                                            {{ quantityToTransfer || 0 }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-between pt-8 border-t border-gray-200">
                        <button
                            type="button"
                            @click="router.visit(route('expired.index'))"
                            :disabled="loading"
                            class="px-8 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-all duration-200"
                            :class="{
                                'opacity-50 cursor-not-allowed': loading,
                            }"
                        >
                            Exit
                        </button>
                        <PrimaryButton 
                        
                            v-if="$page.props.auth.can.transfer_create || $page.props.auth.can.facility_create || $page.props.auth.user.isAdmin"
                            :disabled="loading" 
                            class="relative px-8 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105"
                        >
                            <span v-if="loading" class="absolute left-3">
                                <svg
                                    class="animate-spin h-5 w-5 text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                            </span>
                            <span :class="{ 'pl-7': loading }">{{
                                loading ? "Processing..." : "Create Transfer"
                            }}</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Add Reason Modal -->
    <div v-if="showAddReasonModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddReasonModal = false"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Add New Transfer Reason
                            </h3>
                            <form @submit.prevent="handleAddReason">
                                <div class="mb-4">
                                    <InputLabel for="reason_name" value="Reason Name" class="text-sm font-medium text-gray-700" />
                                    <input
                                        id="reason_name"
                                        v-model="newReasonName"
                                        type="text"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="Enter reason name"
                                        required
                                    />
                                </div>
                                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                    <button
                                        type="submit"
                                        :disabled="addingReason"
                                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span v-if="addingReason" class="flex items-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Adding...
                                        </span>
                                        <span v-else>Add Reason</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="showAddReasonModal = false"
                                        :disabled="addingReason"
                                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Reasons for Transfers: show full reason text, no truncation; dropdown closes on select; "+ Add New Reason" option */
.reason-cell .multiselect {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}
.reason-cell :deep(.multiselect__tags),
.reason-cell :deep(.multiselect__tags-wrap) {
    min-width: 0 !important;
    overflow: visible !important;
}
.reason-cell :deep(.multiselect__single),
.reason-cell :deep(.multiselect__tag),
.reason-cell :deep(.multiselect__tag span) {
    overflow: visible !important;
    text-overflow: clip !important;
    white-space: normal !important;
    word-break: normal !important;
    max-width: none !important;
}
/* Dropdown only on top when open – do not force visibility so it closes when not active */
.reason-cell :deep(.multiselect--active .multiselect__content-wrapper) {
    z-index: 99999 !important;
    position: absolute !important;
}
</style>
