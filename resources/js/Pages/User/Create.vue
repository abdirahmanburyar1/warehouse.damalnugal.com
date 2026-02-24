<template>
    <UserAuthTab>
        <Head title="Create User" />

        <!-- Header -->
        <div class="mb-8">
            <Link
                :href="route('settings.users.index')"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 mb-4 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to users
            </Link>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Create user</h1>
            <p class="mt-1 text-sm text-slate-500">Add a new user and set access and permissions.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-4 sm:p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <InputLabel for="name" value="Full name" />
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Ahmed Hassan"
                            class="mt-1.5 block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 transition-colors"
                        />
                    </div>
                    <div>
                        <InputLabel for="organization" value="Organization" />
                        <select
                            id="organization"
                            v-model="form.organization"
                            required
                            class="mt-1.5 block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                        >
                            <option value="">Select organization</option>
                            <option value="PSI">PSI</option>
                            <option value="MoH">MoH</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="username" value="Username" />
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            required
                            placeholder="Unique login name"
                            class="mt-1.5 block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 transition-colors"
                        />
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="user@example.com"
                            class="mt-1.5 block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 transition-colors"
                        />
                    </div>
                    <div>
                        <InputLabel for="password" value="Password" />
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            placeholder="••••••••"
                            class="mt-1.5 block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 transition-colors"
                        />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirm password" />
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            placeholder="••••••••"
                            class="mt-1.5 block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 transition-colors"
                        />
                    </div>
                    <div>
                        <InputLabel value="Warehouse" />
                        <SearchableSelect
                            v-model="form.warehouse"
                            :options="warehouses"
                            placeholder="Select warehouse"
                            class="mt-1.5"
                            @select="handleSelectWarehouse"
                        />
                    </div>
                    <div>
                        <InputLabel value="Facility" />
                        <SearchableSelect
                            v-model="form.facility"
                            :options="facilities"
                            placeholder="Select facility"
                            class="mt-1.5"
                            @select="handleSelectFacility"
                        />
                    </div>
                    <div>
                        <InputLabel for="roles" value="Roles" />
                        <Multiselect
                            id="roles"
                            v-model="form.roles"
                            :options="roles"
                            :multiple="true"
                            :searchable="true"
                            :close-on-select="false"
                            track-by="id"
                            label="name"
                            placeholder="Select roles"
                            class="mt-1.5"
                        />
                    </div>
                </div>

                <!-- Permissions (when no facility) -->
                <div v-if="!hasFacility" class="px-4 sm:px-6 pb-4 sm:pb-6 border-t border-slate-100 pt-4 sm:pt-6">
                    <InputLabel value="Permissions" />
                    <button
                        type="button"
                        @click="showPermissionsModal = true"
                        class="mt-1.5 inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 transition-colors"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        {{ form.permissions.length ? `${form.permissions.length} permission${form.permissions.length === 1 ? '' : 's'} selected` : 'Select permissions' }}
                    </button>
                </div>

                <!-- Permissions modal -->
                <Modal :show="showPermissionsModal" max-width="full" @close="showPermissionsModal = false">
                    <div class="p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-slate-900">Select permissions</h3>
                            <button
                                type="button"
                                @click="showPermissionsModal = false"
                                class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                                aria-label="Close"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center justify-between gap-2 mb-4">
                            <p class="text-sm text-slate-500">
                                {{ form.permissions.length }} of {{ permissions.length }} selected
                            </p>
                            <button
                                type="button"
                                @click="toggleAllPermissions"
                                class="text-sm font-medium text-slate-600 hover:text-slate-900 underline"
                            >
                                {{ allPermissionsSelected ? 'Deselect all' : 'Select all' }}
                            </button>
                        </div>
                        <div class="mb-4">
                            <input
                                v-model="permissionSearch"
                                type="search"
                                placeholder="Search permissions by name or description..."
                                class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 placeholder-slate-400"
                                autocomplete="off"
                            />
                        </div>
                        <div v-if="!permissions.length" class="text-sm text-slate-500 text-center py-8">
                            No permissions available
                        </div>
                        <div v-else-if="!filteredPermissions.length" class="text-sm text-slate-500 text-center py-8">
                            No permissions match "{{ permissionSearch }}"
                        </div>
                        <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-2 max-h-[calc(100vh-14rem)] overflow-y-auto pr-1 border border-slate-200 rounded-lg p-2">
                            <label
                                v-for="permission in filteredPermissions"
                                :key="permission.id"
                                class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors"
                            >
                                <input
                                    :id="`modal-permission-${permission.id}`"
                                    v-model="form.permissions"
                                    type="checkbox"
                                    :value="permission.id"
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-600 focus:ring-slate-400/50"
                                />
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-slate-800">
                                        {{ permission.display_name || permission.name }}
                                    </span>
                                    <p v-if="permission.description" class="text-xs text-slate-500 mt-0.5">
                                        {{ permission.description }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button
                                type="button"
                                @click="showPermissionsModal = false"
                                class="px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 focus:ring-2 focus:ring-offset-2 focus:ring-slate-500"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                </Modal>

                <!-- Facility notice -->
                <div v-if="hasFacility" class="mx-4 sm:mx-6 mb-4 sm:mb-6 pt-4 sm:pt-6 border-t border-slate-100 rounded-lg bg-slate-50/80 p-4 flex items-start gap-3">
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-slate-200 flex items-center justify-center text-slate-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-sm text-slate-700">
                        <span class="font-medium">Facility user</span> — Permissions are managed automatically for facility users.
                    </div>
                </div>

                <!-- Status & actions -->
                <div class="px-4 sm:px-6 py-4 sm:py-6 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-slate-600 focus:ring-slate-400/50"
                        />
                        <span class="text-sm font-medium text-slate-700">Active</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('settings.users.index')"
                            :disabled="processing"
                            class="px-4 py-2 text-sm font-medium text-slate-700 hover:text-slate-900 disabled:opacity-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="processing"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="processing" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                            {{ processing ? 'Creating…' : 'Create user' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </UserAuthTab>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import UserAuthTab from '@/Layouts/UserAuthTab.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import { useToast } from 'vue-toastification';
import axios from 'axios';
import Swal from 'sweetalert2';

const toast = useToast();
const processing = ref(false);
const showPermissionsModal = ref(false);
const permissionSearch = ref('');

const props = defineProps({
    warehouses: Array,
    facilities: Array,
    roles: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Array,
        default: () => [],
    },
});

const form = ref({
    name: '',
    organization: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    warehouse_id: null,
    warehouse: null,
    facility_id: null,
    facility: null,
    roles: [],
    permissions: [],
    is_active: true,
});

const allPermissionsSelected = computed(() => {
    if (!props.permissions?.length) return false;
    return form.value.permissions.length === props.permissions.length;
});

const filteredPermissions = computed(() => {
    const list = props.permissions ?? [];
    const q = (permissionSearch.value || '').trim().toLowerCase();
    if (!q) return list;
    return list.filter((p) => {
        const name = (p.name || '').toLowerCase();
        const display = (p.display_name || '').toLowerCase();
        const desc = (p.description || '').toLowerCase();
        return name.includes(q) || display.includes(q) || desc.includes(q);
    });
});

const hasFacility = computed(() => form.value.facility_id !== null && form.value.facility_id !== '');

watch(() => form.value.facility, (newVal) => {
    if (!newVal) form.value.facility_id = null;
});

watch(() => form.value.facility_id, (newVal) => {
    if (newVal != null) form.value.permissions = [];
});

watch(() => form.value.warehouse, (newVal) => {
    form.value.warehouse_id = newVal?.id ?? null;
});

function toggleAllPermissions() {
    if (allPermissionsSelected.value) {
        form.value.permissions = [];
    } else {
        form.value.permissions = props.permissions.map((p) => p.id);
    }
}

function handleSelectWarehouse(selected) {
    form.value.warehouse_id = selected?.id ?? null;
}

function handleSelectFacility(selected) {
    if (selected?.id) {
        form.value.facility_id = selected.id;
        form.value.facility = selected;
        form.value.permissions = [];
    } else {
        form.value.facility_id = null;
        form.value.facility = null;
    }
}

async function submit() {
    processing.value = true;
    const formData = {
        name: form.value.name,
        title: form.value.title,
        organization: form.value.organization,
        username: form.value.username,
        email: form.value.email,
        password: form.value.password,
        password_confirmation: form.value.password_confirmation,
        warehouse_id: form.value.warehouse_id,
        facility_id: form.value.facility_id,
        roles: Array.isArray(form.value.roles) ? form.value.roles.map((r) => (r && typeof r === 'object' && 'id' in r ? r.id : r)) : [],
        permissions: hasFacility.value ? [] : form.value.permissions,
        is_active: form.value.is_active,
    };

    try {
        const response = await axios.post(route('settings.users.store'), formData);
        Swal.fire({
            title: 'User created',
            text: response.data?.message || 'User created successfully.',
            icon: 'success',
            confirmButtonColor: '#0f172a',
        }).then(() => {
            toast.success('User created successfully');
            router.visit(route('settings.users.index'));
        });
    } catch (error) {
        const msg = error.response?.data?.message ?? error.response?.data ?? 'Something went wrong.';
        toast.error(msg);
    } finally {
        processing.value = false;
    }
}
</script>
