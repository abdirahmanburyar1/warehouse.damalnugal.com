<template>
    <Head title="Edit Warehouse" />
    <AuthenticatedLayout title="Warehouse Management" description="Create new warehouse" img="/assets/images/facility.png">
        <Link :href="route('inventories.warehouses.index')" >
            <i class="fas fa-arrow-left mr-2"></i> Back to inventory
        </Link>

        <div class="overflow-hidden sm:rounded-lg p-6 mb-[60px]">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label
                            for="name"
                            class="block text-sm font-medium text-gray-700"
                            >Name <span class="text-red-500">*</span></label
                        >
                        <input
                            type="text"
                            id="name"
                            placeholder="Enter warehouse name"
                            v-model="form.name"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                        />
                    </div>
                    <div>
                        <label
                            for="address"
                            class="block text-sm font-medium text-gray-700"
                            >Address</label
                        >
                        <input
                            type="text"
                            id="address"
                            placeholder="Enter warehouse address"
                            v-model="form.address"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="manager_name">Manager Name</label>
                        <input
                            type="text"
                            id="manager_name"
                            placeholder="Enter manager name"
                            v-model="form.manager_name"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                        />
                    </div>
                    <div>
                        <label for="manager_email">Manager Email</label>
                        <input
                            type="email"
                            id="manager_email"
                            placeholder="Enter manager email"
                            v-model="form.manager_email"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                        />
                    </div>
                    <div>
                        <label for="manager_phone">Manager Phone</label>
                        <input
                            type="tel"
                            id="manager_phone"
                            v-model="form.manager_phone"
                            placeholder="Enter manager phone"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                        />
                    </div>
                </div>

                <!-- Location Information -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- region -->
                    <div>
                        <label for="region">Region</label>
                        <Multiselect
                            v-model="form.region"
                            :options="[...props.regions, ADD_NEW_REGION_OPTION]"
                            :searchable="true"
                            :close-on-select="true"
                            :show-labels="false"
                            :allow-empty="true"
                            placeholder="Select Region"
                            @select="handleRegionSelect"
                        >
                            <template v-slot:option="{ option }">
                                <div
                                    :class="{
                                        'add-new-option': option.isAddNew,
                                    }"
                                >
                                    <span
                                        v-if="option.isAddNew"
                                        class="text-indigo-600 font-medium"
                                    >
                                        + Add New Region
                                    </span>
                                    <span v-else>{{ option }}</span>
                                </div>
                            </template>
                        </Multiselect>
                    </div>
                    <div>
                        <label for="district">District</label>
                        <Multiselect
                            v-model="form.district"
                            :options="[...districts, ADD_NEW_DISTRICT_OPTION]"
                            :searchable="true"
                            :disabled="!form.region"
                            :close-on-select="true"
                            :show-labels="false"
                            :allow-empty="true"
                            placeholder="Select District"
                            @select="handleDistrictSelect"
                        >
                            <template v-slot:option="{ option }">
                                <div
                                    :class="{
                                        'add-new-option': option.isAddNew,
                                    }"
                                >
                                    <span
                                        v-if="option.isAddNew"
                                        class="text-indigo-600 font-medium"
                                    >
                                        + Add New District
                                    </span>
                                    <span v-else>{{ option }}</span>
                                </div>
                            </template>
                        </Multiselect>
                    </div>
                    <div>
                        <label for="status">Status</label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end mt-3 gap-2">
                    <!-- exit btn -->
                    <Link :disabled="isSumitting" :href="route('inventories.warehouses.index')" class="mt-6 bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700">
                        Exit
                    </Link>
                    <button type="submit" :disabled="isSumitting" class="mt-6 bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">
                        {{ isSumitting ? 'Updating...' : 'Update Warehouse' }}
                    </button>
                </div>

            </form>
            <!-- New Region Modal -->
            <Modal :show="showRegionModal" @close="showRegionModal = false">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Add New Region
                    </h2>

                    <div class="mt-6">
                        <label for="new_region" class="block text-sm font-medium text-gray-700">Region Name</label>
                        <input
                            id="new_region"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Enter region name"
                            v-model="newRegion"
                            required
                        />
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton
                            @click="showRegionModal = false"
                            :disabled="isNewRegion"
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            :disabled="isNewRegion"
                            @click="createRegion"
                        >
                            {{
                                isNewRegion ? "Waiting..." : "Create Region"
                            }}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

            <!-- New District Modal -->
            <Modal
                :show="showDistrictModal"
                @close="showDistrictModal = false"
            >
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Add New District
                    </h2>

                    <div class="mt-6">
                        <label for="name" class="block text-sm font-medium text-gray-700">District Name</label>
                        <input
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Enter district name"
                            v-model="newDistrict"
                            required
                        />
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton
                            @click="showDistrictModal = false"
                            :disabled="isNewDistrict"
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            :disabled="isNewDistrict"
                            @click="createDistrict"
                        >
                            {{
                                isNewDistrict
                                    ? "Waiting..."
                                    : "Create District"
                            }}
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import axios from "axios";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import Modal from "@/Components/Modal.vue";
import { useToast } from "vue-toastification";

const toast = useToast();

const props = defineProps({
    warehouse: Object,
    regions: Array,
});

const districts = ref([]);

const showRegionModal = ref(false);
const newRegion = ref("");
const isNewRegion = ref(false);
const showDistrictModal = ref(false);
const newDistrict = ref("");
const isNewDistrict = ref(false);

const ADD_NEW_REGION_OPTION = "+ Add New Region";
const ADD_NEW_DISTRICT_OPTION = "+ Add New District";

// Form data
const form = ref({
    id: props.warehouse.id,
    name: props.warehouse.name,
    address: props.warehouse.address,
    manager_name: props.warehouse.manager_name,
    manager_phone: props.warehouse.manager_phone,
    manager_email: props.warehouse.manager_email,
    status: props.warehouse.status,
    district: props.warehouse.district,
    region: props.warehouse.region,
});

async function handleRegionSelect(option) {
    if (option == ADD_NEW_REGION_OPTION) {
        form.value.region = null;
        showRegionModal.value = true;
    } else {
        form.value.region = option;
        await loadDistrict();
    }
}

async function handleDistrictSelect(option) {
    if (option == ADD_NEW_DISTRICT_OPTION) {
        form.value.district = null;
        showDistrictModal.value = true;
    } else {
        form.value.district = option;
    }
}

const createRegion = async () => {
    if (!newRegion.value) {
        toast.error("Please enter a region name");
        return;
    }
    isNewRegion.value = true;
    await axios
        .post(route("assets.regions.store"), { name: newRegion.value })
        .then((response) => {
            isNewRegion.value = false;
            form.value.region = response.data;
            props.regions.push(response.data);
            newRegion.value = "";
            showRegionModal.value = false;
        })
        .catch((error) => {
            isNewRegion.value = false;
            console.log(error);
        });
};

const createDistrict = async () => {
    if (!newDistrict.value || !form.value.region) {
        toast.error("Please select region");
        return;
    }
    isNewDistrict.value = true;
    await axios
        .post(route("districts.store"), {
            name: newDistrict.value,
            region: form.value.region,
        })
        .then((response) => {
            isNewDistrict.value = false;
            form.value.district = response.data;
            districts.value.push(response.data);
            newDistrict.value = "";
            showDistrictModal.value = false;
        })
        .catch((error) => {
            isNewDistrict.value = false;
            console.log(error);
        });
};

async function loadDistrict() {
    await axios
        .post(route("districts.get-districts"), { region: form.value.region })
        .then((response) => {
            districts.value = response.data;
        })
        .catch((error) => {
            console.log(error);
        });
}

const isSumitting = ref(false);

async function submit() {
    isSumitting.value = true;
    await axios.post(route('inventories.warehouses.store'), form.value)
        .then((response) => {
            isSumitting.value = false;
            toast.success(response.data);
            router.visit(route('inventories.warehouses.index'));
        })
        .catch((error) => {
            isSumitting.value = false
            console.log(error);
            toast.error(error.response?.data || "Error creating warehouse");
        });
}
</script>
