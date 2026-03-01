<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';

import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, watch, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';
import Swal from 'sweetalert2';
import moment from 'moment';

const props = defineProps({
    asset: { type: Object, required: true },
    assetItem: { type: Object, required: false, default: null },
    locations: { type: Array, required: true, default: () => [] },
    categories: { type: Array, required: true, default: () => [] },
    types: { type: Array, required: false, default: () => [] },
    fundSources: { type: Array, required: true, default: () => [] },
    regions: { type: Array, required: true, default: () => [] },
    assignees: { type: Array, required: false, default: () => [] },
    facilities: { type: Array, required: false, default: () => [] },
});

const toast = useToast();
const processing = ref(false);

// Options with "+ Add New" entries
const locationOptions = ref([]);
const categoryOptions = ref([]);
const fundSourceOptions = ref([]);
const regionOptions = ref([]);
const typeOptions = ref([]);

watch(() => props.locations, (list) => {
    locationOptions.value = [{ id: 'new', name: '+ Add New Location', isAddNew: true }, ...(list || [])];
}, { immediate: true, deep: true });

watch(() => props.categories, (list) => {
    categoryOptions.value = [{ id: 'new', name: '+ Add New Category', isAddNew: true }, ...(list || [])];
}, { immediate: true, deep: true });

watch(() => props.fundSources, (list) => {
    fundSourceOptions.value = [{ id: 'new', name: '+ Add New Fund Source', isAddNew: true }, ...(list || [])];
}, { immediate: true, deep: true });

watch(() => props.regions, (list) => {
    regionOptions.value = [{ id: 'new', name: '+ Add New Region', isAddNew: true }, ...(list || [])];
}, { immediate: true, deep: true });

watch(() => props.types, (list) => {
    typeOptions.value = [{ id: 'new', name: '+ Add New Type', isAddNew: true }, ...(list || [])];
}, { immediate: true, deep: true });

const facilityOptions = computed(() => (props.facilities || []).map(f => ({ id: f.id, name: f.name, district: f.district, region: f.region })));

const filteredTypeOptions = computed(() => {
    const categoryId = form.value.asset_category_id || form.value.category?.id;
    if (!categoryId) return [{ id: 'new', name: '+ Add New Type', isAddNew: true }];
    const base = typeOptions.value.filter(t => !t.isAddNew && (!t.asset_category_id || t.asset_category_id === categoryId));
    return [...base, { id: 'new', name: '+ Add New Type', isAddNew: true }];
});

// Assignees
const showAssigneeModal = ref(false);
const newAssignee = ref({ name: '', email: '', phone: '', department: '' });
const isSavingAssignee = ref(false);
// Local list to allow appending newly created assignees
const assigneesList = ref([]);
watch(() => props.assignees, (list) => {
    assigneesList.value = Array.isArray(list) ? [...list] : [];
}, { immediate: true, deep: true });
const assigneeOptions = computed(() => [
    { id: 'new', name: '+ Add New Assignee', isAddNew: true },
    ...props.assignees.map(a => ({ id: a.id, name: a.name }))   
]);

const onAssigneeSelect = (opt) => {
    if (!opt) return;
    if (opt.isAddNew) { showAssigneeModal.value = true; return; }
    // Set assignee data
    form.value.assignee_id = opt.id;
    form.value.assignee = { id: opt.id, name: opt.name };
    form.value.assigned_user = { id: opt.id, name: opt.name };
    form.value.assigned_to = '';
};

const onAssigneeClear = () => {
    form.value.assignee_id = null;
    form.value.assignee = null;
    form.value.assigned_to = '';
    form.value.assigned_user = null;
};

const createAssignee = async (e) => {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (!newAssignee.value.name) { toast.error('Full name is required'); return; }
    isSavingAssignee.value = true;
    try {
        const { data } = await axios.post(route('assets.assignees.store'), {
            name: newAssignee.value.name,
            email: newAssignee.value.email || null,
            phone: newAssignee.value.phone || null,
            department: newAssignee.value.department || null,
        });
        // Append to local list and select it
        assigneesList.value = [...assigneesList.value, data];
        form.value.assignee = { id: data.id, name: data.name };
        form.value.assignee_id = data.id;
        form.value.assigned_user = { id: data.id, name: data.name };
        form.value.assigned_to = '';
        newAssignee.value = { name: '', email: '', phone: '', department: '' };
        showAssigneeModal.value = false;
        toast.success('Assignee created');
    } catch (e) {
        toast.error(e.response?.data || 'Failed to create assignee');
    } finally {
        isSavingAssignee.value = false;
    }
};

// Form
const toDateInputValue = (value) => {
    if (!value) return '';
    const m = moment(value);
    return m.isValid() ? m.format('YYYY-MM-DD') : '';
};
const form = ref({});

onMounted(() => {
    // Merge asset and assetItem data into the form
    form.value = {
        // Asset-level fields
        ...props.asset,
        
        // AssetItem-level fields (if assetItem exists)
        ...(props.assetItem && {
            asset_tag: props.assetItem.asset_tag,
            asset_name: props.assetItem.asset_name,
            serial_number: props.assetItem.serial_number,
            original_value: props.assetItem.original_value,
            status: props.assetItem.status,
            assignee_id: props.assetItem.assignee_id,
            asset_category_id: props.assetItem.asset_category_id,
            asset_type_id: props.assetItem.asset_type_id,
            
            // Relationships
            category: props.assetItem.category,
            type: props.assetItem.type,
            assignee: props.assetItem.assignee,
        }),
        
        // Map some fields to match the form expectations
        name: props.assetItem?.asset_name || props.asset?.name,
        tag_no: props.assetItem?.asset_tag || props.asset?.tag_no,
        
        // Format acquisition_date for HTML date input (YYYY-MM-DD)
        acquisition_date: props.asset?.acquisition_date ? moment(props.asset.acquisition_date).format('YYYY-MM-DD') : '',
    };
    
    console.log('Form initialized with:', form.value);
    console.log('Asset data:', props.asset);
    console.log('AssetItem data:', props.assetItem);
    console.log('Acquisition date (raw):', props.asset?.acquisition_date);
    console.log('Acquisition date (formatted):', form.value.acquisition_date);
});

// Keep type cleared when category cleared
watch(() => form.value.category, (newVal) => {
    if (!newVal) { form.value.type = null; form.value.type_id = null; }
});

// Sub-locations
const subLocations = ref([]);
const loadSubLocations = async (locationId) => {
    if (!locationId) { subLocations.value = []; form.value.sub_location = null; form.value.sub_location_id = null; return; }
    try {
        const response = await axios.get(route('assets.locations.sub-locations', { location: locationId }));
        subLocations.value = response.data;
    } catch (e) { toast.error('Error loading sub-locations'); }
};

// Status options (match Create.vue)
const statuses = ref([
    { value: 'active', label: 'Active' },
    { value: 'in_use', label: 'In Use' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'pending_approval', label: 'Pending Approval' },
    { value: 'retired', label: 'Retired' },
    { value: 'disposed', label: 'Disposed' },
]);

// Normalize status in case asset has a value not allowed for editing
const allowedStatusValues = computed(() => statuses.value.map(s => s.value));

// Watch for form initialization to normalize status
watch(() => form.value.status, (newStatus) => {
    if (newStatus && !allowedStatusValues.value.includes(newStatus)) {
        form.value.status = 'active';
    }
});

// Modals + new entries
const showLocationModal = ref(false);
const showSubLocationModal = ref(false);
const showCategoryModal = ref(false);
const showFundSourceModal = ref(false);
const showRegionModal = ref(false);
const showTypeModal = ref(false);

const newLocation = ref('');
const newSubLocation = ref('');
const newCategory = ref('');
const newFundSource = ref('');
const newRegion = ref('');
const newType = ref('');

const isNewLocation = ref(false);
const isNewCategory = ref(false);
const isNewFundSource = ref(false);
const isNewRegion = ref(false);
const isNewType = ref(false);
const selectedLocationForSub = ref(null);

const handleCategorySelect = (selected) => {
    if (!selected) { 
        form.value.asset_category_id = null; 
        form.value.category = null; 
        return; 
    }
    if (selected.isAddNew) { showCategoryModal.value = true; return; }
    form.value.asset_category_id = selected.id; 
    form.value.category = selected;
};

const handleTypeSelect = (selected) => {
    if (!selected) { 
        form.value.asset_type_id = null; 
        form.value.type = null; 
        return; 
    }
    if (selected.isAddNew) { showTypeModal.value = true; return; }
    form.value.asset_type_id = selected.id; 
    form.value.type = selected;
};

// Create a new Type from the modal
const createType = async () => {
    if (!newType.value) {
        toast.error('Please enter a type name');
        return;
    }
    isNewType.value = true;
    try {
        const response = await axios.post(route('assets.types.store'), {
            name: newType.value,
            asset_category_id: form.value.asset_category_id || (form.value.category?.id ?? null),
        });
        const newTypeData = response.data;
        typeOptions.value = [
            ...typeOptions.value.filter(t => !t.isAddNew),
            newTypeData,
            { id: 'new', name: '+ Add New Type', isAddNew: true },
        ];
        form.value.type = newTypeData;
        form.value.asset_type_id = newTypeData.id;
        newType.value = '';
        showTypeModal.value = false;
        toast.success('Type created successfully');
    } catch (e) {
        toast.error(e.response?.data || 'Failed to create type');
    } finally {
        isNewType.value = false;
    }
};

const handleRegionSelect = (selected) => {
    if (!selected) { 
        form.value.region_id = null; 
        form.value.region = null; 
        return; 
    }
    if (selected.isAddNew) { showRegionModal.value = true; return; }
    form.value.region_id = selected.id; 
    form.value.region = selected;
};

const handleFundSourceSelect = (selected) => {
    if (!selected) { 
        form.value.fund_source_id = null; 
        form.value.fund_source = null; 
        return; 
    }
    if (selected.isAddNew) { showFundSourceModal.value = true; return; }
    form.value.fund_source_id = selected.id; 
    form.value.fund_source = selected;
};

const handleLocationSelect = (selected) => {
    if (!selected) { 
        form.value.asset_location_id = null; 
        form.value.asset_location = null; 
        form.value.sub_location = null; 
        form.value.sub_location_id = null; 
        subLocations.value = []; 
        return; 
    }
    if (selected.isAddNew) { showLocationModal.value = true; return; }
    form.value.asset_location_id = selected.id; 
    form.value.asset_location = selected; 
    selectedLocationForSub.value = selected.id; 
    form.value.sub_location = null; 
    form.value.sub_location_id = null; 
    loadSubLocations(selected.id);
};

const handleSubLocationSelect = (selected) => {
    if (!selected) { 
        form.value.sub_location_id = null; 
        form.value.sub_location = null; 
        return; 
    }
    if (selected.isAddNew) { showSubLocationModal.value = true; return; }
    form.value.sub_location_id = selected.id; 
    form.value.sub_location = selected;
};

const handleFacilitySelect = (selected) => {
    if (!selected) return;
    form.value.facility_id = selected.id;
    form.value.facility = selected;
};

const handleFacilityClear = () => {
    form.value.facility_id = null;
    form.value.facility = null;
};

const createLocation = async () => {
    if (!newLocation.value) { toast.error('Please enter a location name'); return; }
    isNewLocation.value = true;
    try {
        const { data } = await axios.post(route('assets.locations.store'), { name: newLocation.value });
        locationOptions.value = [...locationOptions.value.filter(l => !l.isAddNew), data, { id: 'new', name: '+ Add New Location', isAddNew: true }];
        form.value.asset_location = data; 
        form.value.asset_location_id = data.id; 
        selectedLocationForSub.value = data.id;
        newLocation.value = ''; 
        showLocationModal.value = false; 
        await loadSubLocations(data.id); 
        toast.success('Location created successfully');
    } catch (e) { toast.error(e.response?.data || 'Error creating location'); } finally { isNewLocation.value = false; }
};

const createSubLocation = async () => {
    if (!newSubLocation.value || !selectedLocationForSub.value) { toast.error('Please enter a sub-location name and select a location'); return; }
    try {
        const { data } = await axios.post(route('assets.locations.sub-locations.store'), { name: newSubLocation.value, asset_location_id: selectedLocationForSub.value });
        subLocations.value = [...subLocations.value, data]; 
        form.value.sub_location = data; 
        form.value.sub_location_id = data.id; 
        newSubLocation.value = ''; 
        showSubLocationModal.value = false; 
        toast.success('Sub-location created successfully');
    } catch (e) { toast.error(e.response?.data || 'Error creating sub-location'); }
};

const createCategory = async () => {
    if (!newCategory.value) { toast.error('Please enter a category name'); return; }
    isNewCategory.value = true;
    try {
        const { data } = await axios.post(route('assets.categories.store'), { name: newCategory.value });
        categoryOptions.value = [...categoryOptions.value.filter(c => !c.isAddNew), data, { id: 'new', name: '+ Add New Category', isAddNew: true }];
        form.value.category = data; 
        form.value.asset_category_id = data.id; 
        newCategory.value = ''; 
        showCategoryModal.value = false; 
        toast.success('Category created successfully');
    } catch (e) { toast.error(e.response?.data || 'Error creating category'); } finally { isNewCategory.value = false; }
};

const createFundSource = async () => {
    if (!newFundSource.value) { toast.error('Please enter a fund source name'); return; }
    isNewFundSource.value = true;
    try {
        const { data } = await axios.post(route('assets.fund-sources.store'), { name: newFundSource.value });
        fundSourceOptions.value = [...fundSourceOptions.value.filter(f => !f.isAddNew), data, { id: 'new', name: '+ Add New Fund Source', isAddNew: true }];
        form.value.fund_source = data; 
        form.value.fund_source_id = data.id; 
        newFundSource.value = ''; 
        showFundSourceModal.value = false; 
        toast.success('Fund Source created successfully');
    } catch (e) { toast.error(e.response?.data || 'Error creating fund source'); } finally { isNewFundSource.value = false; }
};

const createRegion = async () => {
    if (!newRegion.value) { toast.error('Please enter a region name'); return; }
    isNewRegion.value = true;
    try {
        const { data } = await axios.post(route('assets.regions.store'), { name: newRegion.value });
        regionOptions.value = [...regionOptions.value.filter(r => !r.isAddNew), data, { id: 'new', name: '+ Add New Region', isAddNew: true }];
        form.value.region = data; 
        form.value.region_id = data.id; 
        newRegion.value = ''; 
        showRegionModal.value = false; 
        toast.success('Region created successfully');
    } catch (e) { toast.error(e.response?.data || 'Error creating region'); } finally { isNewRegion.value = false; }
};

const submit = async () => {
    processing.value = true;
    
    // Prepare the data in the format the backend expects
    const updateData = {
        // Asset-level fields
        region_id: form.value.region_id,
        asset_location_id: form.value.asset_location_id,
        sub_location_id: form.value.sub_location_id,
        facility_id: form.value.facility_id || null,
        fund_source_id: form.value.fund_source_id,
        acquisition_date: form.value.acquisition_date, // Already in YYYY-MM-DD format from HTML input
        
        // AssetItem-level fields
        asset_item_data: {
            asset_tag: form.value.asset_tag,
            asset_category_id: form.value.asset_category_id,
            asset_type_id: form.value.asset_type_id,
            asset_name: form.value.asset_name,
            serial_number: form.value.serial_number,
            original_value: form.value.original_value,
            status: form.value.status,
            assignee_id: form.value.assignee_id,
        }
    };
    
    console.log('Submitting update data:', updateData);
    
    await axios.put(route('assets.update', props.asset.id), updateData)
    .then(response => {
        console.log(response);
        processing.value = false;
        Swal.fire({ title: 'Success!', text: 'Asset updated successfully', icon: 'success', confirmButtonText: 'OK' })
            .then(() => router.get(route('assets.index')));
    })
    .catch(error => {
        console.log(error);
        processing.value = false;
        toast.error(error.response?.data || 'Error updating asset');
    });
};
</script>

<template>
    <AuthenticatedLayout title="Assets management" description="Edit asset" img="/assets/images/asset-header.png">
        <Head title="Edit Asset" />
        <div class="overflow-hidden mb-[70px]">
            <div class="p-6 text-gray-900">
                <h2 class="text-2xl font-semibold mb-6">Edit Asset</h2>
                <form @submit.prevent="submit" novalidate class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-800 mt-4">Basic Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="tag_no" class="block text-sm font-medium text-gray-700">Tag No</label>
                            <input id="tag_no" type="text" 
                                    class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="e.g., AST-2025-001" v-model="form.tag_no" required />
                        </div>
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Asset Name</label>
                            <input id="name" type="text" 
                                    class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="e.g., Dell Latitude 7420" v-model="form.name" />
                        </div>
                        <div>
                            <label for="asset_tag" class="block text-sm font-medium text-gray-700">Asset Tag</label>
                            <input id="asset_tag" type="text" 
                                    class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="Internal tag, e.g., INV-000123" v-model="form.asset_tag" required />
                        </div>

                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Asset Category</label>
                            <div class="w-full">
                                    <Multiselect v-model="form.category" :options="categoryOptions" :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Category" track-by="id" label="name" @select="handleCategorySelect">
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Category</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>
                        </div>
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700">Asset Type</label>
                                <Multiselect v-model="form.type" :options="filteredTypeOptions" :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Type" track-by="id" label="name" :disabled="!form.category && !form.category_id" @select="handleTypeSelect">
                                <template v-slot:option="{ option }">
                                    <div :class="{ 'add-new-option': option.isAddNew }">
                                        <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Type</span>
                                        <span v-else>{{ option.name }}</span>
                                    </div>
                                </template>
                            </Multiselect>
                        </div>
                    </div>

                        <div>
                            <label for="serial_number" class="block text-sm font-medium text-gray-700">Serial Number</label>
                            <input id="serial_number" type="text" 
                                    class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="Manufacturer serial no" v-model="form.serial_number" required />
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-800 mt-8">Assignment</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="assigned_to" class="block text-sm font-medium text-gray-700">Assigned User (optional)</label>
                            <Multiselect
                                v-model="form.assignee"
                                :options="assigneeOptions"
                                :searchable="true"
                                :close-on-select="true"
                                :show-labels="false"
                                :allow-empty="true"
                                placeholder="Select user"
                                track-by="id"
                                label="name"
                                @select="onAssigneeSelect"
                                @remove="onAssigneeClear"
                                @clear="onAssigneeClear"
                            />
                        </div>
                        <div>
                            <label for="acquisition_date" class="block text-sm font-medium text-gray-700">Acquisition Date</label>
                            <input id="acquisition_date" type="date" class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500" v-model="form.acquisition_date" required />
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-800 mt-8">Location & Classification</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="region" class="block text-sm font-medium text-gray-700">Region</label>
                            <Multiselect v-model="form.region" :options="regionOptions" :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Region" track-by="id" label="name" @select="handleRegionSelect">
                                <template v-slot:option="{ option }">
                                    <div :class="{ 'add-new-option': option.isAddNew }">
                                        <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Region</span>
                                        <span v-else>{{ option.name }}</span>
                                    </div>
                                </template>
                            </Multiselect>
                        </div>
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                            <div class="w-full">
                                <Multiselect v-model="form.asset_location" :options="locationOptions" :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Location" track-by="id" label="name" @select="handleLocationSelect">
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Location</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>
                        </div>
                        <div>
                            <label for="sub_location" class="block text-sm font-medium text-gray-700">Sub Location</label>
                            <div class="w-full">
                                <Multiselect v-model="form.sub_location" :options="[...subLocations, { id: 'new', name: '+ Add New Sub-location', isAddNew: true } ]" :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Sub-location" track-by="id" label="name" :disabled="!form.asset_location_id" @select="handleSubLocationSelect">
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Sub-location</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mt-4">
                        <div class="md:col-span-2">
                            <label for="facility" class="block text-sm font-medium text-gray-700">Facility (optional)</label>
                            <Multiselect id="facility" v-model="form.facility" :options="facilityOptions" :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Facility" track-by="id" label="name" @select="handleFacilitySelect" @clear="handleFacilityClear">
                                <template v-slot:option="{ option }">
                                    <span>{{ option.name }}</span>
                                    <span v-if="option.district || option.region" class="text-gray-500 text-xs block">{{ [option.district, option.region].filter(Boolean).join(' · ') }}</span>
                                </template>
                            </Multiselect>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mt-4">
                        <div class="md:col-span-2">
                            <label for="facility" class="block text-sm font-medium text-gray-700">Facility (optional)</label>
                            <Multiselect id="facility" v-model="form.facility" :options="facilityOptions" :searchable="true" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Facility" track-by="id" label="name" @select="handleFacilitySelect" @clear="handleFacilityClear">
                                <template v-slot:option="{ option }">
                                    <span>{{ option.name }}</span>
                                    <span v-if="option.district || option.region" class="text-gray-500 text-xs block">{{ [option.district, option.region].filter(Boolean).join(' · ') }}</span>
                                </template>
                            </Multiselect>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mt-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status" class="mt-1 block w-full rounded border-gray-300" v-model="form.status" required>
                                <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="original_value" class="block text-sm font-medium text-gray-700">Original Value</label>
                            <input id="original_value" type="number" 
                                    class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                v-model="form.original_value" required />
                        </div>
                        <div>
                            <label for="fund_source" class="block text-sm font-medium text-gray-700">Fund Source</label>
                            <Multiselect id="fund_source" v-model="form.fund_source" :options="fundSourceOptions" :close-on-select="true" :show-labels="false" :allow-empty="true" placeholder="Select Fund Source" track-by="id" label="name" @select="handleFundSourceSelect">
                                <template v-slot:option="{ option }">
                                    <div :class="{ 'add-new-option': option.isAddNew }">
                                        <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Fund Source</span>
                                        <span v-else>{{ option.name }}</span>
                                    </div>
                                </template>
                            </Multiselect>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <Link :href="route('assets.index')" :disabled="processing">Exit</Link>
                        <PrimaryButton class="ml-4" :disabled="processing">{{ processing ? 'Saving...' : 'Update Asset' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- New Assignee Modal -->
        <Modal :show="showAssigneeModal" @close="showAssigneeModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Assignee</h2>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="new_assignee_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input id="new_assignee_name" name="new_assignee_name" type="text" 
                                class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="e.g., John Doe" :required="showAssigneeModal" v-model="newAssignee.name" />
                    </div>
                    <div>
                        <label for="new_assignee_email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
                        <input id="new_assignee_email" type="email" 
                                class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="name@example.com" v-model="newAssignee.email" />
                    </div>
                    <div>
                        <label for="new_assignee_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input id="new_assignee_phone" name="new_assignee_phone" type="text" 
                                class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="e.g., +1 555 123 4567" :required="showAssigneeModal" v-model="newAssignee.phone" />
                    </div>
                    <div>
                        <label for="new_assignee_department" class="block text-sm font-medium text-gray-700">Department</label>
                        <input id="new_assignee_department" name="new_assignee_department" type="text" 
                                class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 placeholder="e.g., IT" :required="showAssigneeModal" v-model="newAssignee.department" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showAssigneeModal = false" :disabled="isSavingAssignee">Cancel</SecondaryButton>
                    <PrimaryButton @click="createAssignee" :disabled="isSavingAssignee">{{ isSavingAssignee ? 'Saving...' : 'Save' }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Location Modal -->
        <Modal :show="showLocationModal" @close="showLocationModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Location</h2>
                <div class="mt-6">
                    <label for="new_location" class="block text-sm font-medium text-gray-700">Location Name</label>
                    <input id="new_location" type="text" 
                            class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 v-model="newLocation" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showLocationModal = false" :disabled="isNewLocation">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewLocation" @click="createLocation">{{ isNewLocation ? 'Waiting...' : 'Create new location' }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Sub-Location Modal -->
        <Modal :show="showSubLocationModal" @close="showSubLocationModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Sub-Location</h2>
                <div class="mt-6">
                    <label for="new_sub_location" class="block text-sm font-medium text-gray-700">Sub-Location Name</label>
                    <input id="new_sub_location" type="text" 
                            class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 v-model="newSubLocation" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showSubLocationModal = false" :disabled="isNewLocation">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewLocation" @click="createSubLocation">{{ isNewLocation ? 'Waiting...' : 'Create Sub-Location' }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Category Modal -->
        <Modal :show="showCategoryModal" @close="showCategoryModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Category</h2>
                <div class="mt-6">
                    <label for="new_category" class="block text-sm font-medium text-gray-700">Category Name</label>
                    <input id="new_category" type="text" 
                            class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 v-model="newCategory" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showCategoryModal = false" :disabled="isNewCategory">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewCategory" @click="createCategory">{{ isNewCategory ? 'Waiting...' : 'Create Category' }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Region Modal -->
        <Modal :show="showRegionModal" @close="showRegionModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Region</h2>
                <div class="mt-6">
                    <label for="new_region" class="block text-sm font-medium text-gray-700">Region Name</label>
                    <input id="new_region" type="text" 
                            class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 v-model="newRegion" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showRegionModal = false" :disabled="isNewRegion">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewRegion" @click="createRegion">{{ isNewRegion ? 'Waiting...' : 'Create Region' }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Fund Source Modal -->
        <Modal :show="showFundSourceModal" @close="showFundSourceModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Fund Source</h2>
                <div class="mt-6">
                    <label for="new_fund_source" class="block text-sm font-medium text-gray-700">Fund Source Name</label>
                    <input id="new_fund_source" type="text" 
                            class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 v-model="newFundSource" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showFundSourceModal = false" :disabled="isNewFundSource">Exit</SecondaryButton>
                    <PrimaryButton :disabled="isNewFundSource" @click="createFundSource">{{ isNewFundSource ? 'Waiting...' : 'Create Fund Source' }}</PrimaryButton>
                </div>
            </div>
        </Modal>
        
        <!-- New Type Modal -->
        <Modal :show="showTypeModal" @close="showTypeModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Type</h2>
                <div class="mt-6">
                    <label for="new_type" class="block text-sm font-medium text-gray-700">Type Name</label>
                    <input id="new_type" type="text" 
                            class="rounded-md border-gray-300 mt-1 block w-full shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
 v-model="newType" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showTypeModal = false" :disabled="isNewType">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewType" @click="createType">{{ isNewType ? 'Waiting...' : 'Create Type' }}</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
