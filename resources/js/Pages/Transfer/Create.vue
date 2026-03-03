<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { ref, computed, watch, onMounted, nextTick } from "vue";
import { router, usePage } from "@inertiajs/vue3";
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
    transferID: {
        type: String,
        required: true,
    },
    reasons: {
        type: Array,
        default: () => [],
    },
});

const sourceType = ref("warehouse");
const destinationType = ref("warehouse");
const loading = ref(false);
const selectedSource = ref(null);
const selectedDestination = ref(null);
const selectedInventory = ref(null);
const filteredInventories = ref([]);
const availableInventories = ref([]);
const searchQuery = ref("");
const loadingInventories = ref(false);
const currentDetailForReason = ref(null);
const transferFormRef = ref(null);
const transferDateInputRef = ref(null);

const form = ref({
    source_type: sourceType.value,
    source_id: null,
    destination_type: destinationType.value,
    destination_id: null,
    transfer_date: moment().format("YYYY-MM-DD"),
    transferID: props.transferID,
    items: [
        {
            id: null,
            product_id: "",
            product: null,
            quantity: 0,
            available_quantity: 0,
            details: [],
        },
    ],
});

const errors = ref({});

const sourceOptions = computed(() => {
    return sourceType.value === "warehouse"
        ? props.warehouses
        : props.facilities;
});

const destinationOptions = computed(() => {
    return destinationType.value === "warehouse"
        ? props.warehouses
        : props.facilities;
});

const filteredDestinationOptions = computed(() => {
    if (sourceType.value === destinationType.value && selectedSource.value) {
        return destinationOptions.value.filter(
            (item) => item.id !== selectedSource.value.id
        );
    }
    return destinationOptions.value;
});

const handleSourceSelect = (selected) => {
    selectedSource.value = selected;
    form.value.source_id = selected ? selected.id : null;
    form.value.source_type = sourceType.value;
    selectedInventory.value = null;
    if (!selected) {
        availableInventories.value = [];
        filteredInventories.value = [];
    }
    // Reset items table so selected products always match current source's inventory options
    form.value.items = [
        {
            id: null,
            product_id: null,
            product: null,
            quantity: 0,
            available_quantity: 0,
            details: [],
        },
    ];
};

const handleDestinationSelect = (selected) => {
    selectedDestination.value = selected;
    form.value.destination_id = selected ? selected.id : null;
    form.value.destination_type = destinationType.value;
};

const fetchInventories = async () => {
    if (!selectedSource.value || !form.value.source_id) {
        availableInventories.value = [];
        filteredInventories.value = [];
        return;
    }
    loadingInventories.value = true;
    filteredInventories.value = [];
    searchQuery.value = "";
    let timerInterval;
    const swalLoading = Swal.fire({
        title: "Loading Inventory",
        html: "Fetching inventory items... <b></b>",
        timer: 30000,
        timerProgressBar: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
            const timer = Swal.getPopup().querySelector("b");
            timerInterval = setInterval(() => {
                timer.textContent = `${Math.ceil(Swal.getTimerLeft() / 1000)}s`;
            }, 100);
        },
        willClose: () => {
            clearInterval(timerInterval);
        },
    });
    try {
        const response = await axios.get(route("transfers.getInventories"), {
            params: {
                source_type: sourceType.value,
                source_id: form.value.source_id,
            },
        });
        swalLoading.close();
        const data = response.data;
        const list = Array.isArray(data) ? data : (data?.data && Array.isArray(data.data) ? data.data : []);
        availableInventories.value = [...list];
        filteredInventories.value = [...list];
        loadingInventories.value = false;
    } catch (error) {
        swalLoading.close();
        loadingInventories.value = false;
        availableInventories.value = [];
        filteredInventories.value = [];
        setTimeout(() => {
            let errorMessage = "Failed to fetch inventories";
            if (error.response) {
                if (typeof error.response.data === "string") {
                    errorMessage = error.response.data;
                } else if (error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                } else if (error.response.data && error.response.data.error) {
                    errorMessage = error.response.data.error;
                } else {
                    errorMessage = `Server error (${error.response.status})`;
                }
            } else if (error.message) {
                errorMessage = error.message;
            }
            Swal.fire({
                title: "Error!",
                text: errorMessage,
                icon: "error",
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 5000,
            });
        }, 100);
    }
};

const validateForm = () => {
    errors.value = {};
    let isValid = true;
    if (!form.value.source_id) {
        errors.value.source_id = "Please select a source.";
        isValid = false;
    }
    if (!form.value.destination_id) {
        errors.value.destination_id = "Please select a destination.";
        isValid = false;
    }
    if (
        form.value.source_id === form.value.destination_id &&
        form.value.source_type === form.value.destination_type
    ) {
        errors.value.destination_id =
            "Source and destination cannot be the same.";
        isValid = false;
    }
    let hasValidItems = false;
    form.value.items.forEach((item, index) => {
        if (!item.inventory_id) {
            errors.value[`item_${index}_inventory`] =
                "Please select an inventory item.";
            isValid = false;
        }
        if (item.inventory_id && (!item.quantity || item.quantity < 1)) {
            errors.value[`item_${index}_quantity`] =
                "Quantity must be at least 1.";
            isValid = false;
        }
        if (item.inventory_id && item.quantity > item.available_quantity) {
            errors.value[
                `item_${index}_quantity`
            ] = `Maximum available quantity is ${item.available_quantity}.`;
            isValid = false;
        }
        if (
            item.inventory_id &&
            item.quantity >= 1 &&
            item.quantity <= item.available_quantity
        ) {
            hasValidItems = true;
        }
    });
    if (!hasValidItems) {
        errors.value.items =
            "At least one item must be selected with a valid quantity.";
        isValid = false;
    }
    return isValid;
};

watch(sourceType, (newValue) => {
    form.value.source_type = newValue;
    form.value.source_id = null;
    selectedSource.value = null;
    selectedInventory.value = null;
    form.value.inventory_id = null;
    availableInventories.value = [];
    filteredInventories.value = [];
});

// When source is selected, fetch available items (ensures items propagate even if @select does not fire)
watch(selectedSource, async (newVal) => {
    if (newVal?.id) {
        form.value.source_id = newVal.id;
        form.value.source_type = sourceType.value;
        await fetchInventories();
    } else {
        availableInventories.value = [];
        filteredInventories.value = [];
    }
});

watch(destinationType, (newValue) => {
    form.value.destination_type = newValue;
    form.value.destination_id = null;
    selectedDestination.value = null;
});

const submit = async () => {
    loading.value = true;
    // Only consider fulfilled rows: item selected and at least one quantity to transfer
    const fulfilledItems = form.value.items.filter(item => {
        const hasProduct = item.product_id != null && item.product_id !== '';
        const details = Array.isArray(item.details) ? item.details : [];
        const hasQuantity = details.some(d => parseFloat(d.quantity_to_transfer) > 0);
        return hasProduct && hasQuantity;
    });
    if (fulfilledItems.length === 0) {
        loading.value = false;
        Swal.fire({
            title: "No Items to Transfer",
            text: "Please select at least one item and specify quantities to transfer.",
            icon: "warning",
            confirmButtonColor: "#4F46E5",
        });
        return;
    }
    // Ignore unused/empty rows – save only fulfilled rows
    const submitData = {
        ...form.value,
        items: fulfilledItems
    };
    await axios
        .post(route("transfers.store"), submitData)
        .then((response) => {
            loading.value = false;
            Swal.fire({
                title: "Success!",
                text: response.data,
                icon: "success",
                confirmButtonColor: "#4F46E5",
            }).then(() => {
                router.get(route("transfers.index"));
            });
        })
        .catch((error) => {
            loading.value = false;
            Swal.fire({
                title: "Error!",
                text: error.response?.data || "Failed to create transfer",
                icon: "error",
                confirmButtonColor: "#4F46E5",
            });
        });
};

const isLoading = ref([]);
async function handleProductSelect(index, selected) {
    isLoading.value[index] = true;
    const item = form.value.items[index];
    item.details = [];
    if (selected) {
        await axios
            .post(route("transfers.inventory"), {
                product_id: selected.id,
                source_type: sourceType.value,
                source_id: form.value.source_id,
            })
            .then((response) => {
                isLoading.value[index] = false;
                item.details = response.data.map(detail => ({
                    ...detail,
                    quantity_to_transfer: 0,
                    transfer_reason: '',
                    transfer_reason_object: null
                }));
                item.available_quantity = getSafeAvailableQuantity({
                    details: response.data
                });
                item.product = selected;
                item.product_id = selected.id;
                item.quantity = 0;
                addNewItem();
            })
            .catch((error) => {
                isLoading.value[index] = false;
                item.product_id = null;
                item.product = null;
                item.details = [];
                item.available_quantity = 0;
                Swal.fire({
                    title: "Error!",
                    text: error.response.data,
                    icon: "error",
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 5000,
                });
            });
    }
}

function handleProductClear(index) {
    const item = form.value.items[index];
    item.product = null;
    item.product_id = null;
    item.details = [];
    item.quantity = 0;
    item.available_quantity = 0;
    if (isLoading.value[index] !== undefined) {
        isLoading.value[index] = false;
    }
}

function updateItemQuantity(index) {
    const item = form.value.items[index];
    const totalQuantity = item.details.reduce((sum, detail) => {
        return sum + (parseFloat(detail.quantity_to_transfer) || 0);
    }, 0);
    item.quantity = totalQuantity;
    if (errors.value[`item_${index}_quantity`]) {
        errors.value[`item_${index}_quantity`] = null;
    }
}

function addNewItem() {
    const hasEmptyRow = form.value.items.some(item =>
        !item.product_id || item.product_id === null || item.product_id === ''
    );
    if (!hasEmptyRow) {
        form.value.items.push({
            id: null,
            product_id: null,
            product: null,
            available_quantity: 0,
            quantity: 0,
            details: [],
        });
    }
}

function removeItem(index) {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
}

function formatDate(date) {
    return moment(date).format("DD/MM/YYYY");
}

function isExpiringSoon(expiryDate) {
    if (!expiryDate) return false;
    const today = moment();
    const expiry = moment(expiryDate);
    const monthsDiff = expiry.diff(today, 'months');
    return monthsDiff <= 6 && monthsDiff >= 0;
}

// Helper function to safely calculate available quantity
function getSafeAvailableQuantity(item) {
    if (!item || !item.details || !Array.isArray(item.details)) return 0;
    
    const total = item.details.reduce((sum, detail) => {
        const quantity = Number(detail.quantity);
        return sum + (isNaN(quantity) ? 0 : quantity);
    }, 0);
    
    return isNaN(total) ? 0 : total;
}

// Keep item multiselect dropdown closed on initial load (never open by default)
function closeItemDropdownIfOpen() {
    const el = document.activeElement;
    if (el && el.closest && el.closest(".transfer-items-table .item-name-cell .multiselect")) {
        el.blur();
    }
    // Also close any item-name multiselect that is open (e.g. isOpen state true without focus)
    const openWrapper = document.querySelector(".transfer-items-table .item-name-cell .multiselect--active");
    if (openWrapper) {
        const input = openWrapper.querySelector("input");
        if (input) input.blur();
    }
}
onMounted(() => {
    // Focus transfer date so the item multiselect is never focused by default (dropdown stays closed)
    nextTick(() => {
        transferDateInputRef.value?.focus?.();
    });
    [0, 100, 250, 500, 800, 1200].forEach((ms) => {
        if (ms === 0) nextTick(closeItemDropdownIfOpen);
        else setTimeout(closeItemDropdownIfOpen, ms);
    });
});

// Transfer create permission: used for action buttons and "Add New Reason" in reason dropdown
const page = usePage();
const canCreateTransfer = computed(() =>
    !!page.props.auth?.can?.transfer_create || !!page.props.auth?.can?.transfer_manage || !!page.props.auth?.user?.isAdmin
);
// Reason dropdown: "Add New Reason" only for users who can create transfers (avoids permission-only "+" option for others)
const reasonOptions = computed(() => {
    const list = props.reasons || [];
    return canCreateTransfer.value ? ['Add New Reason', ...list] : list;
});
// Modal state for adding new reason
const showAddReasonModal = ref(false);
const newReasonName = ref("");
const addingReason = ref(false);


function handleReasonSelect(detail, selected) {
    if (selected === 'Add New Reason') {
        openAddReasonModal();
        currentDetailForReason.value = detail;
        detail.transfer_reason = '';
    } else {
        detail.transfer_reason = selected;
    }
}

// Function to open add reason modal
function openAddReasonModal() {
    showAddReasonModal.value = true;
    newReasonName.value = "";
}

async function handleAddReason() {
    if (!newReasonName.value.trim()) return;
    addingReason.value = true;
    try {
        const response = await axios.post(route('reasons.store'), { name: newReasonName.value });
        // Add new reason to the reasons array
        props.reasons.push(response.data);
        // Set the new reason for the current detail row
        if (currentDetailForReason.value) {
            currentDetailForReason.value.transfer_reason = response.data;
        }
        showAddReasonModal.value = false;
        newReasonName.value = '';
    } catch (e) {
        // handle error
    } finally {
        addingReason.value = false;
    }
}
</script>

<template>
    <AuthenticatedLayout title="Optimize Your Transfers" description="Moving Supplies, Bridging needs"
        img="/assets/images/transfer.png">
        <div class="bg-gradient-to-br to-blue-50 mb-[80px]">
            <!-- Header Section -->
            <div class="bg-white mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div
                            class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Create Transfer</h1>
                            <p class="text-gray-600 mt-1">Transfer inventory items between locations</p>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-4 py-2 rounded-xl">
                        <div class="text-sm font-medium">Transfer ID</div>
                        <div class="text-lg font-bold">{{ props.transferID }}</div>
                    </div>
                </div>

                <!-- Info Alert -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <div
                            class="w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-blue-800 font-medium">Transfer Instructions</p>
                            <p class="text-blue-700 text-sm mt-1">
                                Select source and destination locations, then choose inventory items to transfer.
                                Specify the exact quantity for each batch and provide a transfer reason.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Form Section -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
                <form ref="transferFormRef" @submit.prevent="submit" class="space-y-8">
                    <!-- Transfer Date -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <label for="transfer_date" class="block text-sm font-semibold text-gray-700 mb-2">
                                Transfer Date
                            </label>
                            <input ref="transferDateInputRef" type="date" v-model="form.transfer_date"
                                class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" />
                        </div>
                    </div>

                    <!-- Source and Destination Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Source Section -->
                        <div class="space-y-6">
                            <div class="flex items-center space-x-2 mb-4">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                        </path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Transfer From</h3>
                            </div>

                            <!-- Source Type Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Source Type</label>
                                <div class="flex space-x-4">
                                    <label
                                        class="flex items-center px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                                        :class="sourceType === 'warehouse' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300'">
                                        <input type="radio" v-model="sourceType" value="warehouse" class="sr-only" />
                                        <div class="w-4 h-4 rounded-full border-2 mr-2 flex items-center justify-center"
                                            :class="sourceType === 'warehouse' ? 'border-blue-500' : 'border-gray-400'">
                                            <div v-if="sourceType === 'warehouse'"
                                                class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                        </div>
                                        <span class="font-medium">Warehouse</span>
                                    </label>
                                    <label
                                        class="flex items-center px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                                        :class="sourceType === 'facility' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300'">
                                        <input type="radio" v-model="sourceType" value="facility" class="sr-only" />
                                        <div class="w-4 h-4 rounded-full border-2 mr-2 flex items-center justify-center"
                                            :class="sourceType === 'facility' ? 'border-blue-500' : 'border-gray-400'">
                                            <div v-if="sourceType === 'facility'"
                                                class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                        </div>
                                        <span class="font-medium">Facility</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Source Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Select Source {{ sourceType === 'warehouse' ? 'Warehouse' : 'Facility' }}
                                </label>
                                <Multiselect v-model="selectedSource" :options="sourceOptions" :searchable="true"
                                    :close-on-select="true" :show-labels="false" :allow-empty="true"
                                    :placeholder="`Select source ${sourceType === 'warehouse' ? 'warehouse' : 'facility'}`"
                                    track-by="id" label="name" @select="handleSourceSelect"
                                    :class="{ 'border-red-500': errors.source_id }" @open="errors.source_id = null">
                                    <template v-slot:option="{ option }">
                                        <span>{{ option.name }}</span>
                                    </template>
                                </Multiselect>
                                <InputError :message="errors.source_id" class="mt-2" />
                            </div>
                        </div>

                        <!-- Destination Section -->
                        <div class="space-y-6">
                            <div class="flex items-center space-x-2 mb-4">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 16l-4-4m0 0l4-4m-4 4h14m-6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                        </path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">Transfer To</h3>
                            </div>

                            <!-- Destination Type Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Destination Type</label>
                                <div class="flex space-x-4">
                                    <label
                                        class="flex items-center px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                                        :class="destinationType === 'warehouse' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300'">
                                        <input type="radio" v-model="destinationType" value="warehouse"
                                            class="sr-only" />
                                        <div class="w-4 h-4 rounded-full border-2 mr-2 flex items-center justify-center"
                                            :class="destinationType === 'warehouse' ? 'border-blue-500' : 'border-gray-400'">
                                            <div v-if="destinationType === 'warehouse'"
                                                class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                        </div>
                                        <span class="font-medium">Warehouse</span>
                                    </label>
                                    <label
                                        class="flex items-center px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors duration-200"
                                        :class="destinationType === 'facility' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300'">
                                        <input type="radio" v-model="destinationType" value="facility"
                                            class="sr-only" />
                                        <div class="w-4 h-4 rounded-full border-2 mr-2 flex items-center justify-center"
                                            :class="destinationType === 'facility' ? 'border-blue-500' : 'border-gray-400'">
                                            <div v-if="destinationType === 'facility'"
                                                class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                        </div>
                                        <span class="font-medium">Facility</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Destination Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Select Destination {{ destinationType === 'warehouse' ? 'Warehouse' : 'Facility' }}
                                </label>
                                <Multiselect v-model="selectedDestination" :options="filteredDestinationOptions"
                                    :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true"
                                    :placeholder="`Select destination ${destinationType === 'warehouse' ? 'warehouse' : 'facility'}`"
                                    track-by="id" label="name" @select="handleDestinationSelect" required
                                    :class="{ 'border-red-500': errors.destination_id }"
                                    @open="errors.destination_id = null">
                                    <template v-slot:option="{ option }">
                                        <span>{{ option.name }}</span>
                                    </template>
                                </Multiselect>
                                <InputError :message="errors.destination_id" class="mt-2" />
                            </div>
                        </div>
                    </div>
                    <!-- Items Table Section -->
                    <table class="transfer-items-table w-full text-sm text-left table-sm rounded-t-lg table-fixed">
                        <thead>
                            <tr style="background-color: #F4F7FB;">
                                <th class="w-[280px] min-w-[280px] max-w-[400px] px-3 py-2 text-xs font-bold rounded-tl-lg"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    Item Name
                                </th>
                                <th class="px-3 py-2 text-xs font-bold"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    Category
                                </th>
                                <th class="px-3 py-2 text-xs font-bold"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    UoM
                                </th>
                                <th class="px-3 py-2 text-xs font-bold text-center"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    Total Quantity on Hand Per Unit
                                </th>
                                <th class="px-3 py-2 text-xs font-bold text-center"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" colspan="4">
                                    Item details
                                </th>

                                <th class="w-[220px] min-w-[220px] max-w-[320px] px-3 py-2 text-xs font-bold"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    Reasons for Transfers
                                </th>
                                <th class="w-[130px] max-w-[130px] px-3 py-2 text-xs font-bold"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    Quantity to be transferred
                                </th>
                                <th class="px-3 py-2 text-xs font-bold"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    Total Quantity to be transferred
                                </th>
                                <th class="px-3 py-2 text-xs font-bold rounded-tr-lg"
                                    style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">
                                    Action
                                </th>
                            </tr>
                            <tr style="background-color: #F4F7FB;">
                                <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center"
                                    style="color: #4F6FCB;">
                                    QTY
                                </th>
                                <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center"
                                    style="color: #4F6FCB;">
                                    Batch Number
                                </th>
                                <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center"
                                    style="color: #4F6FCB;">
                                    Expiry Date
                                </th>
                                <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center"
                                    style="color: #4F6FCB;">
                                    Location
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <template v-for="(item, index) in form.items" :key="index">
                                <!-- Show details if they exist, otherwise show one row with main item data -->
                                <tr v-for="(detail, detailIndex) in (item.details?.length > 0 ? item.details : [{}])"
                                    :key="`${index}-${detail.id || detailIndex}`"
                                    class="hover:bg-gray-50 transition-colors duration-150 border-b"
                                    style="border-bottom: 1px solid #B7C6E6;">

                                    <!-- Item Name - only on first row for this item (overflow-visible so dropdown can show) -->
                                    <td v-if="detailIndex === 0" :rowspan="Math.max(item.details?.length || 1, 1)"
                                        class="item-name-cell w-[280px] min-w-[280px] max-w-[400px] px-3 py-2 text-xs font-medium text-gray-800 align-top overflow-visible">
                                        <div class="w-full min-w-0 max-w-full">
                                            <Multiselect v-model="item.product"
                                                :key="`item-select-${index}-${availableInventories.length}`"
                                                :options="availableInventories"
                                                :searchable="true" :close-on-select="true" :show-labels="false"
                                                :allow-empty="true"
                                                :prevent-autofocus="true"
                                                placeholder="Search for an item..."
                                                track-by="id" label="name"
                                                :multiple="false"
                                                :loading="isLoading[index]"
                                                @select="handleProductSelect(index, $event)"
                                                @remove="handleProductClear(index)">
                                                <template v-slot:option="{ option }">
                                                    <span>{{ option.name }}</span>
                                                </template>
                                            </Multiselect>
                                        </div>
                                    </td>

                                    <!-- Category - only on first row for this item -->
                                    <td v-if="detailIndex === 0" :rowspan="Math.max(item.details?.length || 1, 1)"
                                        class="px-3 py-2 text-xs text-gray-700 align-top">
                                        {{ item.product?.category?.name || '' }}
                                    </td>

                                    <!-- UoM - only on first row for this item -->
                                    <td v-if="detailIndex === 0" :rowspan="Math.max(item.details?.length || 1, 1)"
                                        class="px-3 py-2 text-xs text-gray-700 align-top">
                                        {{ item.details?.[0]?.uom || '' }}
                                    </td>

                                    <!-- Total Quantity on Hand Per Unit - only on first row for this item -->
                                    <td v-if="detailIndex === 0" :rowspan="Math.max(item.details?.length || 1, 1)"
                                        class="px-3 py-2 text-xs text-gray-800 align-top">
                                        {{ getSafeAvailableQuantity(item) }}
                                    </td>

                                    <!-- Item Details Columns -->
                                    <!-- Quantity -->
                                    <td class="px-2 py-1 text-xs text-center"
                                        :class="detail.expiry_date && isExpiringSoon(detail.expiry_date) ? 'text-red-600 font-medium' : 'text-gray-900'">
                                        {{ detail.quantity || '' }}
                                    </td>

                                    <!-- Batch Number -->
                                    <td class="px-2 py-1 text-xs text-center"
                                        :class="detail.expiry_date && isExpiringSoon(detail.expiry_date) ? 'text-red-600 font-medium' : 'text-gray-900'">
                                        {{ detail.batch_number || '' }}
                                    </td>

                                    <!-- Expiry Date -->
                                    <td class="px-2 py-1 text-xs text-center"
                                        :class="detail.expiry_date && isExpiringSoon(detail.expiry_date) ? 'text-red-600 font-medium' : 'text-gray-900'">
                                        {{ detail.expiry_date ? formatDate(detail.expiry_date) : '' }}
                                    </td>

                                    <!-- Location -->
                                    <td class="px-2 py-1 text-xs text-center">
                                        {{ detail.location || '' }}
                                    </td>

                                    <!-- Reasons for Transfers - per detail (full text, dropdown closes on select, Add New Reason option) -->
                                    <td class="reason-cell w-[220px] min-w-[220px] max-w-[320px] px-2 py-1 text-xs text-center">
                                        <div class="w-full min-w-0 max-w-full">
                                            <Multiselect v-if="item.product && detail.quantity"
                                                v-model="detail.transfer_reason"
                                                :options="reasonOptions" :searchable="true"
                                                :multiple="false"
                                                :close-on-select="true" :show-labels="false" :placeholder="'Select reason'"
                                                @select="handleReasonSelect(detail, $event)">
                                                <template v-slot:option="{ option }">
                                                    <span :class="{ 'text-blue-600 font-semibold': option === 'Add New Reason' }">
                                                        {{ option }}
                                                    </span>
                                                </template>
                                            </Multiselect>
                                        </div>
                                    </td>

                                    <!-- Quantity to be transferred - per detail -->
                                    <td class="w-[130px] max-w-[130px] px-2 py-1 text-xs text-center overflow-hidden">
                                        <input v-if="item.product && detail.quantity" type="number"
                                            v-model.number="detail.quantity_to_transfer" :max="detail.quantity" min="0"
                                            class="w-full max-w-full text-xs border rounded px-2 py-1 text-center box-border" placeholder="0"
                                            @input="updateItemQuantity(index)" />
                                    </td>

                                    <!-- Total Quantity to be transferred - only on first row for this item -->
                                    <td v-if="detailIndex === 0" :rowspan="Math.max(item.details?.length || 1, 1)"
                                        class="px-3 py-2 text-xs text-gray-800 align-top">
                                        <div class="text-sm font-medium text-blue-600" v-if="item.product">
                                            {{ item.quantity || 0 }}
                                        </div>
                                    </td>

                                    <!-- Action - only on first row for this item -->
                                    <td v-if="detailIndex === 0" :rowspan="Math.max(item.details?.length || 1, 1)"
                                        class="px-3 py-4 whitespace-nowrap text-xs font-medium align-top">
                                        <button type="button" @click="removeItem(index)"
                                            class="text-red-600 hover:text-red-800" :disabled="form.items.length <= 1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- Action Buttons (only when user can create transfers) -->
                    <div class="flex items-center justify-between pt-8 border-t border-gray-200">
                        <button v-if="canCreateTransfer"
                            type="button" @click="addNewItem"
                            class="inline-flex items-center px-6 py-3 font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Another Item
                        </button>
                        <div v-else class="flex-1"></div>
                        <div class="flex items-center space-x-4">
                            <SecondaryButton :href="route('transfers.index')" as="a" :disabled="loading"
                                class="px-8 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-all duration-200"
                                :class="{
                                    'opacity-50 cursor-not-allowed': loading,
                                }">
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton v-if="canCreateTransfer"
                                :disabled="loading"
                                class="relative px-8 py-3 font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white">
                                <span v-if="loading" class="absolute left-3">
                                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </span>
                                <span :class="{ 'pl-7': loading }">{{ loading ? "Processing..." : "Create Transfer" }}</span>
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Add Reason Modal -->
    <div v-if="showAddReasonModal" class="fixed inset-0 z-50">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddReasonModal = false">
            </div>

            <!-- Modal panel -->
            <div
                class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Add New Transfer Reason
                            </h3>
                            <form @submit.prevent="handleAddReason">
                                <div class="mb-4">
                                    <label for="reason_name" class="block text-sm font-medium text-gray-700">Reason
                                        Name</label>
                                    <input id="reason_name" v-model="newReasonName" type="text"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        placeholder="Enter reason name" required />
                                </div>
                                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                    <button type="submit" :disabled="addingReason"
                                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span v-if="addingReason" class="flex items-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none"
                                                viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                    stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                            Adding...
                                        </span>
                                        <span v-else>Add Reason</span>
                                    </button>
                                    <button type="button" @click="showAddReasonModal = false" :disabled="addingReason"
                                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
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
/* Fixed widths so Item Name, Reasons for Transfers, and Quantity to be transferred don't spread when active/focused */
.transfer-items-table th:nth-child(1),
.transfer-items-table td:nth-child(1) {
    width: 280px;
    min-width: 280px;
    max-width: 400px;
    box-sizing: border-box;
}
.transfer-items-table th:nth-child(9),
.transfer-items-table td:nth-child(9) {
    width: 220px;
    min-width: 220px;
    max-width: 320px;
    box-sizing: border-box;
}
.transfer-items-table th:nth-child(10),
.transfer-items-table td:nth-child(10) {
    width: 130px;
    min-width: 130px;
    max-width: 130px;
    box-sizing: border-box;
}
/* Reasons for Transfers (col 9): show full reason text, no truncation; dropdown closes on select; "Add New Reason" option */
.transfer-items-table td:nth-child(9) .multiselect {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}
.transfer-items-table td:nth-child(9) :deep(.multiselect__tags),
.transfer-items-table td:nth-child(9) :deep(.multiselect__tags-wrap) {
    min-width: 0 !important;
    overflow: visible !important;
}
.transfer-items-table td:nth-child(9) :deep(.multiselect__single),
.transfer-items-table td:nth-child(9) :deep(.multiselect__tag),
.transfer-items-table td:nth-child(9) :deep(.multiselect__tag span) {
    overflow: visible !important;
    text-overflow: clip !important;
    white-space: normal !important;
    word-break: normal !important;
    max-width: none !important;
}
/* Item Name (col 1): show full name, allow wrap – no truncation */
.transfer-items-table td:nth-child(1) .multiselect {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}
.transfer-items-table td:nth-child(1) :deep(.multiselect__tags) {
    min-width: 0 !important;
    overflow: visible !important;
}
.transfer-items-table td:nth-child(1) :deep(.multiselect__tags-wrap) {
    min-width: 0 !important;
    overflow: visible !important;
}
.transfer-items-table td:nth-child(1) :deep(.multiselect__single),
.transfer-items-table td:nth-child(1) :deep(.multiselect__tag),
.transfer-items-table td:nth-child(1) :deep(.multiselect__tag span) {
    overflow: visible !important;
    text-overflow: clip !important;
    white-space: normal !important;
    word-break: normal !important;
    max-width: none !important;
}
.transfer-items-table td:nth-child(10) input {
    max-width: 100%;
    box-sizing: border-box;
}
/* Item and Reason dropdowns: only on top when open – do not force visibility so they close when not active */
.transfer-items-table .item-name-cell :deep(.multiselect--active .multiselect__content-wrapper),
.transfer-items-table .reason-cell :deep(.multiselect--active .multiselect__content-wrapper) {
    z-index: 99999 !important;
    position: absolute !important;
}
</style>
