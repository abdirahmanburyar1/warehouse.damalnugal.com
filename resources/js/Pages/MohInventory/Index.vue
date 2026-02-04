<script setup>
import { ref, watch, computed, onUnmounted, nextTick } from "vue";
import { Head, router, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useToast } from "vue-toastification";
import moment from "moment";
import axios from "axios";
import Swal from "sweetalert2";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";

const toast = useToast();

const props = defineProps({
    nonApprovedInventories: Array,
    selectedInventory: Object,
    categories: Array,
    dosages: Array,
    products: Array,
    warehouses: Array,
    locations: Array,
    filters: Object,
});

// Selected inventory state
const selectedInventoryId = ref(props.filters?.inventory_id || '');
const search = ref(props.filters?.search || '');
const category_id = ref(props.filters?.category_id || '');
const dosage_id = ref(props.filters?.dosage_id || '');

const filterTimeout = ref(null);

// File upload state
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadFile = ref(null);
const showUploadModal = ref(false);
const importId = ref(null);
const progressInterval = ref(null);
const uploadResults = ref(null);
const fileInput = ref(null);

// Approval actions state
const isLoading = ref(false);
const isType = ref({
    is_reviewing: false,
    is_approve: false,
    is_reject: false
});

// Edit modal state
const showEditModal = ref(false);
const isUpdating = ref(false);
const isInitializingEditModal = ref(false);
const editForm = ref({
    id: null,
    product_id: null,
    product: null, // Store the full product object for multiselect
    product_name: '',
    warehouse_id: null,
    warehouse: null, // Store the full warehouse object for multiselect
    warehouse_name: '',
    quantity: 0,
    uom: '',
    batch_number: '',
    expiry_date: '',
    location: "", // Store the full location object for multiselect
    unit_cost: 0,
    total_cost: 0,
    barcode: ''
});

// Create modal state
const showCreateModal = ref(false);
const isCreating = ref(false);
const createForm = ref({
    date: new Date().toISOString().split('T')[0],
});
const createItems = ref([]);

// Data for dropdowns (now from props)

// Apply filters with debouncing
const applyFilters = () => {
    if (filterTimeout.value) {
        clearTimeout(filterTimeout.value);
    }

    filterTimeout.value = setTimeout(() => {
        const query = {};
        
        if (selectedInventoryId.value && selectedInventoryId.value !== '') query.inventory_id = selectedInventoryId.value;
        if (search.value && search.value.trim()) query.search = search.value.trim();
        if (category_id.value && category_id.value !== '') query.category_id = category_id.value;
        if (dosage_id.value && dosage_id.value !== '') query.dosage_id = dosage_id.value;

        isLoading.value = true;

        router.get(route("inventories.moh-inventory.index"), query, {
            preserveState: true,
            preserveScroll: true,
            only: ["nonApprovedInventories", "selectedInventory", "categories", "dosages"],
            onFinish: () => {
                isLoading.value = false;
            },
        });
    }, 300);
};

// Watch for changes in filter values
watch([selectedInventoryId, search, category_id, dosage_id], applyFilters);

// Watch for changes in quantity and unit_cost to calculate total_cost
watch([() => editForm.value.quantity, () => editForm.value.unit_cost], () => {
    const quantity = parseFloat(editForm.value.quantity) || 0;
    const unitCost = parseFloat(editForm.value.unit_cost) || 0;
    editForm.value.total_cost = (quantity * unitCost).toFixed(2);
});

// Watch for product selection changes in create items
watch(createItems, (newItems) => {
    newItems.forEach(item => {
        if (item.product && item.product.id) {
            item.product_id = item.product.id;
        }
    });
}, { deep: true });

// Watch for product selection changes in edit form
watch(() => editForm.value.product, (newProduct) => {
    if (newProduct && newProduct.id) {
        editForm.value.product_id = newProduct.id;
    }
});

// Watch for warehouse selection changes in edit form
watch(() => editForm.value.warehouse, (newWarehouse) => {
    if (newWarehouse && newWarehouse.id) {
        editForm.value.warehouse_id = newWarehouse.id;
        // Clear location when warehouse changes
        if (!isInitializingEditModal.value) {
            editForm.value.location = null;
        }
    }
});

// Watch for location selection changes in edit form
watch(() => editForm.value.location, (newLocation) => {
    // Location is now a string, so we don't need to extract ID
    // The location string will be sent directly to the controller
});

// Clear filters
const clearFilters = () => {
    selectedInventoryId.value = '';
    search.value = '';
    category_id.value = '';
    dosage_id.value = '';
};

// File upload methods
const openUploadModal = () => {
    showUploadModal.value = true;
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    uploadFile.value = null;
    uploadProgress.value = 0;
    uploadResults.value = null;
    if (fileInput.value) {
        fileInput.value.value = null;
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Check file type - allow .xlsx and .csv
    const fileExtension = "." + file.name.split(".").pop().toLowerCase();
    const validExtensions = [".xlsx", ".csv"];

    if (!validExtensions.includes(fileExtension)) {
        toast.error(
            "Invalid file type. Please upload an Excel file (.xlsx) or CSV file (.csv)"
        );
        event.target.value = null; // Clear the file input
        uploadFile.value = null;
        return;
    }

    // Check file size (max 5MB)
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        toast.error("File is too large. Maximum file size is 5MB.");
        event.target.value = null;
        uploadFile.value = null;
        return;
    }

    uploadFile.value = file;
};

const removeSelectedFile = () => {
    uploadFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = null;
    }
};

const uploadExcelFile = async () => {
    if (!uploadFile.value) {
        toast.error('Please select a file to upload');
        return;
    }

    const formData = new FormData();
    formData.append('file', uploadFile.value);
    
    // If a MOH inventory is selected, include its ID
    if (selectedInventoryId.value) {
        formData.append('moh_inventory_id', selectedInventoryId.value);
    }

    isUploading.value = true;
    uploadProgress.value = 0;

    try {
        const response = await fetch(route('inventories.moh-inventory.import'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
        });

        const data = await response.json();

        if (data.success) {
            toast.success(data.message);
            if (data.warning) {
                toast.warning(data.warning);
            }
            
            // Close modal first
            showUploadModal.value = false;
            uploadFile.value = null;
            uploadProgress.value = 0;
            importId.value = null;
            
            // If server created a new MOH inventory, select it so user sees imported rows
            if (data.moh_inventory_id) {
                selectedInventoryId.value = String(data.moh_inventory_id);
            }
            
            // Refresh the page to show new data
            applyFilters();
        } else {
            toast.error(data.message);
            // Show detailed error message
            // Reset upload state on error
            isUploading.value = false;
            uploadProgress.value = 0;
            importId.value = null;
        }
    } catch (error) {
        console.error('Upload error:', error);
        toast.error('Upload failed. Please try again.');
        // Reset upload state on error
        isUploading.value = false;
        uploadProgress.value = 0;
        importId.value = null;
    } finally {
        isUploading.value = false;
    }
};

const startProgressPolling = () => {
    if (progressInterval.value) {
        clearInterval(progressInterval.value);
    }

    let pollingAttempts = 0;
    const maxPollingAttempts = 300; // 5 minutes timeout (300 * 1000ms = 5 minutes)

    progressInterval.value = setInterval(async () => {
        pollingAttempts++;
        
        // Timeout after 5 minutes
        if (pollingAttempts > maxPollingAttempts) {
            clearInterval(progressInterval.value);
            progressInterval.value = null;
            toast.error('Import timeout. Please check if the import completed successfully.');
            isUploading.value = false;
            uploadProgress.value = 0;
            importId.value = null;
            return;
        }

        try {
            const response = await fetch(`${route('inventories.moh-inventory.import-progress')}?import_id=${importId.value}`);
            const data = await response.json();

            if (data.success) {
                uploadProgress.value = data.progress;
                
                if (data.completed) {
                    clearInterval(progressInterval.value);
                    progressInterval.value = null;
                    toast.success('Import completed successfully!');
                    if (data.warning) {
                        toast.warning(data.warning);
                    }
                    // Reset upload state after completion
                    isUploading.value = false;
                    uploadProgress.value = 0;
                    importId.value = null;
                } else if (data.progress === -1) {
                    // Handle import failure
                    clearInterval(progressInterval.value);
                    progressInterval.value = null;
                    toast.error(data.error || 'Import failed. Please check the logs for details.');
                    // Reset upload state on failure
                    isUploading.value = false;
                    uploadProgress.value = 0;
                    importId.value = null;
                }
            }
        } catch (error) {
            console.error('Progress polling error:', error);
            clearInterval(progressInterval.value);
            progressInterval.value = null;
            // Reset upload state on error
            isUploading.value = false;
            uploadProgress.value = 0;
            importId.value = null;
        }
    }, 1000);
};

const cancelUpload = () => {
    showUploadModal.value = false;
    uploadFile.value = null;
    isUploading.value = false;
    uploadProgress.value = 0;
    
    if (progressInterval.value) {
        clearInterval(progressInterval.value);
        progressInterval.value = null;
    }
};

// Download template function
const downloadTemplate = () => {
    // Create a CSV format that Excel can open properly
    const headers = ['Item', 'Category', 'UoM', 'Source', 'Quantity', 'Batch No', 'Expiry Date', 'Location', 'Warehouse', 'Unit Cost'];
    
    // Create sample data
    const sampleData = [
        ['Acetylsalicylic acid (aspirin)75mg tab', 'Drugs', 'Pcs', 'UNICEF', '2300', 'ADF345342', '20/02/2028', 'W1-R1-C1-R1', 'Nugal Regional Main Warehouse', '0.4']
    ];
    
    // Combine headers and sample data
    const csvContent = [headers, ...sampleData]
        .map(row => row.map(field => `"${field}"`).join(','))
        .join('\n');
    
    // Create and download the file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'moh_inventory_template.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Format date helper
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return moment(dateString).format('MMM DD, YYYY HH:mm');
};

// Change MOH inventory status
const changeStatus = async (inventoryId, status, type) => {
    // Show confirmation dialog
    const statusText = status.charAt(0).toUpperCase() + status.slice(1);
    const confirmResult = await Swal.fire({
        title: `${statusText} MOH Inventory?`,
        text: `Are you sure you want to ${status.toLowerCase()} this MOH inventory?`,
        icon: status === 'rejected' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'rejected' ? '#ef4444' : '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Yes, ${statusText}!`,
        cancelButtonText: 'Cancel',
        reverseButtons: true
    });

    if (!confirmResult.isConfirmed) {
        return;
    }

    isLoading.value = true;
    isType.value[type] = true;

    try {
        const response = await axios.post(route('inventories.moh-inventory.change-status', inventoryId), {
            status: status
        });

        if (response.data.success) {
            // Show success confirmation
            await Swal.fire({
                title: 'Success!',
                text: response.data.message || 'Status updated successfully',
                icon: 'success',
                confirmButtonColor: '#10b981'
            });
            
            // Refresh the page to get updated data
            router.reload({
                only: ['nonApprovedInventories', 'selectedInventory']
            });
        } else {
            await Swal.fire({
                title: 'Error!',
                text: response.data.message || 'Failed to update status',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        }
    } catch (error) {
        console.error('Error changing status:', error);
        await Swal.fire({
            title: 'Error!',
            text: error.response?.data?.message || 'An error occurred while updating status',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    } finally {
        isLoading.value = false;
        isType.value[type] = false;
    }
};

// Cleanup timeout on unmount
onUnmounted(() => {
    if (filterTimeout.value) {
        clearTimeout(filterTimeout.value);
    }
    if (progressInterval.value) {
        clearInterval(progressInterval.value);
    }
});

// Format currency
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount || 0);
};

// Get total quantity for a MOH inventory
const getTotalQuantity = (mohInventory) => {
    return mohInventory.moh_inventory_items?.reduce((total, item) => {
        return total + (item.quantity || 0);
    }, 0) || 0;
};

// Get total items count for a MOH inventory
const getTotalItems = (mohInventory) => {
    return mohInventory.moh_inventory_items?.length || 0;
};

// No need for API loading functions - data comes from props

// Open edit modal
const openEditModal = (item) => {
    // Check if inventory is approved
    if (props.selectedInventory?.approved_at) {
        Swal.fire({
            icon: 'warning',
            title: 'Cannot Edit',
            text: 'This MOH inventory has been approved and cannot be edited.',
            timer: 3000,
            showConfirmButton: false
        });
        return;
    }
    
    // Prevent the warehouse watcher from clearing the location during initialization
    isInitializingEditModal.value = true;

    editForm.value = {
        id: item.id,
        product_id: item.product_id || null,
        product: item.product || null,
        product_name: item.product?.name || '',
        warehouse_id: item.warehouse_id || null,
        warehouse: item.warehouse || null,
        warehouse_name: item.warehouse?.name || '',
        quantity: item.quantity || 0,
        uom: item.uom || '',
        batch_number: item.batch_number || '',
        expiry_date: item.expiry_date ? moment(item.expiry_date).format('YYYY-MM-DD') : '',
        location_id: null, // No longer needed since locations are strings
        location: item.location || null, // Set location string
        unit_cost: item.unit_cost || 0,
        total_cost: item.total_cost || 0,
        barcode: item.barcode || ''
    };
    
    // Calculate total cost based on quantity and unit cost
    const quantity = parseFloat(editForm.value.quantity) || 0;
    const unitCost = parseFloat(editForm.value.unit_cost) || 0;
    editForm.value.total_cost = (quantity * unitCost).toFixed(2);
    
    
    showEditModal.value = true;

    // End initialization on next tick so subsequent warehouse changes behave normally
    nextTick(() => {
        isInitializingEditModal.value = false;
    });
};

// Close edit modal
const closeEditModal = () => {
    showEditModal.value = false;
    editForm.value = {
        id: null,
        product_id: null,
        product_name: '',
        warehouse_id: null,
        warehouse_name: '',
        quantity: 0,
        uom: '',
        batch_number: '',
        expiry_date: '',
        location: '',
        unit_cost: 0,
        total_cost: 0,
        barcode: ''
    };
};

// Update MOH inventory item
const updateMohItem = async () => {
    try {
        isUpdating.value = true;
        
        // Ensure product_id is set from the product object
        if (editForm.value.product && editForm.value.product.id) {
            editForm.value.product_id = editForm.value.product.id;
        }
        
        // Ensure warehouse_id is set from the warehouse object
        if (editForm.value.warehouse && editForm.value.warehouse.id) {
            editForm.value.warehouse_id = editForm.value.warehouse.id;
        }
        
        // Location is now a string, no need to extract ID
        
        // Calculate total cost
        const totalCost = (parseFloat(editForm.value.quantity) || 0) * (parseFloat(editForm.value.unit_cost) || 0);
        editForm.value.total_cost = totalCost;
        
        const response = await axios.put(`/moh-inventory/${editForm.value.id}`, {
            product_id: editForm.value.product_id,
            warehouse_id: editForm.value.warehouse_id,
            quantity: editForm.value.quantity,
            uom: editForm.value.uom,
            batch_number: editForm.value.batch_number,
            expiry_date: editForm.value.expiry_date,
            location: editForm.value.location, // Send location string directly
            unit_cost: editForm.value.unit_cost,
            total_cost: editForm.value.total_cost,
            barcode: editForm.value.barcode
        });
        
        if (response.data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'MOH inventory item updated successfully',
                timer: 2000,
                showConfirmButton: false
            });
            
            closeEditModal();
            
            // Reload the page to get updated data
            router.get(route("inventories.moh-inventory.index"), {
                inventory_id: selectedInventoryId.value,
                search: search.value,
                category_id: category_id.value,
                dosage_id: dosage_id.value
            }, {
                preserveState: false,
                preserveScroll: false,
                only: ["nonApprovedInventories", "selectedInventory", "categories", "dosages", "products", "warehouses", "locations"]
            });
        } else {
            throw new Error(response.data.message || 'Failed to update item');
        }
    } catch (error) {
        console.error('Error updating MOH inventory item:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: error.response?.data?.message || 'Failed to update MOH inventory item',
            timer: 3000,
            showConfirmButton: false
        });
    } finally {
        isUpdating.value = false;
    }
};

// Create modal functions
const openCreateModal = () => {
    showCreateModal.value = true;
    createForm.value = {
        date: new Date().toISOString().split('T')[0]
    };
    createItems.value = [];
    addCreateItem();
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    createForm.value = {
        date: new Date().toISOString().split('T')[0],
    };
    createItems.value = [];
};

const addCreateItem = () => {
    createItems.value.push({
        product_id: null,
        product: null, // Store the full product object for multiselect
        quantity: 0,
        uom: '',
        source: '',
        batch_number: '',
        expiry_date: '',
        location: null, // Location is now a string
        warehouse_id: null,
        unit_cost: 0,
        total_cost: 0,
        barcode: ''
    });
};

const removeCreateItem = (index) => {
    if (createItems.value.length > 1) {
        createItems.value.splice(index, 1);
    }
};

const calculateCreateItemTotal = (item) => {
    const quantity = parseFloat(item.quantity) || 0;
    const unitCost = parseFloat(item.unit_cost) || 0;
    item.total_cost = (quantity * unitCost).toFixed(2);
};

// Filter locations by warehouse - locations are now just strings
const getFilteredLocations = (warehouseId) => {
    // Since locations are now just strings, we can't filter by warehouse
    // Return all locations for now, or implement warehouse-based filtering differently
    return props.locations || [];
};

// Get filtered locations for edit form based on selected warehouse
const getEditFilteredLocations = computed(() => {
    // If no warehouse is selected, return empty array to show dependency
    if (!editForm.value.warehouse_id) return [];
    
    // Find the selected warehouse
    const selectedWarehouse = props.warehouses.find(w => w.id == editForm.value.warehouse_id);
    if (!selectedWarehouse) return [];
    
    // Since locations are now strings, we need to filter them based on warehouse
    // This assumes locations are prefixed with warehouse info or we need a different approach
    // For now, return all locations - you may need to implement warehouse-based filtering differently
    return props.locations || [];
});

// Handle warehouse change - clear location selection
const filterLocationsByWarehouse = (item) => {
    item.location = null; // Clear location when warehouse changes
};

const createMohInventory = async () => {
    try {
        isCreating.value = true;
        
        // Calculate total costs for all items and ensure product_id is set
        createItems.value.forEach(item => {
            calculateCreateItemTotal(item);
            // Ensure product_id is set from the product object
            if (item.product && item.product.id) {
                item.product_id = item.product.id;
            }
        });
        
        const response = await axios.post('/moh-inventory', {
            date: createForm.value.date,
            items: createItems.value
        });
        
        if (response.data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'MOH inventory created successfully',
                timer: 2000,
                showConfirmButton: false
            });
            
            closeCreateModal();
            
            // Reload the page to get updated data
            router.get(route("inventories.moh-inventory.index"), {
                inventory_id: selectedInventoryId.value,
                search: search.value,
                category_id: category_id.value,
                dosage_id: dosage_id.value
            }, {
                preserveState: false,
                preserveScroll: false,
                only: ["nonApprovedInventories", "selectedInventory", "categories", "dosages", "products", "warehouses", "locations"]
            });
        } else {
            throw new Error(response.data.message || 'Failed to create MOH inventory');
        }
    } catch (error) {
        console.error('Error creating MOH inventory:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: error.response?.data?.message || 'Failed to create MOH inventory',
            timer: 3000,
            showConfirmButton: false
        });
    } finally {
        isCreating.value = false;
    }
};

// Check if MOH inventory has review/approval status
const getStatusInfo = (mohInventory) => {
    if (mohInventory.approved_at) {
        return { status: 'approved', color: 'green', text: 'Approved' };
    } else if (mohInventory.reviewed_at) {
        return { status: 'reviewed', color: 'blue', text: 'Reviewed' };
    } else {
        return { status: 'pending', color: 'yellow', text: 'Pending' };
    }
};

// Get unique products from MOH inventory items
const getUniqueProducts = (mohInventory) => {
    const products = mohInventory.moh_inventory_items?.map(item => item.product).filter(Boolean) || [];
    const uniqueProducts = products.filter((product, index, self) => 
        index === self.findIndex(p => p.id === product.id)
    );
    return uniqueProducts;
};

// Get total cost for a MOH inventory
const getTotalCost = (mohInventory) => {
    return mohInventory.moh_inventory_items?.reduce((total, item) => {
        return total + (item.total_cost || 0);
    }, 0) || 0;
};

// Filter inventory items based on search and filters
const filteredInventoryItems = computed(() => {
    if (!props.selectedInventory?.moh_inventory_items) return [];
    
    let items = props.selectedInventory.moh_inventory_items;
    
    // Apply search filter
    if (search.value && search.value.trim()) {
        const searchTerm = search.value.toLowerCase();
        items = items.filter(item => 
            item.product?.name?.toLowerCase().includes(searchTerm) ||
            item.product?.product_code?.toLowerCase().includes(searchTerm) ||
            item.batch_number?.toLowerCase().includes(searchTerm) ||
            item.barcode?.toLowerCase().includes(searchTerm)
        );
    }
    
    // Apply category filter
    if (category_id.value && category_id.value !== '') {
        items = items.filter(item => item.product?.category_id == category_id.value);
    }
    
    // Apply dosage filter
    if (dosage_id.value && dosage_id.value !== '') {
        items = items.filter(item => item.product?.dosage_id == dosage_id.value);
    }
    
    return items;
});
</script>

<template>
    <Head title="MOH Inventory" />

    <AuthenticatedLayout :title="'MOH Inventory'" :description="'Ministry of Health Inventory Management'">
        <div class="py-6">
            <!-- Header Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">MOH Inventory</h2>
                        <p class="text-gray-600">Select a non-approved MOH inventory to view its items</p>
                    </div>

                    <!-- MOH Inventory Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Select MOH Inventory <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="selectedInventoryId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">Choose a MOH inventory...</option>
                            <option v-for="inventory in nonApprovedInventories" :key="inventory.id" :value="inventory.id">
                                {{ inventory.uuid || `MOH-${inventory.id}` }}, {{ inventory.date ? moment(inventory.date).format('DD/MM/YYYY') : 'N/A' }}
                                - {{ getStatusInfo(inventory).text }}
                            </option>
                        </select>
                    </div>

                    <!-- Upload Section -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Import MOH Inventory Items</h3>
                                <p class="text-sm text-gray-600">Upload an Excel file to import inventory items</p>
                            </div>
                            <div class="flex space-x-3">
                                <button
                                    @click="openCreateModal"
                                    :disabled="!$page.props.auth.can.moh_inventory_create"
                                    :class="[
                                        $page.props.auth.can.moh_inventory_create
                                            ? 'bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:ring-blue-500'
                                            : 'bg-gray-400 cursor-not-allowed',
                                    ]"
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Create New MOH Inventory
                                </button>
                                <button
                                    @click="openUploadModal"
                                    :disabled="!$page.props.auth.can.moh_inventory_create"
                                    :class="[
                                        $page.props.auth.can.moh_inventory_create
                                            ? 'bg-green-600 hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:ring-green-500'
                                            : 'bg-gray-400 cursor-not-allowed',
                                    ]"
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    Upload Excel File
                                </button>
                            </div>
                        </div>
                        
                        <!-- Progress Bar (shown during upload) -->
                        <div v-if="isUploading || uploadProgress > 0" class="mt-4">
                            <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                                <span v-if="isUploading">Uploading file...</span>
                                <span v-else-if="uploadProgress > 0 && uploadProgress < 100">Processing import... ({{ uploadProgress }}%)</span>
                                <span v-else-if="uploadProgress === 100">Import completed!</span>
                                <span v-else>Preparing import...</span>
                                <span v-if="uploadProgress > 0">{{ uploadProgress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div 
                                    class="bg-green-600 h-2 rounded-full transition-all duration-300"
                                    :style="{ width: uploadProgress + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters (only show when inventory is selected) -->
                    <div v-if="props.selectedInventory" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search Items</label>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search products, batch, barcode..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select
                                v-model="category_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">All Categories</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
                            <select
                                v-model="dosage_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">All Dosages</option>
                                <option v-for="dosage in dosages" :key="dosage.id" :value="dosage.id">
                                    {{ dosage.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button
                                @click="clearFilters"
                                class="w-full px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                            >
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>

            <!-- Selected Inventory Details -->
            <div v-else-if="props.selectedInventory" class="space-y-6">
                <!-- Inventory Summary -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Inventory Summary</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-6">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">UUID</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ selectedInventory.uuid || `MOH-${selectedInventory.id}` }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ selectedInventory.date ? moment(selectedInventory.date).format('MMM DD, YYYY') : 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Items</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ getTotalItems(props.selectedInventory) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Quantity</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ getTotalQuantity(props.selectedInventory) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Cost</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ formatCurrency(getTotalCost(props.selectedInventory)) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <span 
                                        :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-${getStatusInfo(props.selectedInventory).color}-100 text-${getStatusInfo(props.selectedInventory).color}-800`"
                                    >
                                        {{ getStatusInfo(props.selectedInventory).text }}
                                    </span>
                                </dd>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Items Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Inventory Items ({{ filteredInventoryItems.length }})</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UoM</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in filteredInventoryItems" :key="item.id" class="hover:bg-gray-50">
                                    <!-- Item -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ item.product?.name || 'N/A' }}</div>
                                            <div class="text-sm text-gray-500">{{ item.product?.product_code || 'N/A' }}</div>
                                            <div class="text-xs text-gray-400">
                                                {{ item.product?.category?.name || 'N/A' }} - {{ item.product?.dosage?.name || 'N/A' }}
                                            </div>
                                        </div>
                                    </td>
                                    <!-- UoM -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.uom || 'N/A' }}
                                    </td>
                                    <!-- Source -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.source || 'N/A' }}
                                    </td>
                                    <!-- Quantity -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.quantity }}
                                    </td>
                                    <!-- Batch No -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.batch_number || 'N/A' }}
                                    </td>
                                    <!-- Expiry Date -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.expiry_date ? moment(item.expiry_date).format('MMM DD, YYYY') : 'N/A' }}
                                    </td>
                                    <!-- Location -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.location || 'N/A' }}
                                    </td>
                                    <!-- Unit Cost -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatCurrency(item.unit_cost) }}
                                    </td>
                                    <!-- Total Cost -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatCurrency(item.total_cost) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">                                        
                                        <button @click="openEditModal(item)"
                                            :disabled="props.selectedInventory?.approved_at"
                                            :class="[
                                                props.selectedInventory?.approved_at
                                                    ? 'text-gray-400 bg-gray-100 cursor-not-allowed'
                                                    : 'text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500'
                                            ]"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md">
                                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            {{ props.selectedInventory?.approved_at ? 'Edit (Disabled)' : 'Edit' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- Approval Actions -->
            <div v-if="props.selectedInventory" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">MOH Inventory Approval Actions</h3>
                    <p class="text-sm text-gray-500 mt-1">Review and approve this MOH inventory</p>
                </div>
                <div class="px-6 py-4">
                    <div class="flex flex-wrap items-center justify-center gap-4">
                        <!-- Review Button -->
                        <div class="relative">
                            <div class="flex flex-col">
                                <button 
                                    @click="changeStatus(selectedInventory.id, 'reviewed', 'is_reviewing')"
                                    :disabled="isType['is_reviewing'] || selectedInventory.reviewed_at || selectedInventory.status === 'rejected' || (!$page.props.auth.can.moh_inventory_review && !$page.props.auth.isAdmin)"
                                    :class="[
                                        selectedInventory.reviewed_at
                                            ? 'bg-green-500'
                                            : selectedInventory.status === 'rejected'
                                                ? 'bg-gray-300 cursor-not-allowed'
                                                : (!$page.props.auth.can.moh_inventory_review && !$page.props.auth.isAdmin)
                                                    ? 'bg-gray-400 cursor-not-allowed'
                                                    : 'bg-yellow-500 hover:bg-yellow-600',
                                    ]" 
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <img src="/assets/images/review.png" class="w-5 h-5 mr-2" alt="Review" />
                                    <span class="text-sm font-bold text-white">
                                        {{ props.selectedInventory.reviewed_at ? 'Reviewed' : isType['is_reviewing'] ? 'Please Wait...' : 'Review' }}
                                    </span>
                                </button>
                                <span v-show="props.selectedInventory?.reviewed_at" class="text-sm text-gray-600 mt-1">
                                    On {{ moment(props.selectedInventory?.reviewed_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.selectedInventory?.reviewed_by" class="text-sm text-gray-600">
                                    By {{ props.selectedInventory?.reviewer?.name }}
                                </span>
                            </div>
                            <div v-if="!props.selectedInventory.reviewed_at && props.selectedInventory.status !== 'rejected'" class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Approve Button -->
                        <div class="relative" v-if="props.selectedInventory.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button 
                                    @click="changeStatus(props.selectedInventory.id, 'approved', 'is_approve')"
                                    :disabled="isType['is_approve'] || !props.selectedInventory.reviewed_at || props.selectedInventory.approved_at || (!$page.props.auth.can.moh_inventory_approve && !$page.props.auth.isAdmin)"
                                    :class="[
                                        props.selectedInventory.approved_at
                                            ? 'bg-green-500'
                                            : props.selectedInventory.reviewed_at && !props.selectedInventory.approved_at
                                                ? (!$page.props.auth.can.moh_inventory_approve && !$page.props.auth.isAdmin)
                                                    ? 'bg-gray-400 cursor-not-allowed'
                                                    : 'bg-yellow-500 hover:bg-yellow-600'
                                                : 'bg-gray-300 cursor-not-allowed',
                                    ]" 
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <svg v-if="isType['is_approve']" 
                                        class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <template v-else>
                                        <img src="/assets/images/approved.png" class="w-5 h-5 mr-2" alt="Approve" />
                                        <span class="text-sm font-bold text-white">
                                            {{ props.selectedInventory.approved_at ? 'Approved' : isType['is_approve'] ? 'Please Wait...' : 'Approve' }}
                                        </span>
                                    </template>
                                </button>
                                <span v-show="props.selectedInventory?.approved_at" class="text-sm text-gray-600 mt-1">
                                    On {{ moment(props.selectedInventory?.approved_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.selectedInventory?.approved_by" class="text-sm text-gray-600">
                                    By {{ props.selectedInventory?.approver?.name }}
                                </span>
                            </div>
                            <div v-if="props.selectedInventory.reviewed_at && !props.selectedInventory.approved_at" class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Reject Button - Only show if not approved -->
                        <div class="relative" v-if="!props.selectedInventory.approved_at">
                            <div class="flex flex-col">
                                <button
                                    @click="changeStatus(props.selectedInventory.id, 'rejected', 'is_reject')"
                                    :disabled="isType['is_reject'] || props.selectedInventory.status === 'rejected' || (!$page.props.auth.can.moh_inventory_reject && !$page.props.auth.isAdmin)"
                                    :class="[
                                        props.selectedInventory.status === 'rejected'
                                            ? 'bg-red-500'
                                            : (!$page.props.auth.can.moh_inventory_reject && !$page.props.auth.isAdmin)
                                                ? 'bg-gray-400 cursor-not-allowed'
                                                : 'bg-red-500 hover:bg-red-600',
                                    ]" 
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <img src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Reject" />
                                    <span class="text-sm font-bold text-white">
                                        {{ props.selectedInventory.status === 'rejected' ? 'Rejected' : isType['is_reject'] ? 'Please Wait...' : 'Reject' }}
                                    </span>
                                </button>
                                <span v-show="props.selectedInventory?.status === 'rejected'" class="text-sm text-gray-600 mt-1">
                                    On {{ moment(props.selectedInventory?.rejected_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.selectedInventory?.rejected_by" class="text-sm text-gray-600">
                                    By {{ props.selectedInventory?.rejected?.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <!-- Empty State - No Inventory Selected -->
            <div v-else-if="!props.selectedInventory" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No MOH inventory selected</h3>
                <p class="mt-1 text-sm text-gray-500">Please select a MOH inventory from the dropdown above to view its items.</p>
            </div>

            <!-- Empty State - No Non-Approved Inventories -->
            <div v-else-if="nonApprovedInventories.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No non-approved MOH inventories</h3>
                <p class="mt-1 text-sm text-gray-500">All MOH inventories have been approved or there are no MOH inventories available.</p>
            </div>
        </div>

        <!-- Excel Upload Modal -->
        <div v-if="showUploadModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-stretch justify-stretch z-50 p-0"
            @click="closeUploadModal">
            <div class="bg-white shadow-xl w-screen h-screen max-w-none max-h-none overflow-hidden flex flex-col" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200 flex-none">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Upload MOH Inventory</h3>
                        <p class="text-sm text-gray-500 mt-1">Import MOH inventory items from Excel file</p>
                    </div>
                    <button @click="closeUploadModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <div class="p-4 flex-1 overflow-hidden flex flex-col gap-4">
                    <!-- Download Template Section -->
                    <div
                        class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-green-800">Need a template?</h4>
                                <p class="text-sm text-green-700 mt-1">
                                    Download our template to see the correct format for uploading MOH inventory items.
                                </p>
                                <button @click="downloadTemplate"
                                    class="mt-3 inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-medium text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                        </path>
                                    </svg>
                                    Download Template
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Collapse long requirements to avoid scrolling in fullscreen modal -->
                    <details class="bg-gray-50 rounded-lg border border-gray-200">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-900 select-none">
                            Required Columns (click to expand)
                        </summary>
                        <div class="px-4 pb-4">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column Name</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Item</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Product name</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Category</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Product category</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">UoM</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Unit of measurement</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Source</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Supply source (e.g., UNICEF)</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Quantity</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Number of items</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Batch No</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Batch number</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Expiry Date</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Expiration date (DD/MM/YYYY)</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Location</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Storage location</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Warehouse</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Required</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Warehouse name (must exist)</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">Unit Cost</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Optional</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">Cost per unit (default: 0)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-800">
                                        <strong>Note:</strong> Products must already exist in the system (otherwise those rows will be skipped). Warehouses must exist in the database before importing.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </details>

                    <div class="flex-1 min-h-0">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-50 transition-colors cursor-pointer"
                            @click="triggerFileInput">
                            <input type="file" ref="fileInput" class="hidden" @change="handleFileUpload"
                                accept=".xlsx,.xls,.csv" />
                            <svg class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                                </path>
                            </svg>
                            <p class="text-lg font-medium text-gray-900 mb-2">
                                {{ uploadFile ? 'File Selected' : 'Choose File' }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ uploadFile ? uploadFile.name : 'Click to select or drag and drop file here' }}
                            </p>
                            <p class="text-xs text-gray-400 mt-2">
                                Supports .xlsx, .xls, and .csv files (max 5MB)
                            </p>
                        </div>

                        <div v-if="uploadFile"
                            class="mt-4 flex items-center justify-between bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-blue-500 mr-3" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-blue-900">{{ uploadFile.name }}</p>
                                    <p class="text-xs text-blue-700">{{ (uploadFile.size / 1024 / 1024).toFixed(2) }}
                                        MB</p>
                                </div>
                            </div>
                            <button @click.stop="removeSelectedFile"
                                class="text-red-500 hover:text-red-700 transition-colors duration-200">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div v-if="isUploading" class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Upload Progress</h4>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: uploadProgress + '%' }"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">{{ uploadProgress }}% complete</p>
                    </div>

                    <!-- Upload Results -->
                    <div v-if="uploadResults && !isUploading" class="mb-6">
                        <div class="bg-green-50 border border-green-200 rounded-md p-4">
                            <h3 class="text-sm font-medium text-green-800">Upload Results</h3>
                            <p class="text-sm text-green-700 mt-1">{{ uploadResults.message }}</p>
                            <div v-if="uploadResults.import_id" class="mt-2 text-xs text-gray-600">
                                <p>Import ID: {{ uploadResults.import_id }}</p>
                                <p v-if="uploadResults.status">Status: {{ uploadResults.status }}</p>
                                <p v-if="uploadResults.completed_at">Completed at: {{
                                    formatDate(uploadResults.completed_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 p-4 border-t border-gray-200 bg-gray-50 flex-none">
                    <button @click="closeUploadModal"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200">
                        Cancel
                    </button>
                    <button @click="uploadExcelFile"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-amber-600 hover:to-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all duration-200"
                        :disabled="!uploadFile || isUploading || !$page.props.auth.can.moh_inventory_create">
                        <svg v-if="isUploading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                            </path>
                        </svg>
                        {{ isUploading ? 'Uploading...' : 'Upload File' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit MOH Inventory Item Modal -->
        <div v-if="showEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Edit MOH Inventory Item</h3>
                        <button @click="closeEditModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="mt-4">
                        <!-- Warning for approved inventories -->
                        <div v-if="props.selectedInventory?.approved_at" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">Cannot Edit Approved Inventory</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>This MOH inventory has been approved and cannot be edited. Only pending, reviewed, or rejected inventories can be modified.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="updateMohItem" :class="{ 'opacity-50 pointer-events-none': props.selectedInventory?.approved_at }">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Product Name -->
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Name *</label>
                                    <Multiselect
                                        v-model="editForm.product"
                                        :options="props.products"
                                        :custom-label="product => product.name"
                                        placeholder="Select Product"
                                        :searchable="true"
                                        :allow-empty="false"
                                        :close-on-select="true"
                                        :clear-on-select="false"
                                        :preserve-search="false"
                                        :preserve-scroll="true"
                                        :show-labels="false"
                                        :max-height="200"
                                        :loading="false"
                                        :internal-search="true"
                                        :options-limit="100"
                                        :taggable="false"
                                        :multiple="false"
                                        :required="true"
                                        class="text-sm"
                                    />
                                </div>

                                <!-- Quantity -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <input v-model="editForm.quantity" type="number" step="0.01" min="0" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <!-- UOM -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit of Measure</label>
                                    <input v-model="editForm.uom" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <!-- Batch Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
                                    <input v-model="editForm.batch_number" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <!-- Expiry Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                                    <input v-model="editForm.expiry_date" type="date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <!-- Location -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                    <Multiselect
                                        v-model="editForm.location"
                                        :options="getEditFilteredLocations"
                                        placeholder="Select Location"
                                        :searchable="true"
                                        :allow-empty="true"
                                        :close-on-select="true"
                                        :clear-on-select="false"
                                        :preserve-search="false"
                                        :preserve-scroll="true"
                                        :show-labels="false"
                                        :max-height="200"
                                        :loading="false"
                                        :internal-search="true"
                                        :taggable="false"
                                        :multiple="false"
                                        :disabled="!editForm.warehouse_id"
                                        class="text-sm"
                                    />
                                </div>

                                <!-- Warehouse -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse *</label>
                                    <Multiselect
                                        v-model="editForm.warehouse"
                                        :options="props.warehouses"
                                        :custom-label="warehouse => warehouse.name"
                                        placeholder="Select Warehouse"
                                        :searchable="true"
                                        :allow-empty="false"
                                        :close-on-select="true"
                                        :clear-on-select="false"
                                        :preserve-search="false"
                                        :preserve-scroll="true"
                                        :show-labels="false"
                                        :max-height="200"
                                        :loading="false"
                                        :internal-search="true"
                                        :options-limit="100"
                                        :taggable="false"
                                        :multiple="false"
                                        :required="true"
                                        class="text-sm"
                                    />
                                </div>

                                <!-- Unit Cost -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit Cost</label>
                                    <input v-model="editForm.unit_cost" type="number" step="0.01" min="0"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <!-- Total Cost -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost (Calculated)</label>
                                    <input v-model="editForm.total_cost" type="number" step="0.01" min="0" readonly
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500">
                                    <p class="text-xs text-gray-500 mt-1">Automatically calculated as: Quantity × Unit Cost</p>
                                </div>

                                <!-- Barcode -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Barcode</label>
                                    <input v-model="editForm.barcode" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                </div>

                            </div>

                            <!-- Modal Footer -->
                            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                                <button type="button" @click="closeEditModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="isUpdating"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                                    <svg v-if="isUpdating" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ isUpdating ? 'Updating...' : 'Update Item' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create MOH Inventory Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative mx-auto p-5 border w-full h-full max-h-screen shadow-lg bg-white my-8">
                <div class="h-full max-h-screen flex flex-col">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-gray-200 flex-shrink-0">
                        <h3 class="text-lg font-medium text-gray-900">Create New MOH Inventory</h3>
                        <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="createMohInventory" class="flex flex-col flex-1 min-h-0">
                        <!-- Basic Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                <input v-model="createForm.date" type="date" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="flex-1 flex flex-col mb-6 min-h-0">
                            <div class="flex items-center justify-between mb-4 flex-shrink-0">
                                <h4 class="text-md font-medium text-gray-900">Inventory Items</h4>
                                <button type="button" @click="addCreateItem"
                                    class="inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Add Row
                                </button>
                            </div>

                            <div class="flex-1 overflow-auto min-h-0">
                                <table class="min-w-full divide-y divide-gray-200" style="min-width: 1500px;">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="width: 400px;">Item *</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="width: 180px;">UoM</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="width: 250px;">Source</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="width: 180px;">Quantity *</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="width: 200px;">Batch & Expiry</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="width: 300px;">Warehouse & Location *</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="width: 200px;">Costs</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(item, index) in createItems" :key="index" class="hover:bg-gray-50">
                                            <!-- Item (Product) -->
                                            <td class="px-3 py-2 border-r border-gray-200" style="width: 400px;">
                                                <Multiselect
                                                    v-model="item.product"
                                                    :options="props.products"
                                                    :custom-label="product => product.name"
                                                    placeholder="Select Product"
                                                    :searchable="true"
                                                    :allow-empty="false"
                                                    :close-on-select="true"
                                                    :clear-on-select="false"
                                                    :preserve-search="false"
                                                    :preserve-scroll="true"
                                                    :show-labels="false"
                                                    :max-height="200"
                                                    :loading="false"
                                                    :internal-search="true"
                                                    :options-limit="100"
                                                    :taggable="false"
                                                    :multiple="false"
                                                    :required="true"
                                                    class="text-sm"
                                                />
                                            </td>

                                            <!-- UoM -->
                                            <td class="px-3 py-2 border-r border-gray-200" style="width: 180px;">
                                                <input v-model="item.uom" type="text"
                                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                            </td>

                                            <!-- Source -->
                                            <td class="px-3 py-2 border-r border-gray-200" style="width: 250px;">
                                                <input v-model="item.source" type="text"
                                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                            </td>

                                            <!-- Quantity -->
                                            <td class="px-3 py-2 border-r border-gray-200" style="width: 180px;">
                                                <input v-model="item.quantity" type="number" step="0.01" min="0" required
                                                    @input="calculateCreateItemTotal(item)"
                                                    class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                            </td>

                                            <!-- Batch & Expiry Combined -->
                                            <td class="px-3 py-2 border-r border-gray-200" style="width: 200px;">
                                                <div class="space-y-2">
                                                    <!-- Batch Number -->
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Batch No</label>
                                                        <input v-model="item.batch_number" type="text"
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <!-- Expiry Date -->
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Expiry Date</label>
                                                        <input v-model="item.expiry_date" type="date"
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Warehouse & Location Combined -->
                                            <td class="px-3 py-2 border-r border-gray-200" style="width: 300px;">
                                                <div class="space-y-2">
                                                    <!-- Warehouse -->
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Warehouse *</label>
                                                        <select v-model="item.warehouse_id" required @change="filterLocationsByWarehouse(item)"
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="">Select Warehouse</option>
                                                            <option v-for="warehouse in props.warehouses" :key="warehouse.id" :value="warehouse.id">
                                                                {{ warehouse.name }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <!-- Location -->
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Location</label>
                                                        <select v-model="item.location"
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="">Select Location</option>
                                                            <option v-for="location in props.locations" :key="location" :value="location">
                                                                {{ location }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Costs Combined -->
                                            <td class="px-3 py-2 border-r border-gray-200" style="width: 200px;">
                                                <div class="space-y-2">
                                                    <!-- Unit Cost -->
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Unit Cost</label>
                                                        <input v-model="item.unit_cost" type="number" step="0.01" min="0"
                                                            @input="calculateCreateItemTotal(item)"
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <!-- Total Cost -->
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Total Cost (Calculated)</label>
                                                        <input v-model="item.total_cost" type="number" step="0.01" min="0" readonly
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 rounded bg-gray-50 text-gray-500">
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Actions -->
                                            <td class="px-3 py-2 w-20">
                                                <button type="button" @click="removeCreateItem(index)"
                                                    :disabled="createItems.length <= 1"
                                                    class="text-red-600 hover:text-red-800 disabled:text-gray-400 disabled:cursor-not-allowed">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>

                    <!-- Modal Footer -->
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 flex-shrink-0">
                        <button type="button" @click="closeCreateModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="button" @click="createMohInventory" :disabled="isCreating || !$page.props.auth.can.moh_inventory_create"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                            <svg v-if="isCreating" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isCreating ? 'Creating...' : 'Create MOH Inventory' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
