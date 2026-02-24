<template>
    <AuthenticatedLayout title="Settings" img="/assets/images/Setting.png" description="User & Access Management">
        <div class="p-6">
            <Link :href="route('settings.index')" class="flex items-center text-sm font-medium text-blue-600 hover:text-blue-500 transition duration-150 ease-in-out">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Back to settings
            </Link>

            <!-- Tabs -->
            <div class="flex space-x-4 border-b border-gray-300 mb-6 mt-5">
                <Link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :href="route(tab.name)"
                    class="py-2 px-4 text-sm font-medium border-b-2 -mb-[2px] transition-colors"
                    :class="{
                        'border-blue-500 text-blue-600': $page.component.startsWith(tab.component),
                        'border-transparent text-gray-600 hover:text-blue-500 hover:border-blue-300': !$page.component.startsWith(tab.component)
                    }"
                >
                    {{ tab.label }}
                </Link>
            </div>

            <!-- Page content -->
            <slot />
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref } from "vue";

const tabs = [
    { name: 'settings.users.index', label: 'Manage Users', component: 'User' },
    { name: 'settings.roles.index', label: 'Roles', component: 'Settings/Role' },
];

const currentTab = ref('users');
</script>
