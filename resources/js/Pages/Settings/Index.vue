<template>
    <AuthenticatedLayout :title="'Settings'" description="Manage your system preferences and configurations" img="/assets/images/settings.png">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">System Settings</h1>
                <p class="mt-2 text-sm text-gray-500">Configure application behavior, manage users, and setup system defaults.</p>
            </div>
            
            <div class="space-y-12">
                <!-- System Configuration Section -->
                <section v-if="page.props.auth.can.permission_manage || page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.isAdmin">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">System Configuration</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Users -->
                        <Link :href="route('settings.users.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-blue-50 text-blue-600 rounded-lg group-hover:bg-blue-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Users Management</h3>
                            <p class="mt-2 text-sm text-gray-500">Manage user accounts, passwords, and access permissions across the system.</p>
                        </Link>

                        <!-- Audit Trails -->
                        <Link :href="route('settings.audit-trail.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-purple-50 text-purple-600 rounded-lg group-hover:bg-purple-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Audit Trails</h3>
                            <p class="mt-2 text-sm text-gray-500">View detailed logs of all user activities and system changes.</p>
                        </Link>

                        <!-- Email Notifications -->
                        <Link :href="route('settings.email-notifications.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-yellow-50 text-yellow-600 rounded-lg group-hover:bg-yellow-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Email Notifications</h3>
                            <p class="mt-2 text-sm text-gray-500">Configure automated email alerts and messaging templates.</p>
                        </Link>

                        <!-- Report Schedules -->
                        <Link :href="route('settings.report-schedules.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-red-50 text-red-600 rounded-lg group-hover:bg-red-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Report Schedules</h3>
                            <p class="mt-2 text-sm text-gray-500">Automate report generation and schedule delivery times.</p>
                        </Link>

                        <!-- Report Submission Rate -->
                        <Link :href="route('settings.report-submission.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-green-50 text-green-600 rounded-lg group-hover:bg-green-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Report Submission Rate</h3>
                            <p class="mt-2 text-sm text-gray-500">Monitor and configure expectations for facility reporting compliance.</p>
                        </Link>
                    </div>
                </section>

                <!-- Asset Management Section -->
                <section v-if="page.props.auth.can.asset_manage || page.props.auth.can.setting_manage || page.props.auth.isAdmin">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Asset Management</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Asset Depreciation -->
                        <Link :href="route('settings.asset-depreciation.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-teal-50 text-teal-600 rounded-lg group-hover:bg-teal-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Asset Depreciation</h3>
                            <p class="mt-2 text-sm text-gray-500">Configure depreciation rules, lifespans, and values for physical assets.</p>
                        </Link>
                    </div>
                </section>

                <!-- Logistics Management Section -->
                <section v-if="page.props.auth.can.system_settings || page.props.auth.can.manage_system || page.props.auth.can.view_system || page.props.auth.isAdmin">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Logistics & Transportation</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Logistic Companies -->
                        <Link :href="route('settings.logistics.companies.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-orange-50 text-orange-600 rounded-lg group-hover:bg-orange-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Logistic Companies</h3>
                            <p class="mt-2 text-sm text-gray-500">Manage vendor profiles and contact details for transport providers.</p>
                        </Link>

                        <!-- Manage Drivers -->
                        <Link :href="route('settings.drivers.index')" class="group block relative rounded-2xl bg-white border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="p-3 bg-sky-50 text-sky-600 rounded-lg group-hover:bg-sky-600 group-hover:text-white transition-colors duration-200">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">Manage Drivers</h3>
                            <p class="mt-2 text-sm text-gray-500">Maintain driver database, licenses, and assign them to logistics partners.</p>
                        </Link>
                    </div>
                </section>

                <!-- System Status (for view-system users) -->
                <div v-if="page.props.auth.can.view_system && !page.props.auth.can.manage_system && !page.props.auth.isAdmin" class="rounded-2xl bg-blue-50 border border-blue-100 p-6 shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">System View Mode Active</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>You currently have view-only access to system modules. Contact your system administrator if you require action permissions to manage these settings.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fallback for users with no specific settings permissions -->
                <div v-if="!hasAnySettingsPermission" class="rounded-2xl bg-yellow-50 border border-yellow-100 p-6 shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Limited Access</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>You do not have the required permissions to view or modify system settings. Please contact an administrator for assistance.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';

const page = usePage();

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
</script>