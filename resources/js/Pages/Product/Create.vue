<template>
    <AuthenticatedLayout title="Create Product" description="Add a new product to your inventory" img="/assets/images/products.png">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="flex items-center space-x-3 mb-1">
                        <!-- Product Icon -->
                        <div class="flex items-center justify-center w-8 h-8 bg-gradient-to-r from-purple-400 to-indigo-500 rounded-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">Create New Product</h1>
                    </div>
                    <p class="text-gray-600 text-sm">Add a new product to your inventory system</p>
                </div>
                <Link
                    :href="route('products.index')"
                    class="inline-flex items-center px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Products
                </Link>
            </div>
        </div>

        <!-- Form Section -->
        <div class="mb-[100px]">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900">Product Information</h3>
                <p class="text-sm text-gray-500 mt-1">Fill in the details below to create a new product</p>
            </div>
            
            <div>
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 xs:grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- First Column - Product Name -->
                        <div>
                            <InputLabel for="name" value="Product Name" class="text-sm font-medium text-gray-700 mb-2" />
                            <input
                                id="name"
                                type="text"
                                class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                                v-model="form.name"
                                required
                                autofocus
                                placeholder="Enter product name"
                            />
                        </div>

                        <!-- Second Column - Category and Dosage Form -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Category -->
                            <div>
                                <InputLabel for="category_id" value="Category" class="text-sm font-medium text-gray-700 mb-2" />
                                <Multiselect 
                                    v-model="form.category" 
                                    :value="form.category_id" 
                                    :options="[ { id: 'new', name: '+ Add New Category', isAddNew: true}, ...props.categories.data]"
                                    :searchable="true" 
                                    :close-on-select="true" 
                                    :show-labels="false"
                                    :allow-empty="true" 
                                    placeholder="Select Category" 
                                    track-by="id" 
                                    label="name"
                                    class="text-sm"
                                    @select="handleCategorySelect"
                                >
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }" class="py-1">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium flex items-center">
                                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                                Add New Category
                                            </span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>

                            <!-- Dosage Form -->
                            <div>
                                <InputLabel for="dosage_id" value="Dosage Form" class="text-sm font-medium text-gray-700 mb-2" />
                                <Multiselect 
                                    v-model="form.dosage" 
                                    :value="form.dosage_id" 
                                    :options="[{ id: 'new', name: '+ Add New Dosage form', isAddNew: true }, ...props.dosages.data]"
                                    :searchable="true" 
                                    :close-on-select="true" 
                                    :show-labels="false"
                                    :allow-empty="true" 
                                    placeholder="Select Dosage Form" 
                                    track-by="id" 
                                    label="name"
                                    class="text-sm"
                                    @select="handleDosageSelect"
                                >
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }" class="py-1">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium flex items-center">
                                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                                Add New Dosage Form
                                            </span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>
                        </div>
                        <!-- Tracert Type -->
                        <div>
                            <InputLabel for="tracert_type" value="Tracert Type" class="text-sm font-medium text-gray-700 mb-2" />
                            <Multiselect
                                v-model="form.tracert_type"
                                :options="['Warehouse', 'Facility']"
                                :multiple="true"
                                :searchable="false"
                                :close-on-select="false"
                                :clear-on-select="false"
                                :preserve-search="true"
                                :show-labels="false"
                                placeholder="Select tracert types"
                                class="text-sm"
                            >
                                <template v-slot:selection="{ values, search, isOpen }">
                                    <span class="multiselect__single" v-if="values.length && !isOpen">
                                        {{ values.join(', ') }}
                                    </span>
                                </template>
                            </Multiselect>
                            <p class="text-sm text-gray-500 mt-2 flex items-center">
                                <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Select where this product should be traced (optional)
                            </p>
                        </div>
                    </div>

                    <!-- Facility Types -->
                    <div>
                        <InputLabel for="facility_types" value="Applicable Facility Types" class="text-sm font-medium text-gray-700 mb-2" />
                        <Multiselect
                            v-model="form.facility_types"
                            :options="facilityTypes"
                            :multiple="true"
                            :searchable="true"
                            :close-on-select="false"
                            :clear-on-select="false"
                            :preserve-search="true"
                            :show-labels="false"
                            placeholder="Select facility types"
                            class="text-sm"
                        >
                            <template v-slot:selection="{ values, search, isOpen }">
                                <span class="multiselect__single" v-if="values.length && !isOpen">
                                    {{ values.join(', ') }}
                                </span>
                            </template>
                        </Multiselect>
                        <p class="text-sm text-gray-500 mt-2 flex items-center">
                            <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Select the facility types where this product can be used
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center gap-2 justify-end pt-6 border-t border-gray-200">
                        <Link
                            :href="route('products.index')"
                            class="inline-flex items-center px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                        >
                            Exit
                        </Link>
                        <PrimaryButton 
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 shadow-sm" 
                            :disabled="processing || !$page.props.auth.can.product_manage && !$page.props.auth.isAdmin"
                        >
                            <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ processing ? "Creating Product..." : "Create Product" }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Category Modal -->
    <div v-if="showCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click="showCategoryModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Create New Category</h3>
                    <p class="text-sm text-gray-500 mt-1">Add a new product category</p>
                </div>
                <button
                    @click="showCategoryModal = false"
                    class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <div class="mb-4">
                    <InputLabel for="new-category-name" value="Category Name" class="text-sm font-medium text-gray-700 mb-2" />
                    <input
                        id="new-category-name"
                        type="text"
                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                        v-model="newCategory.name"
                        placeholder="Enter category name"
                        autofocus
                    />
                </div>
            </div>

            <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
                <button 
                    @click="showCategoryModal = false" 
                    :disabled="processing" 
                    type="button" 
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                >
                    Cancel
                </button>
                <button 
                    @click="createNewCategory" 
                    :disabled="processing" 
                    type="button" 
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    {{ processing ? 'Creating...' : 'Create Category' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Dosage Modal -->
    <div v-if="showDosageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click="showDosageModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Create New Dosage Form</h3>
                    <p class="text-sm text-gray-500 mt-1">Add a new dosage form</p>
                </div>
                <button
                    @click="showDosageModal = false"
                    class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <div class="mb-4">
                    <InputLabel for="new-dosage-name" value="Dosage Form Name" class="text-sm font-medium text-gray-700 mb-2" />
                    <input
                        id="new-dosage-name"
                        type="text"
                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                        v-model="newDosage.name"
                        placeholder="Enter dosage form name"
                        autofocus
                    />
                </div>
            </div>

            <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
                <button 
                    @click="showDosageModal = false" 
                    :disabled="processing" 
                    type="button" 
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                >
                    Cancel
                </button>
                <button 
                    @click="createNewDosage" 
                    :disabled="processing" 
                    type="button" 
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    {{ processing ? 'Creating...' : 'Create Dosage Form' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useToast } from "vue-toastification";
import axios from 'axios';
import Swal from 'sweetalert2';

import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';

const toast = useToast();

const props = defineProps({
    categories: {
        type: Object,
        required: true,
        default: () => ({
            data: []
        })
    },
    dosages: {
        type: Object,
        required: true,
        default: () => ({
            data: []
        })
    },
    facilityTypes: {
        type: Array,
        required: false,
        default: () => ([])
    }
});

const form = ref({
    id: null,
    name: '',
    category_id: '',
    dosage_id: '',
    category: null,
    dosage: null,
    facility_types: [],
    tracert_type: [],
});

const processing = ref(false);
const showCategoryModal = ref(false);
const showDosageModal = ref(false);
const newCategory = ref({ name: '' });
const newDosage = ref({ name: '' });

const facilityTypes = ref(props.facilityTypes || []);

const submit = async () => {
    // Create a payload with the correct format
    const payload = {
        ...form.value,
        // Ensure we're sending the IDs, not the objects
        category_id: form.value.category?.id,
        dosage_id: form.value.dosage?.id,
        // Convert tracert_type array to JSON string
        tracert_type: form.value.tracert_type && form.value.tracert_type.length > 0 ? JSON.stringify(form.value.tracert_type) : null
    };
    console.log(payload);
    processing.value = true;
    await axios.post(route('products.store'), payload)
    .then((response) => {
        processing.value = false;
        Swal.fire({
            icon: 'success',
            title: 'Product Created',
            text: response.data
        }).then(() => {
            router.visit(route('products.index'))
        });
    })
    .catch((error) => {
        processing.value = false;
        console.error('Error creating product:', error);
        toast.error(error.response?.data || 'Failed to create product');
    });
};

// Function to create a new category
async function createNewCategory() {
    console.log(newCategory.value);
    if (!newCategory.value.name) {
        toast.error('Category name is required');
        return;
    }
    
    try {
        processing.value = true;
        const response = await axios.post(route('products.categories.store'), newCategory.value);
        
        // Add the new category to the categories list
        const newCategoryObj = {
            id: response.data.id || response.data,  // Handle different response formats
            name: newCategory.value.name
        };
        
        props.categories.data.push(newCategoryObj);
        
        // Select the newly created category
        form.value.category = newCategoryObj;
        form.value.category_id = newCategoryObj.id;
        
        // Reset and close modal
        newCategory.value = { name: '' };
        showCategoryModal.value = false;
        
        toast.success('Category created successfully');
        processing.value = false;
    } catch (error) {
        processing.value = false;
        console.log(error);
        toast.error(error.response?.data || 'Failed to create category');
    }
}

// Function to create a new dosage form
async function createNewDosage() {
    if (!newDosage.value.name) {
        toast.error('Dosage form name is required');
        return;
    }
    
    try {
        processing.value = true;
        const response = await axios.post(route('products.dosages.store'), newDosage.value);
        
        // Add the new dosage to the dosages list
        const newDosageObj = {
            id: response.data.id || response.data,  // Handle different response formats
            name: newDosage.value.name
        };
        
        props.dosages.data.push(newDosageObj);
        
        // Select the newly created dosage
        form.value.dosage = newDosageObj;
        form.value.dosage_id = newDosageObj.id;
        
        // Reset and close modal
        newDosage.value = { name: '' };
        showDosageModal.value = false;
        
        toast.success('Dosage form created successfully');
        processing.value = false;
    } catch (error) {
        processing.value = false;
        toast.error(error.response?.data || 'Failed to create dosage form');
    }
};

function handleCategorySelect(selected){
    if(selected.isAddNew){
        form.value.category_id = "";
        form.value.category = null;
        showCategoryModal.value = true;
        return;
    }

    // Make sure we're setting both the object and the ID
    form.value.category = selected;
    form.value.category_id = selected.id;
}


function handleDosageSelect(selected){
    if(selected.isAddNew){
        form.value.dosage_id = "";
        form.value.dosage = null;
        showDosageModal.value = true;
        return;
    }

    // Make sure we're setting both the object and the ID
    form.value.dosage = selected;
    form.value.dosage_id = selected.id;
}


</script>
