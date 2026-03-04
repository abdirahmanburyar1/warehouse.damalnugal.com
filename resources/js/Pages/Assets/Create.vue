<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, Link } from "@inertiajs/vue3";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import Modal from "@/Components/Modal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { ref, watch, computed } from "vue";
import { useToast } from "vue-toastification";
import axios from "axios";
import Swal from "sweetalert2";
import moment from "moment";

const toast = useToast();
const processing = ref(false);
const isSubmitting = ref(false);

const props = defineProps({
    locations: {
        type: Array,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
        default: () => [],
    },
    types: {
        type: Array,
        required: false,
        default: () => [],
    },
    fundSources: {
        type: Array,
        required: true,
        default: () => [],
    },
    regions: {
        type: Array,
        required: true,
        default: () => [],
    },
    users: {
        type: Array,
        required: false,
        default: () => [],
    },
    assignees: {
        type: Array,
        required: false,
        default: () => [],
    },
    facilities: {
        type: Array,
        required: false,
        default: () => [],
    },
});

const locationOptions = ref([]);
const categoryOptions = ref([]);
const fundSourceOptions = ref([]);
const regionOptions = ref([]);

watch(
    () => props.categories,
    (newCategories) => {
        categoryOptions.value = [
            { id: "new", name: "+ Add New Category", isAddNew: true },
            ...newCategories,
        ];
    },
    { immediate: true, deep: true }
);

const typeOptions = ref([]);
watch(
    () => props.types,
    (newTypes) => {
        typeOptions.value = [
            { id: "new", name: "+ Add New Type", isAddNew: true },
            ...newTypes,
        ];
    },
    { immediate: true, deep: true }
);

const getFilteredTypeOptions = (category) => {
    if (!category) {
        return [{ id: "new", name: "+ Add New Type", isAddNew: true }];
    }
    const categoryId = category.id || category;
    const base = typeOptions.value.filter(
        t => !t.isAddNew && (!t.asset_category_id || t.asset_category_id === categoryId)
    );
    return [
        ...base,
        { id: "new", name: "+ Add New Type", isAddNew: true },
    ];
};

const usersAsOptions = computed(() => (props.users || []).map(u => ({ id: u.id, name: u.name })));

// Maintain a local list to allow appending newly created assignees
const assigneesList = ref([]);
watch(() => props.assignees, (list) => {
    assigneesList.value = Array.isArray(list) ? [...list] : [];
}, { immediate: true, deep: true });

const assigneeOptions = computed(() => [
    { id: 'new', name: '+ Add New Assignee', isAddNew: true },
    ...assigneesList.value.map(a => ({ id: a.id, name: a.name }))
]);

const showAssigneeModal = ref(false);
const newAssignee = ref({ name: '', email: '', phone: '', department: '' });
const isSavingAssignee = ref(false);

const onAssigneeSelect = (opt) => {
    if (!opt) return;
    if (opt.isAddNew) {
        showAssigneeModal.value = true;
        return;
    }
    form.value.assignee_id = opt.id;
    form.value.assigned_to = '';
    form.value.assignee_name = '';
    form.value.assignee_email = '';
    form.value.assignee_phone = '';
    form.value.assignee_department = '';
};

const onAssigneeClear = () => {
    form.value.assigned_to = '';
    form.value.assignee_id = null;
};

const createAssignee = async (e) => {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (!newAssignee.value.name) {
        toast.error('Full name is required');
        return;
    }
    isSavingAssignee.value = true;
    try {
        const { data } = await axios.post(route('assets.assignees.store'), {
            name: newAssignee.value.name,
            email: newAssignee.value.email || null,
            phone: newAssignee.value.phone || null,
            department: newAssignee.value.department || null,
        });
        assigneesList.value = [...assigneesList.value, data];
        form.value.assigned_user = { id: data.id, name: data.name };
        form.value.assignee_id = data.id;
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

const showTypeModal = ref(false);
const newType = ref("");
const isNewType = ref(false);

const handleTypeSelect = (selected) => {
    if (!selected) {
        form.value.type_id = null;
        form.value.type = null;
        return;
    }
    if (selected.isAddNew) {
        showTypeModal.value = true;
        return;
    }
    form.value.type_id = selected.id;
    form.value.type = selected;
};

const createType = async () => {
    if (!newType.value) {
        toast.error("Please enter a type name");
        return;
    }
    isNewType.value = true;
    try {
        const response = await axios.post(route('assets.types.store'), {
            name: newType.value,
            asset_category_id: form.value.asset_category_id || (form.value.asset_category?.id ?? null),
        });

        const newTypeData = response.data;
        typeOptions.value = [
            ...typeOptions.value.filter(t => !t.isAddNew),
            newTypeData,
            { id: "new", name: "+ Add New Type", isAddNew: true },
        ];
        form.value.type = newTypeData;
        form.value.type_id = newTypeData.id;
        newType.value = "";
        showTypeModal.value = false;
        toast.success("Type created successfully");
    } catch (error) {
        console.error("Error creating type:", error);
        const data = error.response?.data;
        let message = "Error creating type";
        if (data?.errors && typeof data.errors === "object") {
            const firstKey = Object.keys(data.errors)[0];
            const firstMsg = firstKey ? data.errors[firstKey]?.[0] : null;
            if (firstMsg) message = firstMsg;
        } else if (data?.message && typeof data.message === "string") {
            message = data.message;
        } else if (typeof data === "string") {
            message = data;
        }
        toast.error(message);
    } finally {
        isNewType.value = false;
    }
};

watch(
    () => props.locations,
    (newLocations) => {
        locationOptions.value = [
            { id: "new", name: "+ Add New Location", isAddNew: true },
            ...newLocations,
        ];
    },
    { immediate: true, deep: true }
);

watch(
    () => props.fundSources,
    (newFundSources) => {
        fundSourceOptions.value = [
            { id: "new", name: "+ Add New Fund Source", isAddNew: true },
            ...newFundSources,
        ];
    },
    { immediate: true, deep: true }
);

watch(
    () => props.regions,
    (newRegions) => {
        regionOptions.value = [
            { id: "new", name: "+ Add New Region", isAddNew: true },
            ...newRegions,
        ];
    },
    { immediate: true, deep: true }
);

const facilityOptions = computed(() =>
    (props.facilities || []).map((f) => ({ id: f.id, name: f.name, district: f.district, region: f.region }))
);

const form = ref({
    asset_number: "",
    acquisition_date: "",
    fund_source_id: "",
    region_id: "",
    asset_location_id: "",
    sub_location_id: "",
    facility_id: null,
    facility: null,
    asset_items: [
        {
            asset_tag: "",
            asset_name: "",
            serial_number: "",
            asset_category_id: null,
            asset_category: null,
            asset_type_id: null,
            asset_type: null,
            assignee_id: null,
            assignee: null,
            status: "pending_approval",
            original_value: "",
        },
    ],
});

const subLocations = ref([]);

const filteredSubLocations = computed(() => {
    if (!form.value.asset_location) return [];
    const locId = form.value.asset_location.id || form.value.asset_location;
    return subLocations.value.filter((sub) => sub.location_id === locId);
});

function onLocationChange(selected) {
    form.value.sub_location = null;
}

const loadSubLocations = async (locationId) => {
    if (!locationId) {
        subLocations.value = [];
        form.value.sub_location_id = "";
        return;
    }
    try {
        const response = await axios.get(
            route("assets.locations.sub-locations", { location: locationId })
        );
        subLocations.value = response.data;
    } catch (error) {
        console.log(error);
        toast.error("Error loading sub-locations");
    }
};

watch(
    () => form.value.asset_location_id,
    (newValue) => {
        loadSubLocations(newValue);
    }
);

const statuses = ref([
    { value: "functioning", label: "Functioning" },
    { value: "not_functioning", label: "Not functioning" },
    { value: "pending_approval", label: "Pending Approval" },
    { value: "in_use", label: "In Use" },
    { value: "maintenance", label: "Maintenance" },
    { value: "retired", label: "Retired" },
    { value: "disposed", label: "Disposed" },
]);

const showLocationModal = ref(false);
const showSubLocationModal = ref(false);
const newLocation = ref("");
const newSubLocation = ref("");
const newCategory = ref("");
const newRegion = ref("");
const newFundSource = ref("");
const showCategoryModal = ref(false);
const showFundSourceModal = ref(false);
const showRegionModal = ref(false);
const isNewRegion = ref(false);
const isNewCategory = ref(false);
const isNewFundSource = ref(false);
const selectedLocationForSub = ref(null);

const handleLocationSelect = (selected) => {
    if (!selected) {
        form.value.asset_location_id = null;
        selectedLocationForSub.value = null;
        form.value.sub_location_id = null;
        form.value.sub_location = null;
        subLocations.value = [];
        return;
    }

    if (selected.isAddNew) {
        const currentSelection = form.value.asset_location;
        showLocationModal.value = true;
        form.value.asset_location = currentSelection;
        form.value.asset_location_id = currentSelection.id;
        return;
    }

    form.value.asset_location_id = selected.id;
    form.value.asset_location = selected;
    selectedLocationForSub.value = selected.id;
    loadSubLocations(selected.id);
    form.value.sub_location_id = null;
    form.value.sub_location = null;
};

const handleSubLocationSelect = (selected) => {
    if (!selected) {
        form.value.sub_location_id = null;
        form.value.sub_location = null;
        return;
    }

    if (selected.isAddNew) {
        const currentSelection = form.value.asset_location;
        showSubLocationModal.value = true;
        form.value.asset_location = currentSelection;
        form.value.asset_location_id = currentSelection.id;
        return;
    }

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

const isNewLocation = ref(false);
const createLocation = async () => {
    if (!newLocation.value) return;
    isNewLocation.value = true;
    try {
        const response = await axios.post(route("assets.locations.store"), {
            name: newLocation.value,
        });
        const newLocationData = response.data;

        locationOptions.value = [
            ...locationOptions.value.filter((loc) => !loc.isAddNew),
            newLocationData,
            { id: "new", name: "+ Add New Location", isAddNew: true },
        ];

        form.value.asset_location = newLocationData;
        form.value.asset_location_id = newLocationData.id;
        selectedLocationForSub.value = newLocationData.id;

        newLocation.value = "";
        showLocationModal.value = false;

        await loadSubLocations(newLocationData.id);

        toast.success("Location created successfully");
    } catch (error) {
        console.error(error);
    } finally {
        isNewLocation.value = false;
    }
};

const createRegion = async () => {
    if (!newRegion.value) {
        toast.error("Please enter a region name");
        return;
    }

    isNewRegion.value = true;

    try {
        const response = await axios.post(route("assets.regions.store"), {
            name: newRegion.value,
        });

        isNewRegion.value = false;

        const newRegionData = response.data;

        regionOptions.value = [
            ...regionOptions.value.filter((reg) => !reg.isAddNew),
            newRegionData,
            { id: "new", name: "+ Add New Region", isAddNew: true },
        ];

        form.value.region = newRegionData;
        form.value.region_id = newRegionData.id;

        newRegion.value = "";
        showRegionModal.value = false;

        toast.success("Region created successfully");
    } catch (error) {
        isNewRegion.value = false;
        console.error("Error creating region:", error);
        toast.error(error.response?.data || "Error creating region");
    } finally {
        isNewRegion.value = false;
    }
};

const handleCategorySelect = (selected) => {
    if (!selected) {
        form.value.asset_category_id = null;
        form.value.asset_category = null;
        return;
    }

    if (selected.isAddNew) {
        const currentSelection = form.value.asset_category;
        showCategoryModal.value = true;
        form.value.asset_category_id = currentSelection.id;
        form.value.asset_category = currentSelection;
        return;
    }

    form.value.asset_category_id = selected.id;
    form.value.asset_category = selected;
};

const handleRegionSelect = (selected) => {
    if (!selected) {
        form.value.region_id = null;
        form.value.region = null;
        return;
    }

    if (selected.isAddNew) {
        const currentSelection = form.value.region;
        showRegionModal.value = true;
        form.value.region_id = currentSelection.id;
        return;
    }

    form.value.region_id = selected.id;
    form.value.region = selected;
};

const handleFundSourceSelect = (selected) => {
    if (!selected) {
        form.value.fund_source_id = null;
        form.value.fund_source = null;
        return;
    }

    if (selected.isAddNew) {
        const currentSelection = form.value.fund_source;
        showFundSourceModal.value = true;
        form.value.fund_source_id = currentSelection.id;
        return;
    }

    form.value.fund_source_id = selected.id;
    form.value.fund_source = selected;
};

const createCategory = async () => {
    if (!newCategory.value) {
        toast.error("Please enter a category name");
        return;
    }

    isNewCategory.value = true;
    try {
        const response = await axios.post(route('assets.categories.store'), {
            name: newCategory.value,
        });

        const newCategoryData = response.data;

        categoryOptions.value = [
            ...categoryOptions.value.filter((cat) => !cat.isAddNew),
            newCategoryData,
            { id: "new", name: "+ Add New Category", isAddNew: true },
        ];

        form.value.asset_category = newCategoryData;
        form.value.asset_category_id = newCategoryData.id;

        newCategory.value = "";
        showCategoryModal.value = false;

        toast.success("Category created successfully");
    } catch (error) {
        console.error("Error creating category:", error);
        toast.error(error.response?.data || "Error creating category");
    } finally {
        isNewCategory.value = false;
    }
};

const createFundSource = async () => {
    if (!newFundSource.value) {
        toast.error("Please enter a fund source name");
        return;
    }

    isNewFundSource.value = true;
    try {
        const response = await axios.post(route("assets.fund-sources.store"), {
            name: newFundSource.value,
        });

        isNewFundSource.value = false;

        const newFundSourceData = response.data;

        fundSourceOptions.value = [
            ...fundSourceOptions.value.filter((fs) => !fs.isAddNew),
            newFundSourceData,
            { id: "new", name: "+ Add New Fund Source", isAddNew: true },
        ];

        form.value.fund_source = newFundSourceData;
        form.value.fund_source_id = newFundSourceData.id;

        newFundSource.value = "";
        showFundSourceModal.value = false;

        toast.success("Fund Source created successfully");
    } catch (error) {
        isNewFundSource.value = false;
        console.error("Error creating fund source:", error);
        toast.error(error.response?.data || "Error creating fund source");
    } finally {
        isNewFundSource.value = false;
    }
};

const createSubLocation = async () => {
    if (!newSubLocation.value || !selectedLocationForSub.value) {
        toast.error("Please enter a sub-location name and select a location");
        return;
    }
    isNewLocation.value = true;

    try {
        const response = await axios.post(
            route("assets.locations.sub-locations.store"),
            {
                name: newSubLocation.value,
                asset_location_id: selectedLocationForSub.value,
            }
        );
        isNewLocation.value = false;

        const newSubLocationData = response.data;

        subLocations.value = [...subLocations.value, newSubLocationData];

        form.value.sub_location = newSubLocationData;
        form.value.sub_location_id = newSubLocationData.id;

        newSubLocation.value = "";
        showSubLocationModal.value = false;

        toast.success("Sub-location created successfully");
    } catch (error) {
        isNewLocation.value = true;
        console.error("Error creating sub-location:", error);
        toast.error(error.response?.data || "Error creating sub-location");
    }
};

// Asset Item Management Methods
const addAssetItem = () => {
    form.value.asset_items.push({
        asset_tag: "",
        asset_name: "",
        serial_number: "",
        asset_category_id: null,
        asset_category: null,
        asset_type_id: null,
        asset_type: null,
        assignee_id: null,
        assignee: null,
        status: "pending_approval",
        original_value: "",
    });
};

const removeAssetItem = (index) => {
    if (form.value.asset_items.length > 1) {
        form.value.asset_items.splice(index, 1);
    } else {
        toast.warning("At least one asset item is required");
    }
};

const handleItemCategorySelect = (selected, index) => {
    if (!selected) {
        form.value.asset_items[index].asset_category_id = null;
        form.value.asset_items[index].asset_category = null;
        form.value.asset_items[index].asset_type_id = null;
        form.value.asset_items[index].asset_type = null;
        return;
    }

    if (selected.isAddNew) {
        const currentSelection = form.value.asset_items[index].asset_category;
        showCategoryModal.value = true;
        form.value.asset_items[index].asset_category_id = currentSelection.id;
        form.value.asset_items[index].asset_category = currentSelection;
        return;
    }

    form.value.asset_items[index].asset_category_id = selected.id;
    form.value.asset_items[index].asset_category = selected;
    form.value.asset_items[index].asset_type_id = null;
    form.value.asset_items[index].asset_type = null;
};

const handleItemTypeSelect = (selected, index) => {
    if (!selected) {
        form.value.asset_items[index].asset_type_id = null;
        form.value.asset_items[index].asset_type = null;
        return;
    }

    if (selected.isAddNew) {
        const currentSelection = form.value.asset_items[index].asset_type;
        showTypeModal.value = true;
        form.value.asset_items[index].asset_type_id = currentSelection.id;
        form.value.asset_items[index].asset_type = currentSelection;
        return;
    }

    form.value.asset_items[index].asset_type_id = selected.id;
    form.value.asset_items[index].asset_type = selected;
};

const handleItemAssigneeSelect = (selected, index) => {
    if (!selected) {
        form.value.asset_items[index].assignee_id = null;
        form.value.asset_items[index].assignee = null;
        return;
    }

    if (selected.isAddNew) {
        showAssigneeModal.value = true;
        return;
    }

    form.value.asset_items[index].assignee_id = selected.id;
    form.value.asset_items[index].assignee = selected;
};

const handleItemAssigneeClear = (index) => {
    form.value.asset_items[index].assignee_id = null;
    form.value.asset_items[index].assignee = null;
};

const submit = async () => {
    isSubmitting.value = true;
    processing.value = true;

    try {
        // Validate form
        if (!form.value.asset_number || !form.value.acquisition_date || !form.value.fund_source_id || !form.value.region_id || !form.value.asset_location_id) {
            toast.error("Please fill in all required asset fields");
            return;
        }

        if (form.value.asset_items.length === 0) {
            toast.error("At least one asset item is required");
            return;
        }

        // Validate asset items
        for (let i = 0; i < form.value.asset_items.length; i++) {
            const item = form.value.asset_items[i];
            if (!item.asset_tag || !item.asset_name || !item.asset_category_id || !item.asset_type_id || !item.original_value) {
                toast.error(`Please fill in all required fields for item #${i + 1}`);
                return;
            }
        }

        const requestData = {
            asset_number: form.value.asset_number,
            acquisition_date: form.value.acquisition_date,
            fund_source_id: form.value.fund_source_id,
            region_id: form.value.region_id,
            asset_location_id: form.value.asset_location_id,
            sub_location_id: form.value.sub_location_id || null,
            facility_id: form.value.facility_id || null,
            asset_items: form.value.asset_items
        };

        console.log('Submitting asset data:', requestData);
        
        const response = await axios.post(route("assets.store"), requestData, {
            headers: {
                "Content-Type": "application/json",
            },
        });
        
        console.log('Asset creation response:', response.data);

        processing.value = false;
        isSubmitting.value = false;

        if (response.data.success) {
            Swal.fire({
                title: "Success!",
                text: response.data.message,
                icon: "success",
                confirmButtonText: "OK",
            }).then(() => {
                router.get(route("assets.index"));
            });
        } else {
            toast.error(response.data.message || "Asset creation failed");
        }
    } catch (error) {
        processing.value = false;
        isSubmitting.value = false;
        console.error("Error creating asset:", error);

        if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        } else if (error.response?.data?.errors) {
            // Handle validation errors
            const errors = error.response.data.errors;
            const firstError = Object.values(errors)[0][0];
            toast.error(firstError);
        } else {
            toast.error("Error creating asset: " + (error.message || "Unknown error"));
        }
    }
};
</script>

<template>
    <AuthenticatedLayout title="Assets management" description="Create new asset with multiple items"
        img="/assets/images/asset-header.png">

        <Head title="Create Asset" />
        <div class="mb-[80px]">
            <div class="p-6 text-gray-900">
                <h2 class="text-2xl font-semibold mb-6">Create New Asset</h2>

                <form @submit.prevent="submit" novalidate class="space-y-6">
                    <!-- Asset Information Section -->
                    <div class="bg-white rounded-lg border border-gray-200 p-4 sm:p-5">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Asset Information</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3">
                            <div class="min-w-0">
                                <InputLabel for="asset_number" value="Asset Number" />
                                <input id="asset_number" type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    placeholder="e.g., ASSET-2025-001" v-model="form.asset_number" required />
                            </div>
                            <div class="min-w-0">
                                <InputLabel for="acquisition_date" value="Acquisition Date" />
                                <input id="acquisition_date" type="date"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    v-model="form.acquisition_date" required />
                            </div>
                            <div class="min-w-0 sm:col-span-2 lg:col-span-1">
                                <InputLabel for="fund_source" value="Fund Source" />
                                <Multiselect id="fund_source" v-model="form.fund_source" :options="fundSourceOptions"
                                    :close-on-select="true" :show-labels="false" :allow-empty="true"
                                    placeholder="Select Fund Source" track-by="id" label="name"
                                    class="multiselect-compact mt-1"
                                    @select="handleFundSourceSelect">
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Fund Source</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>

                            <div class="min-w-0">
                                <InputLabel for="region" value="Region" />
                                <Multiselect v-model="form.region" :options="regionOptions" :searchable="true"
                                    :close-on-select="true" :show-labels="false" :allow-empty="true"
                                    placeholder="Select Region" track-by="id" label="name"
                                    class="multiselect-compact mt-1"
                                    @select="handleRegionSelect">
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Region</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>
                            <div class="min-w-0">
                                <InputLabel for="location" value="Location" />
                                <Multiselect v-model="form.asset_location" :options="locationOptions"
                                    :searchable="true" :close-on-select="true" :show-labels="false"
                                    :allow-empty="true" placeholder="Select Location" track-by="id" label="name"
                                    class="multiselect-compact mt-1"
                                    @select="handleLocationSelect">
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Location</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>
                            <div class="min-w-0 sm:col-span-2 lg:col-span-1">
                                <InputLabel for="sub_location" value="Sub Location" />
                                <Multiselect v-model="form.sub_location" :options="[
                                    ...subLocations,
                                    { id: 'new', name: '+ Add New Sub-location', isAddNew: true },
                                ]" :searchable="true" :close-on-select="true" :show-labels="false"
                                    :allow-empty="true" placeholder="Select Sub-location" track-by="id" label="name"
                                    :disabled="!form.asset_location_id"
                                    class="multiselect-compact mt-1"
                                    @select="handleSubLocationSelect">
                                    <template v-slot:option="{ option }">
                                        <div :class="{ 'add-new-option': option.isAddNew }">
                                            <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Sub-location</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>

                            <div class="min-w-0 sm:col-span-2">
                                <InputLabel for="facility" value="Facility (optional)" />
                                <Multiselect id="facility" v-model="form.facility" :options="facilityOptions"
                                    :searchable="true" :close-on-select="true" :show-labels="false"
                                    :allow-empty="true" placeholder="Select Facility" track-by="id" label="name"
                                    class="multiselect-compact mt-1"
                                    @select="handleFacilitySelect"
                                    @clear="handleFacilityClear">
                                    <template v-slot:option="{ option }">
                                        <span>{{ option.name }}</span>
                                        <span v-if="option.district || option.region" class="text-gray-500 text-xs block">{{ [option.district, option.region].filter(Boolean).join(' · ') }}</span>
                                    </template>
                                </Multiselect>
                            </div>
                        </div>
                    </div>

                    <!-- Asset Items Section -->
                    <div class="bg-white rounded-xl border border-gray-200/80 shadow-sm">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
                            <h3 class="text-lg font-semibold text-gray-800">Asset Items</h3>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500">{{ form.asset_items.length }} item(s)</span>
                                <button type="button" @click="addAssetItem"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Item
                                </button>
                            </div>
                        </div>

                        <div v-if="form.asset_items.length === 0" class="text-center py-12 text-gray-500 bg-gray-50/50">
                            <p class="text-sm">No asset items added yet.</p>
                            <p class="text-sm mt-1">Click "Add Item" above to add the first line.</p>
                        </div>

                        <div v-else class="pb-3 pr-2 overflow-visible">
                            <table class="min-w-[880px] w-full border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-10">#</th>
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[95px]">Asset Tag</th>
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[110px]">Asset Name</th>
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[85px]">Serial</th>
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[130px]">Category</th>
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[120px]">Type</th>
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-[120px]">Assignee</th>
                                        <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[80px]">Value</th>
                                        <th class="px-2 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-14">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="(item, index) in form.asset_items" :key="index" class="hover:bg-gray-50/80 transition-colors">
                                        <td class="px-2 py-2 text-sm text-gray-500 font-medium align-top">{{ index + 1 }}</td>
                                        <td class="px-2 py-2 align-top">
                                            <input :id="`asset_tag_${index}`" type="text"
                                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                placeholder="e.g. INV-000123" v-model="item.asset_tag" required />
                                        </td>
                                        <td class="px-2 py-2 align-top">
                                            <input :id="`asset_name_${index}`" type="text"
                                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                placeholder="e.g. Dell Latitude 7420" v-model="item.asset_name" required />
                                        </td>
                                        <td class="px-2 py-2 align-top">
                                            <input :id="`serial_number_${index}`" type="text"
                                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                placeholder="Serial" v-model="item.serial_number" required />
                                        </td>
                                        <td class="px-2 py-2 align-top w-[130px]">
                                            <Multiselect v-model="item.asset_category" :options="categoryOptions"
                                                :searchable="true" :close-on-select="true" :show-labels="false"
                                                :allow-empty="true" placeholder="Category" track-by="id" label="name"
                                                class="text-sm multiselect-compact multiselect-asset-table multiselect-asset-category"
                                                @select="(selected) => handleItemCategorySelect(selected, index)">
                                                <template v-slot:option="{ option }">
                                                    <div :class="{ 'add-new-option': option.isAddNew }">
                                                        <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Category</span>
                                                        <span v-else>{{ option.name }}</span>
                                                    </div>
                                                </template>
                                            </Multiselect>
                                        </td>
                                        <td class="px-2 py-2 align-top w-[120px]">
                                            <Multiselect v-model="item.asset_type"
                                                :options="getFilteredTypeOptions(item.asset_category)"
                                                :searchable="true" :close-on-select="true" :show-labels="false"
                                                :allow-empty="true" placeholder="Type" track-by="id" label="name"
                                                :disabled="!item.asset_category" class="text-sm multiselect-compact multiselect-asset-table multiselect-asset-type"
                                                @select="(selected) => handleItemTypeSelect(selected, index)">
                                                <template v-slot:option="{ option }">
                                                    <div :class="{ 'add-new-option': option.isAddNew }">
                                                        <span v-if="option.isAddNew" class="text-indigo-600 font-medium">+ Add New Type</span>
                                                        <span v-else>{{ option.name }}</span>
                                                    </div>
                                                </template>
                                            </Multiselect>
                                        </td>
                                        <td class="px-2 py-2 align-top w-[120px]">
                                            <Multiselect v-model="item.assignee" :options="assigneeOptions"
                                                :searchable="true" :close-on-select="true" :show-labels="false"
                                                :allow-empty="true" placeholder="Assignee" track-by="id" label="name"
                                                class="text-sm multiselect-compact multiselect-asset-table multiselect-asset-assignee"
                                                @select="(selected) => handleItemAssigneeSelect(selected, index)"
                                                @clear="() => handleItemAssigneeClear(index)" />
                                        </td>
                                        <td class="px-2 py-2 align-top">
                                            <input :id="`original_value_${index}`" type="number" step="0.01" min="0"
                                                class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                                placeholder="0.00" v-model="item.original_value" required />
                                        </td>
                                        <td class="px-2 py-2 text-center align-top w-16">
                                            <button type="button" @click="removeAssetItem(index)"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-md text-red-600 hover:bg-red-50 hover:text-red-700 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                                                :disabled="form.asset_items.length === 1"
                                                title="Remove row">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link :href="route('assets.index')"
                            class="px-4 py-2 text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        Cancel
                        </Link>
                        <PrimaryButton type="submit" :disabled="processing || form.asset_items.length === 0">
                            {{ processing ? "Creating..." : "Create Asset" }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modals for creating new entities -->
        <!-- New Location Modal -->
        <Modal :show="showLocationModal" @close="showLocationModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Location</h2>
                <div class="mt-6">
                    <InputLabel for="new_location" value="Location Name" />
                    <input id="new_location" type="text" class="mt-1 block w-full" v-model="newLocation" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showLocationModal = false" :disabled="isNewLocation">Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="isNewLocation" @click="createLocation">{{ isNewLocation ? "Waiting..." :
                        "Create new location" }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Sub-Location Modal -->
        <Modal :show="showSubLocationModal" @close="showSubLocationModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Sub-Location</h2>
                <div class="mt-6">
                    <InputLabel for="new_sub_location" value="Sub-Location Name" />
                    <input id="new_sub_location" type="text" class="mt-1 block w-full" v-model="newSubLocation"
                        required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showSubLocationModal = false" :disabled="isNewLocation">Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="isNewLocation" @click="createSubLocation">{{ isNewLocation ? "Waiting..."
                        :
                        "Create Sub-Location" }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Category Modal -->
        <Modal :show="showCategoryModal" @close="showCategoryModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Category</h2>
                <div class="mt-6">
                    <InputLabel for="new_category" value="Category Name" />
                    <input id="new_category" type="text" class="mt-1 block w-full" v-model="newCategory" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showCategoryModal = false" :disabled="isNewCategory">Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="isNewCategory" @click="createCategory">{{ isNewCategory ? "Waiting..." : "Create Category" }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Region Modal -->
        <Modal :show="showRegionModal" @close="showRegionModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Region</h2>
                <div class="mt-6">
                    <InputLabel for="new_region" value="Region Name" />
                    <input id="new_region" type="text" class="mt-1 block w-full" v-model="newRegion" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showRegionModal = false" :disabled="isNewRegion">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewRegion" @click="createRegion">{{ isNewRegion ? "Waiting..." : "Create Region" }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Fund Source Modal -->
        <Modal :show="showFundSourceModal" @close="showFundSourceModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Fund Source</h2>
                <div class="mt-6">
                    <InputLabel for="new_fund_source" value="Fund Source Name" />
                    <input id="new_fund_source" type="text" class="mt-1 block w-full" v-model="newFundSource"
                        required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showFundSourceModal = false" :disabled="isNewFundSource">Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="isNewFundSource" @click="createFundSource">{{ isNewFundSource ?
                        "Waiting..." :
                        "Create Fund Source" }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Type Modal -->
        <Modal :show="showTypeModal" @close="showTypeModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Type</h2>
                <div class="mt-6">
                    <InputLabel for="new_type" value="Type Name" />
                    <input id="new_type" type="text" class="mt-1 block w-full" v-model="newType" required />
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showTypeModal = false" :disabled="isNewType">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="isNewType" @click="createType">{{ isNewType ? 'Waiting...' : 'Create Type'
                        }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- New Assignee Modal -->
        <Modal :show="showAssigneeModal" @close="showAssigneeModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Assignee</h2>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="new_assignee_name" value="Full Name" />
                        <input id="new_assignee_name" name="new_assignee_name" type="text" class="mt-1 block w-full"
                            placeholder="e.g., John Doe" :required="showAssigneeModal" v-model="newAssignee.name" />
                    </div>
                    <div>
                        <InputLabel for="new_assignee_email" value="Email (optional)" />
                        <input id="new_assignee_email" type="email" class="mt-1 block w-full"
                            placeholder="name@example.com" v-model="newAssignee.email" />
                    </div>
                    <div>
                        <InputLabel for="new_assignee_phone" value="Phone" />
                        <input id="new_assignee_phone" name="new_assignee_phone" type="text" class="mt-1 block w-full"
                            placeholder="e.g., +1 555 123 4567" :required="showAssigneeModal"
                            v-model="newAssignee.phone" />
                    </div>
                    <div>
                        <InputLabel for="new_assignee_department" value="Department" />
                        <input id="new_assignee_department" name="new_assignee_department" type="text"
                            class="mt-1 block w-full" placeholder="e.g., IT" :required="showAssigneeModal"
                            v-model="newAssignee.department" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showAssigneeModal = false" :disabled="isSavingAssignee">Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="createAssignee" :disabled="isSavingAssignee">
                        {{ isSavingAssignee ? 'Saving...' : 'Save' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.multiselect-compact.multiselect) {
    min-height: 32px;
}
:deep(.multiselect-compact .multiselect__tags) {
    min-height: 32px;
    padding: 4px 8px;
}
:deep(.multiselect-compact .multiselect__single) {
    font-size: 0.875rem;
}
</style>
