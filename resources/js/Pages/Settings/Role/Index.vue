<template>
    <UserAuthTab>
        <Head title="Roles" />

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Roles</h1>
            <p class="mt-1 text-sm text-slate-500">Create and manage roles for user assignment.</p>
        </div>

        <!-- Create role form -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden mb-6">
            <div class="p-4 sm:p-6 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-800 mb-4">Create new role</h2>
                <form @submit.prevent="createRole" class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[200px] flex-1">
                        <label for="role-name" class="block text-xs font-medium text-slate-500 mb-1">Role name</label>
                        <input
                            id="role-name"
                            v-model="newRoleName"
                            type="text"
                            required
                            placeholder="e.g. warehouse_manager"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="creating"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg v-if="creating" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 12 12 12s12-5.373 12-12h-4a8 8 0 01-8 8z" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        {{ creating ? 'Creating…' : 'Create role' }}
                    </button>
                </form>
                <p v-if="createError" class="mt-2 text-sm text-red-600">{{ createError }}</p>
            </div>
        </div>

        <!-- Roles table -->
        <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Guard</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="role in roles" :key="role.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-900">{{ role.name }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ role.guard_name || 'web' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    @click="confirmDelete(role)"
                                    class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Delete role"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!roles.length" class="px-4 py-12 text-center text-sm text-slate-500">
                No roles yet. Create one above.
            </div>
        </div>
    </UserAuthTab>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import UserAuthTab from '@/Layouts/UserAuthTab.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useToast } from 'vue-toastification';

const toast = useToast();
const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
});

const newRoleName = ref('');
const creating = ref(false);
const createError = ref('');

async function createRole() {
    const name = (newRoleName.value || '').trim();
    if (!name) return;
    creating.value = true;
    createError.value = '';
    try {
        const { data } = await axios.post(route('settings.roles.store'), {
            name,
            guard_name: 'web',
        });
        toast.success(data.message || 'Role created');
        newRoleName.value = '';
        router.reload({ only: ['roles'] });
    } catch (err) {
        const msg = err.response?.data?.message ?? err.response?.data ?? 'Failed to create role';
        createError.value = typeof msg === 'string' ? msg : 'Failed to create role';
        toast.error(createError.value);
    } finally {
        creating.value = false;
    }
}

function confirmDelete(role) {
    Swal.fire({
        title: 'Delete role?',
        text: `Remove "${role.name}"? Users with this role will keep it until you edit them.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete',
    }).then((result) => {
        if (result.isConfirmed) deleteRole(role);
    });
}

async function deleteRole(role) {
    try {
        await axios.delete(route('settings.roles.destroy', role.id));
        toast.success('Role deleted');
        router.reload({ only: ['roles'] });
    } catch (err) {
        const msg = err.response?.data?.message ?? err.response?.data ?? 'Failed to delete role';
        toast.error(typeof msg === 'string' ? msg : 'Failed to delete role');
    }
}
</script>
