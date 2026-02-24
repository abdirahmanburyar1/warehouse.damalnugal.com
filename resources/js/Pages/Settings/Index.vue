<template>
    <AuthenticatedLayout :title="'Settings'" description="Customize it as You Like" img="/assets/images/settings.png">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">Settings</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- System Configuration -->
            <div v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.can.view_system || page.props.auth.isAdmin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">System Configuration</h2>
                    
                    <div class="space-y-6">
                        <div v-if="page.props.auth.can.permission_manage || page.props.auth.can.manage_system || page.props.auth.isAdmin">
                            <h3 class="text-lg font-medium mb-2">User & Access Management</h3>
                            <ul class="space-y-2">
                                <li><Link :href="route('settings.users.index')" class="text-gray-600 hover:text-indigo-600">Manage Users</Link></li>
                                <li><Link :href="route('settings.roles.index')" class="text-gray-600 hover:text-indigo-600">Roles</Link></li>
                                <li><Link :href="route('settings.email-notifications.index')" class="text-gray-600 hover:text-indigo-600">Email Notifications</Link></li>
                                <li><a href="#" class="text-gray-600 hover:text-indigo-600">Permissions</a></li>
                                <li><a href="#" class="text-gray-600 hover:text-indigo-600">Audit Trials</a></li>
                            </ul>
                        </div>
                        
                        <div v-if="page.props.auth.can.asset_manage || page.props.auth.can.setting_manage || page.props.auth.isAdmin">
                            <h3 class="text-lg font-medium mb-2">Asset Management</h3>
                            <ul class="space-y-2">
                                <li><Link :href="route('settings.asset-depreciation.index')" class="text-gray-600 hover:text-indigo-600">Asset Depreciation Settings</Link></li>
                            </ul>
                        </div>
                        
                    </div>
                </div>
            </div>

            <!-- Logistics Management -->
            <div v-if="page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.can.view_system || page.props.auth.isAdmin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h2 class="text-xl font-semibold mb-4 border-b pb-2">Logistics Management</h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-medium mb-2">Transportation</h3>
                            <ul class="space-y-2">
                                <li><Link :href="route('settings.logistics.companies.index')" class="text-gray-600 hover:text-indigo-600">Logistic Companies</Link></li>
                                <li><Link :href="route('settings.drivers.index')" class="text-gray-600 hover:text-indigo-600">Manage Drivers</Link></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Status (for view-system users) -->
            <div v-if="page.props.auth.can.view_system && !page.props.auth.can.manage_system && !page.props.auth.isAdmin" class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4 text-blue-800">System Status</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-blue-700">View Mode:</span>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">Active</span>
                    </div>
                    <p class="text-blue-600 text-sm">
                        You have view access to all system modules. 
                        Contact an administrator for action permissions.
                    </p>
                </div>
            </div>

            <!-- Fallback for users with no specific settings permissions -->
            <div v-if="!hasAnySettingsPermission" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4 text-yellow-800">Settings Access</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-yellow-700">Access Level:</span>
                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">Limited</span>
                    </div>
                    <p class="text-yellow-600 text-sm">
                        You have limited access to settings. Contact an administrator for additional permissions.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from "@inertiajs/vue3";
import { ref, onMounted, computed } from 'vue';

// Get page props
const page = usePage();

// Check if user has any settings permissions
const hasAnySettingsPermission = computed(() => {
    const can = page.props.auth?.can || {};
    return can.permission_manage || 
           can.system_settings || 
           can.manage_system || 
           can.view_system || 
           can.asset_manage || 
           can.setting_manage || 
           page.props.auth?.isAdmin;
});

onMounted(() => {
    console.log('Settings page mounted');
    console.log('Page props:', page.props);
    console.log('Auth user:', page.props.auth?.user);
    console.log('User permissions:', page.props.auth?.can);
    console.log('Has any settings permission:', hasAnySettingsPermission.value);
});
</script>
