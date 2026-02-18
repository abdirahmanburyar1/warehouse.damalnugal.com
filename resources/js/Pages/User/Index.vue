<template>
    <UserAuthTab>
        <Head title="User Management" />

        <!-- Compact header -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">User Management</h1>
                    <p class="mt-1 text-sm text-slate-500">Manage users, access, and permissions</p>
                </div>
                <Link
                    :href="route('settings.users.create')"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add user
                </Link>
            </div>
        </div>

        <!-- Stats strip -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ users.total || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Active</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ users.data?.filter(u => u.is_active).length || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Inactive</p>
                <p class="mt-1 text-2xl font-semibold text-slate-400">{{ users.data?.filter(u => !u.is_active).length || 0 }}</p>
            </div>
        </div>

        <!-- Filters + active chips -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm mb-6 overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px] max-w-md">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Name, email, username..."
                                class="block w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 transition-colors"
                            />
                        </div>
                    </div>
                    <div class="w-36">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Organization</label>
                        <select
                            v-model="organization"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                        >
                            <option value="">All</option>
                            <option value="PSI">PSI</option>
                            <option value="MoH">MoH</option>
                        </select>
                    </div>
                    <div class="w-40">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Warehouse</label>
                        <Multiselect
                            v-model="warehouse"
                            :options="props.warehouses"
                            :searchable="true"
                            :allow-empty="true"
                            :show-labels="false"
                            :multiple="false"
                            placeholder="All"
                            class="text-sm"
                        />
                    </div>
                    <div class="w-40">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Facility</label>
                        <Multiselect
                            v-model="facility"
                            :options="props.facilities"
                            :searchable="true"
                            :show-labels="false"
                            :allow-empty="true"
                            :multiple="false"
                            placeholder="All"
                            class="text-sm"
                        />
                    </div>
                    <div class="w-32">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                        <select
                            v-model="status"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                        >
                            <option value="All">All</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="w-28">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Per page</label>
                        <select
                            v-model="per_page"
                            @change="props.filters.page = 1"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                        >
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
            <!-- Active filter chips -->
            <div v-if="hasActiveFilters" class="px-4 py-2 bg-slate-50/80 border-t border-slate-100 flex flex-wrap gap-2 items-center">
                <span class="text-xs text-slate-500 mr-1">Filters:</span>
                <button
                    v-if="search"
                    @click="search = ''"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-100"
                >
                    Search: {{ search }} <span class="text-slate-400">×</span>
                </button>
                <button
                    v-if="organization"
                    @click="organization = ''"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-100"
                >
                    Org: {{ organization }} <span class="text-slate-400">×</span>
                </button>
                <button
                    v-if="warehouse"
                    @click="warehouse = null"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-100"
                >
                    WH: {{ warehouse }} <span class="text-slate-400">×</span>
                </button>
                <button
                    v-if="facility"
                    @click="facility = null"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-100"
                >
                    Facility: {{ facility }} <span class="text-slate-400">×</span>
                </button>
                <button
                    v-if="status !== 'All'"
                    @click="status = 'All'"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-100"
                >
                    Status: {{ status === '1' ? 'Active' : 'Inactive' }} <span class="text-slate-400">×</span>
                </button>
                <button
                    @click="clearAllFilters"
                    class="text-xs text-slate-500 hover:text-slate-700 underline"
                >
                    Clear all
                </button>
            </div>
        </div>

        <!-- View toggle + Table -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50/50">
                <p class="text-sm text-slate-600">
                    <span v-if="users.data?.length">Showing {{ ((users.current_page - 1) * users.per_page) + 1 }}–{{ Math.min(users.current_page * users.per_page, users.total) }} of {{ users.total }}</span>
                    <span v-else>No users</span>
                </p>
                <div class="flex items-center gap-2">
                    <button
                        :class="viewMode === 'table' ? 'bg-slate-200 text-slate-800' : 'text-slate-400 hover:text-slate-600'"
                        class="p-2 rounded-lg transition-colors"
                        title="Table view"
                        @click="viewMode = 'table'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </button>
                    <button
                        :class="viewMode === 'cards' ? 'bg-slate-200 text-slate-800' : 'text-slate-400 hover:text-slate-600'"
                        class="p-2 rounded-lg transition-colors"
                        title="Card view"
                        @click="viewMode = 'cards'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Table view -->
            <div v-show="viewMode === 'table'" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Organization</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-slate-200 flex items-center justify-center text-sm font-semibold text-slate-600 shrink-0">
                                        {{ user.name?.charAt(0).toUpperCase() || '?' }}
                                    </div>
                                    <div>
                                        <div class="font-medium text-slate-900">{{ user.name }}</div>
                                        <div class="text-xs text-slate-500">{{ user.title || '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-slate-900">{{ user.username }}</div>
                                <div class="text-xs text-slate-500">{{ user.email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-blue-100 text-blue-800': user.organization === 'PSI',
                                        'bg-emerald-100 text-emerald-800': user.organization === 'MoH',
                                        'bg-slate-100 text-slate-600': !user.organization
                                    }"
                                >
                                    {{ user.organization || '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <div>{{ user.warehouse?.name || '—' }}</div>
                                <div class="text-xs text-slate-500">{{ user.facility?.name || '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full"
                                    :class="user.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :class="user.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="route('settings.users.edit', user.id)"
                                        class="p-2 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors"
                                        title="Edit"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="confirmToggleStatus(user)"
                                        :title="user.is_active ? 'Deactivate' : 'Activate'"
                                        class="p-2 rounded-lg transition-colors"
                                        :class="user.is_active ? 'text-emerald-600 hover:bg-emerald-50' : 'text-slate-400 hover:bg-slate-100'"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Card view -->
            <div v-show="viewMode === 'cards'" class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="user in users.data"
                        :key="user.id"
                        class="border border-slate-200 rounded-xl p-4 hover:border-slate-300 hover:shadow-sm transition-all"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center text-sm font-semibold text-slate-600 shrink-0">
                                    {{ user.name?.charAt(0).toUpperCase() || '?' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium text-slate-900 truncate">{{ user.name }}</div>
                                    <div class="text-xs text-slate-500 truncate">{{ user.email }}</div>
                                    <span
                                        class="inline-flex mt-1.5 px-2 py-0.5 text-xs font-medium rounded-full"
                                        :class="user.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <Link
                                    :href="route('settings.users.edit', user.id)"
                                    class="p-2 text-slate-500 hover:text-slate-900 hover:bg-slate-100 rounded-lg"
                                    title="Edit"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button
                                    @click="confirmToggleStatus(user)"
                                    class="p-2 rounded-lg text-slate-500 hover:bg-slate-100"
                                    :title="user.is_active ? 'Deactivate' : 'Activate'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-slate-100 text-xs text-slate-500 space-y-0.5">
                            <div>{{ user.warehouse?.name || '—' }} / {{ user.facility?.name || '—' }}</div>
                            <div>{{ user.organization || '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!users.data?.length" class="text-center py-16 px-4">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-base font-medium text-slate-900">No users found</h3>
                <p class="mt-1 text-sm text-slate-500 max-w-sm mx-auto">Add your first user or adjust filters.</p>
                <Link
                    :href="route('settings.users.create')"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add user
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="users.data?.length && users.last_page > 1" class="px-4 py-3 border-t border-slate-100 bg-slate-50/50">
                <div class="flex items-center justify-end">
                    <TailwindPagination
                        :data="users"
                        @pagination-change-page="getUsers"
                        :limit="2"
                    />
                </div>
            </div>
        </div>
    </UserAuthTab>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import UserAuthTab from '@/Layouts/UserAuthTab.vue';
import { Link, Head } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';
import { TailwindPagination } from 'laravel-vue-pagination';

const props = defineProps({
    users: Object,
    roles: Array,
    warehouses: Array,
    facilities: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const processing = ref(false);
const per_page = ref(props.filters?.per_page || '10');
const organization = ref(props.filters?.organization || '');
const warehouse = ref(props.filters?.warehouse ?? null);
const facility = ref(props.filters?.facility ?? null);
const role = ref(props.filters?.role || null);
const status = ref(props.filters?.status ?? 'All');
const viewMode = ref('table');

const hasActiveFilters = computed(() => {
    return search.value || organization.value || warehouse.value || facility.value || (status.value && status.value !== 'All');
});

function clearAllFilters() {
    search.value = '';
    organization.value = '';
    warehouse.value = null;
    facility.value = null;
    status.value = 'All';
}

watch(
    () => [role.value, search.value, organization.value, warehouse.value, facility.value, status.value, props.filters?.page, per_page.value],
    () => applyFilters()
);

function applyFilters() {
    const params = {};
    if (search.value) params.search = search.value;
    if (role.value) params.role = role.value;
    if (organization.value) params.organization = organization.value;
    if (warehouse.value) params.warehouse = warehouse.value;
    if (facility.value) params.facility = facility.value;
    if (status.value && status.value !== 'All') params.status = status.value;
    if (per_page.value) params.per_page = per_page.value;
    if (props.filters?.page) params.page = props.filters.page;

    router.get(route('settings.users.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'roles', 'warehouses', 'facilities'],
    });
}

function getUsers(page) {
    props.filters.page = page;
}

function confirmToggleStatus(user) {
    const newStatus = !user.is_active;
    const action = newStatus ? 'activate' : 'deactivate';
    Swal.fire({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} user?`,
        text: `Do you want to ${action} ${user.name}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: newStatus ? '#10B981' : '#64748b',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: `Yes, ${action}`,
    }).then((result) => {
        if (result.isConfirmed) toggleUserStatus(user, newStatus);
    });
}

async function toggleUserStatus(user, newStatus) {
    try {
        processing.value = true;
        const response = await axios.post(route('settings.users.toggle-status'), {
            user_id: user.id,
            is_active: newStatus,
        });
        if (response.data.success) {
            user.is_active = newStatus;
            Swal.fire({ title: 'Done', text: response.data.message, icon: 'success', confirmButtonColor: '#10B981' });
        } else {
            throw new Error(response.data.message || 'Failed to update status');
        }
    } catch (error) {
        Swal.fire({
            title: 'Error',
            text: error.response?.data?.message || error.message || 'Could not update status',
            icon: 'error',
            confirmButtonColor: '#EF4444',
        });
    } finally {
        processing.value = false;
    }
}
</script>
