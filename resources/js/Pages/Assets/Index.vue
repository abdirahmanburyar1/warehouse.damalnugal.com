<template>
    <AuthenticatedLayout title="Asset Management" description="Comprehensive asset tracking and approval system"
        img="/assets/images/asset-header.png">


        <div class="space-y-6">
            <!-- Header Section with Stats -->
            <div class="bg-white shadow-xl rounded-2xl">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 bg-white/20 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-10 h-10 text-white">
                                    <path
                                        d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27z" />
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white">Asset Management</h1>
                                <p class="text-blue-100 text-sm mt-1">
                                    Track, manage, and approve your organization's assets
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 sm:mt-0 flex space-x-3">
                            <Link v-if="$page.props.auth.can.asset_create" :href="route('assets.get-create')"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-white hover:bg-indigo-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="w-4 h-4 mr-2">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                            </svg>
                            Add Asset
                            </Link>

                            <!-- Bulk Upload Buttons -->
                            <button v-if="page.props.auth.can.asset_export" @click="downloadTemplate"
                                class="inline-flex items-center px-4 py-2 border border-white/50 text-sm font-medium rounded-md text-white hover:bg-white/10 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-4 h-4 mr-2">
                                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" />
                                </svg>
                                Download Template
                            </button>

                            <button v-if="page.props.auth.can.asset_bulk_import" @click="showBulkUploadModal = true"
                                class="inline-flex items-center px-4 py-2 border border-white/50 text-sm font-medium rounded-md text-white hover:bg-white/10 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-4 h-4 mr-2">
                                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                                </svg>
                                Bulk Upload
                            </button>

                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="p-6 sm:p-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="w-8 h-8 text-blue-600">
                                        <path
                                            d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-blue-600">Total Assets</p>
                                    <p class="text-2xl font-bold text-blue-900">{{ props.assetsCount }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-xl p-6 border border-green-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="w-8 h-8 text-green-600">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-green-600">Functioning Assets</p>
                                    <p class="text-2xl font-bold text-green-900">
                                        {{props.assets.data.filter(a => a.status === 'functioning').length }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-xl p-6 border border-yellow-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="w-8 h-8 text-yellow-600">
                                        <path fill-rule="evenodd"
                                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-yellow-600">Pending Approval</p>
                                    <p class="text-2xl font-bold text-yellow-900">
                                        {{ props.assets.data.filter(a => a.status === 'pending_approval').length }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-xl p-6 border border-red-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="w-8 h-8 text-red-600">
                                        <path fill-rule="evenodd"
                                            d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter and Search Section -->
            <div class="bg-white rounded-xl">
                <div class="p-4">
                    <div class="grid grid-cols-1 lg:grid-cols-6 gap-4">
                        <div class="lg:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input v-model="search" type="text" id="search"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Search by name, tag no, serial, source..." />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <Multiselect v-model="categoryFilter" :options="props.categories || []" placeholder="All Categories"
                                label="name" track-by="id" :show-labels="false" :close-on-select="true" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Asset Type</label>
                            <Multiselect v-model="typeFilter" :options="filteredTypeOptions" :placeholder="categoryFilter ? 'All Types' : 'Select Category first'"
                                label="name" track-by="id" :show-labels="false" :close-on-select="true" :disabled="!categoryFilter" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fund Source</label>
                            <Multiselect v-model="fundSourceFilter" :options="props.fundSources || []" placeholder="All Fund Sources"
                                label="name" track-by="id" :show-labels="false" :close-on-select="true" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assignee</label>
                            <Multiselect v-model="assigneeFilter" :options="props.assignees || []" placeholder="All Assignees"
                                label="name" track-by="id" :show-labels="false" :close-on-select="true" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                            <Multiselect v-model="regionFilter" :options="regionOptions" placeholder="All Regions"
                                label="name" track-by="id" :show-labels="false" :close-on-select="true" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                            <Multiselect v-model="districtFilter" :options="districtsOptions" :placeholder="regionFilter ? 'All Districts' : 'Select Region first'"
                                label="name" track-by="id" :show-labels="false" :close-on-select="true" :disabled="!regionFilter" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Asset Location</label>
                            <Multiselect
                                v-model="locationFilter"
                                :options="locationOptions"
                                :placeholder="districtFilter ? 'All Locations' : 'Select District first'"
                                label="name"
                                track-by="id"
                                :show-labels="false"
                                :close-on-select="true"
                                :disabled="!districtFilter"
                                @select="onLocationChange"
                            >
                                <template #option="{ option }">
                                    <div>
                                        <span class="font-medium text-gray-900 block">{{ option.name }}</span>
                                        <span v-if="option.district || option.region" class="text-gray-500 text-xs block">{{ [option.district, option.region].filter(Boolean).join(' · ') }}</span>
                                    </div>
                                </template>
                            </Multiselect>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sub-location</label>
                            <Multiselect
                                v-model="subLocationFilter"
                                :options="subLocationOptions"
                                :placeholder="locationFilter ? 'All Sub-locations' : 'Select Location first'"
                                label="name"
                                track-by="id"
                                :show-labels="false"
                                :close-on-select="true"
                                :disabled="!locationFilter"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <Multiselect v-model="selectedStatus" :options="statusOptions" placeholder="All Statuses"
                                label="label" track-by="value" :show-labels="false" :close-on-select="true" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Acquisition From</label>
                            <input v-model="acquisitionFrom" type="date" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Acquisition To</label>
                            <input v-model="acquisitionTo" type="date" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Created From</label>
                            <input v-model="createdFrom" type="date" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Created To</label>
                            <input v-model="createdTo" type="date" class="block w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>

                        <div class="flex items-end space-x-2">
                            <button @click="clearFilters"
                                class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors text-sm">
                                Clear
                            </button>
                            <select v-model="per_page" @change="props.filters.page = 1; reloadAssets();"
                                class="w-[140px] border border-gray-300 rounded-md py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="10">10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Assets Table -->
            <div class="bg-white rounded-2xl">
                <div v-if="loading" class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                </div>

                <div v-else-if="props.assets.data.length === 0" class="text-center py-12">
                    <svg v-if="!hasActiveFilters" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-400 mx-auto mb-4">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                        class="w-16 h-16 text-gray-400 mx-auto mb-4">
                        <path
                            d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                        {{ hasActiveFilters ? 'No assets found' : 'Select filters to view assets' }}
                    </h3>
                    <p class="text-gray-500">
                        {{ hasActiveFilters ? 'No assets match your current filters.' : 'Please select at least one filter or enter a search term above to load assets.' }}
                    </p>
                </div>

                <div v-else class="relative bg-white/90 backdrop-blur-sm rounded-xl shadow-sm ring-1 ring-gray-200/70">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr style="background-color: #F4F7FB;">
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Asset Tag & Name</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Serial Number</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Category</span>
                                </th>
                                <th class="px-3 py-3 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Type</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Status</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Assignee</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Region</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Location</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Sub Location</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Acquisition Date</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Value</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold border-r" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6; border-right-color: #B7C6E6;">
                                    <span>Fund Source</span>
                                </th>
                                <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                    <span>Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-y divide-gray-100">
                            <tr v-for="asset in props.assets.data" :key="asset.id"
                                 class="odd:bg-white even:bg-gray-50/60 hover:bg-indigo-50 transition-colors duration-150 border-b border-gray-100 group">
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        <Link :href="route('assets.show', asset.asset_id || asset.asset?.id)" class="text-blue-600 hover:text-blue-800 underline">
                                            {{ asset.asset_tag || 'N/A' }}
                                        </Link>
                                        <div class="text-xs font-semibold text-gray-900 mt-1">
                                        {{ asset.asset_name || 'N/A' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.serial_number || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.category?.name || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.type?.name || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <span :class="{
                                        'bg-green-100 text-green-800': asset.status === 'functioning',
                                        'bg-yellow-100 text-yellow-800': asset.status === 'pending_approval',
                                        'bg-orange-100 text-orange-800': asset.status === 'maintenance' || asset.status === 'not_functioning',
                                        'bg-red-100 text-red-800': asset.status === 'disposed',
                                        'bg-gray-100 text-gray-800': !['functioning', 'pending_approval', 'maintenance', 'not_functioning', 'disposed'].includes(asset.status)
                                    }"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                        <span v-if="asset.status === 'pending_approval'"
                                            class="w-2 h-2 bg-yellow-400 rounded-full mr-1 animate-pulse"></span>
                                        <span
                                            v-else-if="asset.status === 'functioning'"
                                            class="w-2 h-2 bg-green-400 rounded-full mr-1"></span>
                                        <span v-else-if="asset.status === 'maintenance' || asset.status === 'not_functioning'"
                                            class="w-2 h-2 bg-orange-400 rounded-full mr-1 animate-pulse"></span>
                                        <span v-else class="w-2 h-2 bg-gray-400 rounded-full mr-1"></span>
                                        {{ formatStatus(asset.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.assignee?.name || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.region?.name || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.facility?.name || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.sub_location?.name || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ formatDate(asset.acquisition_date) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ formatCurrency(asset.original_value) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top border-r border-gray-100">
                                    <div class="text-xs text-gray-900">
                                        {{ asset.fund_source?.name || 'N/A' }}
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-top">
                                        <div class="relative dropdown-container">
                                            <button @click.stop="toggleDropdown(asset.id)" 
                                                class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-gray-100 hover:to-gray-200 text-gray-700 hover:text-gray-900 text-xs font-medium rounded-md border border-gray-200 transition-all duration-200 group-hover:shadow-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                                    <path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                                                </svg>
                                            </button>
                                            
                                            <!-- Dropdown Menu -->
                                            <div v-if="activeDropdown === asset.id" 
                                                class="absolute right-0 z-[9999] w-48 bg-white rounded-md shadow-lg border border-gray-200 py-1"
                                                :class="getDropdownPosition(asset.id)">

                                                <Link v-if="page.props.auth.can.asset_edit" :href="route('assets.edit', asset.asset_id || asset.asset?.id)"
                                                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2 text-gray-600">
                                                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                                    </svg>
                                                    Edit Asset
                                                </Link>
                                                
                                                <Link v-if="page.props.auth.can.asset_view" :href="route('assets.history.index', asset.id)"
                                                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                                    title="View asset item history">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2 text-green-600">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.25h-2.25a.75.75 0 000 1.5h2.25v2.25a.75.75 0 001.5 0v-2.25h2.25a.75.75 0 000-1.5h-2.25v-2.25z" clip-rule="evenodd" />
                                                    </svg>
                                                    Asset Item History
                                                </Link>
                                                
                                                <div class="border-t border-gray-100"></div>
                                                
                                                <button v-if="page.props.auth.can.asset_manage" @click="openTransferModal(asset); closeDropdown()"
                                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2 text-blue-600">
                                                        <path fill-rule="evenodd" d="M13.2 2.24a.75.75 0 00.04 1.06L15.54 5H9.5a7 7 0 000 14h.75a.75.75 0 000-1.5H9.5A5.5 5.5 0 019.5 6.5h6.04l-2.3 1.7a.75.75 0 001.02 1.1l3.5-2.6a.75.75 0 000-1.2l-3.5-2.6a.75.75 0 00-1.06.04z" clip-rule="evenodd" />
                                                    </svg>
                                                    Transfer Asset
                                                </button>

                                                <button v-if="page.props.auth.can.asset_edit && isFunctioningStatus(asset.status)"
                                                    @click="toggleAssetStatus(asset, 'not_functioning'); closeDropdown()"
                                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2 text-orange-600">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    Mark as Not functioning
                                                </button>
                                                <button v-if="page.props.auth.can.asset_edit && !isFunctioningStatus(asset.status)"
                                                    @click="toggleAssetStatus(asset, 'functioning'); closeDropdown()"
                                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2 text-green-600">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                                    </svg>
                                                    Mark as Functioning
                                                </button>
                                                                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                

                <!-- Pagination -->
                <div v-if="hasActiveFilters" class="bg-gray-50 px-6 py-3 border-t border-gray-200 mb-[80px] flex justify-between">
                    <!-- FROM TO COUNT -->
                    <div class="text-sm text-gray-500">
                        Showing {{ props.assets?.meta?.from || 0 }} to {{ props.assets?.meta?.to || 0 }} of {{ props.assets?.meta?.total || 0 }} assets
                    </div>
                    <div class="flex items-center justify-end">
                        <TailwindPagination v-if="props.assets?.meta" :data="props.assets" @pagination-change-page="getResults" :limit="2" />
                    </div>
                </div>
            </div>
        </div>





        <!-- Transfer Modal -->
        <TransitionRoot as="template" :show="showTransferModal">
            <Dialog as="div" :open="showTransferModal" class="fixed z-50 inset-0 overflow-y-auto" @close="showTransferModal = false">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0"
                        enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100"
                        leave-to="opacity-0">
                        <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                    </TransitionChild>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div
                            class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div
                                        class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                            class="w-6 h-6 text-blue-600">
                                            <path d="M16 17l-4 4-4-4m4 4V3" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <DialogTitle as="h3" class="text-lg leading-6 font-medium text-gray-900">
                                            Transfer Asset
                                        </DialogTitle>
                                        <div class="mt-2">
                                            <div class="text-sm text-gray-500 space-y-2">
                                                <p>
                                                    Transfer asset <strong>{{ selectedAsset?.asset_tag }}</strong> to a new assignee.
                                                </p>
                                            </div>
                                            <div class="mt-4 space-y-4">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                                                        <Multiselect
                                                            v-model="transferData.region"
                                                            :options="props.regions || []"
                                                            :searchable="true"
                                                            :close-on-select="true"
                                                            :show-labels="false"
                                                            :allow-empty="true"
                                                            placeholder="Select Region"
                                                            track-by="id"
                                                            label="name"
                                                            :open-direction="'bottom'"
                                                            @select="() => { transferData.district = null; transferData.facility = null; }"
                                                            @clear="() => { transferData.district = null; transferData.facility = null; }" />
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                                                        <Multiselect
                                                            v-model="transferData.district"
                                                            :options="transferDistrictOptions"
                                                            :searchable="true"
                                                            :close-on-select="true"
                                                            :show-labels="false"
                                                            :allow-empty="true"
                                                            placeholder="Select District"
                                                            track-by="id"
                                                            label="name"
                                                            :open-direction="'bottom'"
                                                            :disabled="!transferData.region"
                                                            @select="() => { transferData.facility = null; }"
                                                            @clear="() => { transferData.facility = null; }" />
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Asset Location</label>
                                                        <Multiselect
                                                            v-model="transferData.facility"
                                                            :options="transferFacilityOptions"
                                                            :searchable="true"
                                                            :close-on-select="true"
                                                            :show-labels="false"
                                                            :allow-empty="true"
                                                            placeholder="Select Location"
                                                            track-by="id"
                                                            label="name"
                                                            :open-direction="'bottom'"
                                                            :disabled="!transferData.district" />
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Assignee</label>
                                                        <Multiselect 
                                                            v-model="transferData.assignee" 
                                                            :options="assigneeOptions"
                                                            :searchable="true" 
                                                            :close-on-select="true" 
                                                            :show-labels="false"
                                                            :allow-empty="true" 
                                                            placeholder="Select Assignee" 
                                                            track-by="id" 
                                                            label="name" 
                                                            :open-direction="'bottom'"
                                                            @select="onAssigneeSelect"
                                                            @clear="onAssigneeClear" />
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Transfer Date (Required)</label>
                                                        <input v-model="transferData.transfer_date" type="date"
                                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                                                    </div>
                                                </div>
                                                
                                                <div class="grid grid-cols-1 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                                                        <textarea v-model="transferData.assignment_notes" rows="2"
                                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                            placeholder="Add any notes about the transfer"></textarea>
                                                    </div>
                                                </div>
                                                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" @click="transferAsset"
                                    :disabled="!transferData.assignee || !transferData.transfer_date || transferring"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                    <svg v-if="transferring" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    {{ transferring ? 'Transferring...' : 'Transfer' }}
                                </button>
                                <button type="button" @click="showTransferModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- New Assignee Modal -->
        <Modal :show="showAssigneeModal" @close="showAssigneeModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add New Assignee</h2>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="new_assignee_name" value="Full Name" />
                        <input id="new_assignee_name" name="new_assignee_name" type="text" class="mt-1 block w-full"
                            placeholder="e.g., John Doe" required v-model="newAssignee.name" />
                    </div>
                    <div>
                        <InputLabel for="new_assignee_email" value="Email (optional)" />
                        <input id="new_assignee_email" type="email" class="mt-1 block w-full"
                            placeholder="name@example.com" v-model="newAssignee.email" />
                    </div>
                    <div>
                        <InputLabel for="new_assignee_phone" value="Phone (optional)" />
                        <input id="new_assignee_phone" name="new_assignee_phone" type="text" class="mt-1 block w-full"
                            placeholder="e.g., +1 555 123 4567" v-model="newAssignee.phone" />
                    </div>
                    <div>
                        <InputLabel for="new_assignee_department" value="Department (optional)" />
                        <input id="new_assignee_department" name="new_assignee_department" type="text"
                            class="mt-1 block w-full" placeholder="e.g., IT" v-model="newAssignee.department" />
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

        <!-- Approval Modal -->
        <TransitionRoot as="template" :show="showApprovalModal">
            <Dialog as="div" :open="showApprovalModal" class="fixed z-50 inset-0 overflow-y-auto" @close="showApprovalModal = false">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0"
                        enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100"
                        leave-to="opacity-0">
                        <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                    </TransitionChild>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div
                            class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10"
                                        :class="approvalAction === 'approve' ? 'bg-green-100' : 'bg-red-100'">
                                        <svg v-if="approvalAction === 'approve'" xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-green-600">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                            fill="currentColor" class="w-6 h-6 text-red-600">
                                            <path d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <DialogTitle as="h3" class="text-lg leading-6 font-medium text-gray-900">
                                            {{ approvalAction === 'approve' ? 'Approve' : 'Reject' }} Asset
                                        </DialogTitle>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-600 mb-2">
                                                {{ selectedAsset?.asset_tag }} - {{ selectedAsset?.serial_number }}
                                            </p>
                                            <label for="approval-notes"
                                                class="block text-sm font-medium text-gray-700 mb-2">
                                                Notes (Optional)
                                            </label>
                                            <textarea id="approval-notes" v-model="approvalNotes" rows="3"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="Add any notes about your decision..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" 
                                    @click="processApproval" 
                                    :disabled="processingApproval"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                                    :class="approvalAction === 'approve' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500'">
                                    <svg v-if="processingApproval" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    {{ processingApproval ? 'Processing...' : (approvalAction === 'approve' ? 'Approve' : 'Reject') }}
                                </button>
                                <button type="button" @click="showApprovalModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Transfer Approval Modal -->
        <TransitionRoot as="template" :show="showTransferApprovalModal">
            <Dialog as="div" :open="showTransferApprovalModal" class="fixed z-50 inset-0 overflow-y-auto" @close="showTransferApprovalModal = false">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0"
                        enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100"
                        leave-to="opacity-0">
                        <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                    </TransitionChild>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div
                            class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10"
                                        :class="transferApprovalData.action === 'approve' ? 'bg-green-100' : 'bg-red-100'">
                                        <svg v-if="transferApprovalData.action === 'approve'" xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-green-600">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                            fill="currentColor" class="w-6 h-6 text-red-600">
                                            <path d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <DialogTitle as="h3" class="text-lg leading-6 font-medium text-gray-900">
                                            {{ transferApprovalData.action === 'approve' ? 'Approve' : 'Reject' }} Transfer
                                        </DialogTitle>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-600 mb-2">
                                                Transfer ID: {{ transferApprovalData.approval_id }}
                                            </p>
                                            <label for="transfer-approval-notes"
                                                class="block text-sm font-medium text-gray-700 mb-2">
                                                Notes (Optional)
                                            </label>
                                            <textarea id="transfer-approval-notes" v-model="transferApprovalData.notes" rows="3"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="Add any notes about your decision..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" 
                                    @click="processTransferApproval" 
                                    :disabled="processingApproval"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                                    :class="transferApprovalData.action === 'approve' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500'">
                                    <svg v-if="processingApproval" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    {{ processingApproval ? 'Processing...' : (transferApprovalData.action === 'approve' ? 'Approve' : 'Reject') }}
                                </button>
                                <button type="button" @click="showTransferApprovalModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Retirement Modal -->
        <TransitionRoot as="template" :show="showRetirementModal">
            <Dialog as="div" :open="showRetirementModal" class="fixed z-50 inset-0 overflow-y-auto" @close="showRetirementModal = false">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0"
                        enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100"
                        leave-to="opacity-0">
                        <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                    </TransitionChild>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div
                            class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                                                         <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 sm:mx-0 sm:h-10 sm:w-10">
                                         <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-orange-600">
                                             <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 11-.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807c1.123 0 2.087-.816 2.285-1.917l.841-10.52.149.023a.75.75 0 11-.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                         </svg>
                                     </div>
                                     <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                         <DialogTitle as="h3" class="text-lg leading-6 font-medium text-gray-900">
                                             Initiate Asset Retirement
                                         </DialogTitle>
                                         <div class="mt-2">
                                             <p class="text-sm text-gray-600 mb-4">
                                                 Submit this asset for retirement approval. The retirement request will be reviewed by authorized personnel.
                                             </p>
                                             <div class="space-y-4">
                                                 <div>
                                                     <label for="retirement-reason" class="block text-sm font-medium text-gray-700 mb-2">
                                                         Retirement Reason <span class="text-red-500">*</span>
                                                     </label>
                                                     <textarea id="retirement-reason" v-model="retirementData.retirement_reason" rows="3"
                                                         class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                         placeholder="Enter the reason for retiring this asset..."></textarea>
                                                 </div>
                                                 <div>
                                                     <label for="retirement-date" class="block text-sm font-medium text-gray-700 mb-2">
                                                         Retirement Date <span class="text-red-500">*</span>
                                                     </label>
                                                     <input id="retirement-date" v-model="retirementData.retirement_date" type="date"
                                                         class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" />
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" 
                                    @click="processRetirement" 
                                    :disabled="processingApproval || !retirementData.retirement_reason || !retirementData.retirement_date"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm bg-orange-600 hover:bg-orange-700 focus:ring-orange-500">
                                    <svg v-if="processingApproval" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    {{ processingApproval ? 'Submitting...' : 'Submit for Retirement' }}
                                </button>
                                <button type="button" @click="showRetirementModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Bulk Upload Modal -->
        <TransitionRoot as="template" :show="showBulkUploadModal">
            <Dialog as="div" :open="showBulkUploadModal" class="fixed z-50 inset-0 overflow-y-auto" @close="showBulkUploadModal = false">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0"
                        enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100"
                        leave-to="opacity-0">
                        <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                    </TransitionChild>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div class="inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-blue-600">
                                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" />
                                        </svg>
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <DialogTitle as="h3" class="text-lg leading-6 font-medium text-gray-900">
                                            Bulk Upload Assets
                                        </DialogTitle>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-600 mb-4">
                                                Upload an Excel file (.xlsx) to import multiple assets at once. The Excel file should include all required fields: Asset Tag, Asset Name, Category, Type, Fund Source, Region, Asset Location, and Sub Location. Make sure to use the correct template format.
                                            </p>
                                            
                                            <!-- File Upload Area -->
                                            <div class="mt-4">
                                                <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md" 
                                                     :class="{'border-blue-400 bg-blue-50': isDragOver, 'border-gray-300': !isDragOver}"
                                                     @drop.prevent="handleFileDrop"
                                                     @dragover.prevent="isDragOver = true"
                                                     @dragleave.prevent="isDragOver = false">
                                                    <div class="space-y-1 text-center">
                                                        <svg v-if="!selectedFile" class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                        <svg v-else class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <div class="flex text-sm text-gray-600">
                                                            <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                                <span v-if="!selectedFile">Upload a file</span>
                                                                <span v-else>{{ selectedFile.name }}</span>
                                                                <input id="file-upload" name="file-upload" type="file" class="sr-only" accept=".xlsx,.xls" @change="handleFileSelect" />
                                                            </label>
                                                            <p v-if="!selectedFile" class="pl-1">or drag and drop</p>
                                                        </div>
                                                        <p v-if="!selectedFile" class="text-xs text-gray-500">Excel files only (.xlsx, .xls)</p>
                                                        <p v-if="selectedFile" class="text-xs text-green-600">File selected successfully!</p>
                                                    </div>
                                                </div>

                                                <!-- Smart Import Tips -->
                                                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                                    <div class="flex">
                                                        <div class="flex-shrink-0">
                                                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                            </svg>
                                                        </div>
                                                        <div class="ml-3">
                                                            <h3 class="text-sm font-medium text-blue-800">Smart Import Tips</h3>
                                                            <div class="mt-2 text-sm text-blue-700">
                                                                <ul class="list-disc pl-5 space-y-1">
                                                                    <li>The system will automatically create new Regions, Fund Sources, Asset Locations, and Sub Locations if they don't exist</li>
                                                                    <li>Assignees must already exist in the system - they won't be created automatically</li>
                                                                    <li>Download the template to see the exact format required</li>
                                                                    <li>All required fields are clearly marked in the template</li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Upload Progress -->
                                                <div v-if="uploadProgress > 0 && uploadProgress < 100" class="mt-4">
                                                    <div class="bg-gray-200 rounded-full h-2">
                                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
                                                    </div>
                                                    <p class="text-sm text-gray-600 mt-1">Uploading... {{ uploadProgress }}%</p>
                                                </div>

                                                <!-- Error Messages -->
                                                <div v-if="uploadErrors.length > 0" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                                                    <div class="flex">
                                                        <div class="flex-shrink-0">
                                                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                            </svg>
                                                        </div>
                                                        <div class="ml-3">
                                                            <h3 class="text-sm font-medium text-red-800">Upload Errors</h3>
                                                            <div class="mt-2 text-sm text-red-700">
                                                                <ul class="list-disc pl-5 space-y-1">
                                                                    <li v-for="error in uploadErrors" :key="error">{{ error }}</li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" 
                                    @click="uploadFile" 
                                    :disabled="!selectedFile || uploading"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">
                                    <svg v-if="uploading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                    </svg>
                                    {{ uploading ? 'Uploading...' : 'Upload Assets' }}
                                </button>
                            </div>
                            <button type="button" @click="showBulkUploadModal = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>
    </AuthenticatedLayout>
</template>
<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { TailwindPagination } from "laravel-vue-pagination";
import { Link, router, usePage } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { debounce } from "lodash";
import axios from "axios";
import * as XLSX from "xlsx";
import moment from "moment";
import { useToast } from "vue-toastification";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import { reactive } from "vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Modal from "@/Components/Modal.vue";
import {
    Dialog,
    DialogOverlay,
    DialogTitle,
    TransitionRoot,
    TransitionChild,
} from "@headlessui/vue";

const toast = useToast();
const page = usePage();

// Utility functions
const formatStatus = (status) => {
    if (!status) return '-';
    const statusMap = {
        'functioning': 'Functioning',
        'not_functioning': 'Not functioning',
        'maintenance': 'Maintenance',
        'disposed': 'Disposed',
        'pending_approval': 'Pending Approval'
    };
    return statusMap[status] || status.replace('_', ' ').toUpperCase();
};

const formatDate = (date) => {
    if (!date) return '-';
    return moment(date).format('DD/MM/YYYY');
};

const formatCurrency = (value) => {
    if (!value || isNaN(value)) return '$0.00';
    return '$' + parseFloat(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

const props = defineProps({
    facilities: {
        type: Array,
        required: true,
    },
    districts: { type: Array, required: false },
    categories: { type: Array, required: false },
    types: { type: Array, required: false },
    assignees: { type: Array, required: false },
    assets: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
    },
    regions: {
        type: Array,
        required: true,
    },
    fundSources: {
        type: Array,
        required: true,
    },
    assetsCount: {
        type: Number,
        required: true,
    },
});


const showTransferModal = ref(false);
const selectedAsset = ref(null);
const transferData = reactive({
    asset_id: null,
    assignee: null,
    region: null,
    district: null,
    facility: null,
    transfer_date: "",
    assignment_notes: "",
});

const transferDistrictOptions = computed(() => {
    if (!transferData.region) return [];
    return (props.districts || []).filter(d => d.region === transferData.region.name);
});

const transferFacilityOptions = computed(() => {
    if (!transferData.district) return [];
    return (props.facilities || []).filter(f => f.district === transferData.district.name);
});

// Assignee management
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
const transferring = ref(false);

// Bulk Upload Variables
const showBulkUploadModal = ref(false);
const selectedFile = ref(null);
const isDragOver = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadErrors = ref([]);

function openTransferModal(asset) {
    console.log('Opening transfer modal for asset:', asset);
    console.log('Asset ID:', asset?.id);
    console.log('Asset data:', asset);
    
    if (!asset || !asset.id) {
        toast.error('Invalid asset data. Please try again.');
        return;
    }
    
    // Check if the asset exists in the current assets list
    const assetExists = props.assets.data.some(a => a.id === asset.id);
    if (!assetExists) {
        toast.error('Asset not found in current list. Please refresh the page and try again.');
        return;
    }
    
    selectedAsset.value = asset;
    transferData.asset_id = asset.id;
    
    // Populate with current asset values (old/current values)
    console.log('AssetItem data:', asset);
    console.log('Asset assignee:', asset.assignee);
    
    if (asset.assignee) {
        transferData.assignee = { id: asset.assignee.id, name: asset.assignee.name };
    } else {
        transferData.assignee = null;
    }
    
    if (asset.region) {
        transferData.region = { id: asset.region.id, name: asset.region.name };
    } else {
        transferData.region = null;
    }
    
    if (asset.district) {
        transferData.district = { id: asset.district.id, name: asset.district.name, region: asset.district.region };
    } else {
        transferData.district = null;
    }
    
    if (asset.facility) {
        transferData.facility = { id: asset.facility.id, name: asset.facility.name, district: asset.facility.district };
    } else {
        transferData.facility = null;
    }
    
    transferData.transfer_date = "";
    transferData.assignment_notes = "";
    showTransferModal.value = true;
}

function closeTransferModal() {
    showTransferModal.value = false;
}

const onAssigneeSelect = (opt) => {
    if (!opt) return;
    if (opt.isAddNew) {
        showAssigneeModal.value = true;
        return;
    }
    transferData.assignee = opt;
};

const onAssigneeClear = () => {
    transferData.assignee = null;
};

                // Note: Asset Location-related functions removed since we're not updating location during transfers

const createAssignee = async (e) => {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (!newAssignee.value.name) {
        toast.error('Full name is required');
        return;
    }
    isSavingAssignee.value = true;
    try {
        console.log('Creating assignee with data:', newAssignee.value);
        const { data } = await axios.post(route('assets.assignees.store'), {
            name: newAssignee.value.name,
            email: newAssignee.value.email || null,
            phone: newAssignee.value.phone || null,
            department: newAssignee.value.department || null,
        });
        console.log('Assignee created successfully:', data);
        assigneesList.value = [...assigneesList.value, data];
        transferData.assignee = { id: data.id, name: data.name };
        newAssignee.value = { name: '', email: '', phone: '', department: '' };
        showAssigneeModal.value = false;
        toast.success('Assignee created');
    } catch (e) {
        console.error('Error creating assignee:', e);
        console.error('Error response:', e.response);
        toast.error(e.response?.data || 'Failed to create assignee');
    } finally {
        isSavingAssignee.value = false;
    }
};



const assets = ref([]);
const loading = ref(false);
const selectedLocations = ref([]);
const selectedSubLocations = ref([]);
const collapsedLocations = ref([]);
const isHistoryModalOpen = ref(false);
const selectedAssetHistory = ref([]);

function openHistoryModal(asset) {
    selectedAssetHistory.value = [...asset.history].sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at)
    );
    isHistoryModalOpen.value = true;
}
function closeHistoryModal() {
    isHistoryModalOpen.value = false;
    selectedAssetHistory.value = [];
}
const isAttachmentsModalOpen = ref(false);
const selectedAttachments = ref([]);

function openAttachmentsModal(attachments) {
    selectedAttachments.value = attachments;
    isAttachmentsModalOpen.value = true;
}
function closeAttachmentsModal() {
    isAttachmentsModalOpen.value = false;
    selectedAttachments.value = [];
}

const search = ref(props.filters?.search || '');
const per_page = ref(props.filters.per_page || 25);

// Filter state for multiselect components
const selectedLocation = ref(null);
const selectedStatus = ref(null);

// Status options for multiselect
const statusOptions = ref([
    { label: 'Functioning', value: 'functioning' },
    { label: 'Not functioning', value: 'not_functioning' },
    { label: 'Maintenance', value: 'maintenance' },
    { label: 'Disposed', value: 'disposed' },
    { label: 'Pending Approval', value: 'pending_approval' }
]);

// Region, Location, SubLocation filter state
const regionFilter = ref(null);
const districtFilter = ref(null);
const locationFilter = ref(null);
const subLocationFilter = ref(null);
const subLocationOptions = ref([]);

                // Note: filteredSubLocationOptions removed since we're not using asset location fields in transfers
const fundSourceFilter = ref(null);
const categoryFilter = ref(null);
const typeFilter = ref(null);
const assigneeFilter = ref(null);
const acquisitionFrom = ref(props.filters?.acquisition_from || '');
const acquisitionTo = ref(props.filters?.acquisition_to || '');
const createdFrom = ref(props.filters?.created_from || '');
const createdTo = ref(props.filters?.created_to || '');

const hasActiveFilters = computed(() => {
    return !!(
        search.value ||
        regionFilter.value ||
        districtFilter.value ||
        locationFilter.value ||
        subLocationFilter.value ||
        fundSourceFilter.value ||
        categoryFilter.value ||
        typeFilter.value ||
        assigneeFilter.value ||
        selectedStatus.value ||
        acquisitionFrom.value ||
        acquisitionTo.value ||
        createdFrom.value ||
        createdTo.value
    );
});

const filteredTypeOptions = computed(() => {
    if (!props.types) return [];
    if (!categoryFilter.value) return [];
    return props.types.filter(t => t.asset_category_id === categoryFilter.value.id);
});

watch(() => categoryFilter.value, () => {
    typeFilter.value = null;
});

// Dropdown functionality
const activeDropdown = ref(null);

function toggleDropdown(assetId) {
    try {
        console.log('Toggling dropdown for asset:', assetId);
        activeDropdown.value = activeDropdown.value === assetId ? null : assetId;
    } catch (error) {
        console.error('Error in toggleDropdown:', error);
    }
}

function closeDropdown() {
    activeDropdown.value = null;
}

function isFunctioningStatus(status) {
    return status === 'functioning';
}

const togglingStatus = ref(false);
async function toggleAssetStatus(asset, newStatus) {
    if (togglingStatus.value) return;
    togglingStatus.value = true;
    try {
        await axios.patch(route('assets.items.toggle-status', asset.id), { status: newStatus });
        toast.success(`Asset marked as ${newStatus === 'functioning' ? 'Functioning' : 'Not functioning'}`);
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to update status');
    } finally {
        togglingStatus.value = false;
    }
}

// Function to determine dropdown position to avoid overlapping with pagination
function getDropdownPosition(assetId) {
    // Check if this is one of the last few rows (likely to overlap with pagination)
    const assetIndex = props.assets.data.findIndex(asset => asset.id === assetId);
    const totalAssets = props.assets.data.length;
    
    // If it's in the last 3 rows, position dropdown above the button
    if (assetIndex >= totalAssets - 3) {
        return 'bottom-full mb-2'; // Position above with margin bottom
    }
    
    // Default position below the button
    return 'top-full mt-2'; // Position below with margin top
}

// Close dropdown when clicking outside
onMounted(() => {
    const handleClickOutside = (event) => {
        // Check if click is outside dropdown
        if (!event.target.closest('.dropdown-container')) {
            activeDropdown.value = null;
        }
    };
    
    document.addEventListener('click', handleClickOutside);
    
    // Cleanup on unmount
    onUnmounted(() => {
        document.removeEventListener('click', handleClickOutside);
    });
});

// Initialize filters from URL on hard reload/navigation (when props.filters may not include all keys)
onMounted(async () => {
    try {
        const params = new URLSearchParams(window.location.search || '');
        // Status
        const statusParam = params.get('status');
        if (statusParam) {
            const match = (statusOptions.value || []).find(s => s.value === statusParam);
            if (match) selectedStatus.value = match;
        }
        // Region
        const regionId = params.get('region_id');
        if (regionId && Array.isArray(props.regions)) {
            const reg = props.regions.find(r => String(r.id) === String(regionId));
            if (reg) regionFilter.value = reg;
        }

        const districtId = params.get('district_id');
        if (districtId && Array.isArray(props.districts)) {
            const dist = props.districts.find(d => String(d.id) === String(districtId));
            if (dist) districtFilter.value = dist;
        }

                        // Asset Location and Sub-location (dependent)
        const locationId = params.get('location_id');
        if (locationId && Array.isArray(props.facilities)) {
            const loc = props.facilities.find(l => String(l.id) === String(locationId));
            if (loc) {
                locationFilter.value = loc;
                await onLocationChange(loc);
                const subId = params.get('sub_location_id');
                if (subId && Array.isArray(subLocationOptions.value)) {
                    const sub = subLocationOptions.value.find(s => String(s.id) === String(subId));
                    if (sub) subLocationFilter.value = sub;
                }
            }
        }
    } catch (e) {
        // ignore URL parsing errors
    }
});

const regionOptions = computed(() => props.regions || []);

const districtsOptions = computed(() => {
    if (!regionFilter.value) return [];
    return (props.districts || []).filter(d => d.region === regionFilter.value.name);
});

const locationOptions = computed(() => {
    if (!districtFilter.value) return [];
    return (props.facilities || []).filter(f => f.district === districtFilter.value.name).map((f) => ({ id: f.id, name: f.name, district: f.district, region: f.region }));
});

// Clear dependent filters when region changes
watch(() => regionFilter.value, () => {
    districtFilter.value = null;
    locationFilter.value = null;
    subLocationFilter.value = null;
    subLocationOptions.value = [];
});

watch(() => districtFilter.value, () => {
    locationFilter.value = null;
    subLocationFilter.value = null;
    subLocationOptions.value = [];
});


async function onLocationChange(selected) {
    selectedSubLocations.value = [];
    subLocationOptions.value = [];

    if (!selected) return;

    const locationId = selected.id || selected;
    try {
        const response = await axios.get(
            route("assets.locations.sub-locations", { location: locationId })
        );
        subLocationOptions.value = response.data;
    } catch (error) {
        subLocationOptions.value = [];
    }
}

// Asset transfer
async function transferAsset() {
    if (!transferData.assignee || !transferData.transfer_date) {
        toast.error("Assignee and transfer date are required.");
        return;
    }
    
    if (!selectedAsset.value || !selectedAsset.value.id) {
        toast.error("No asset selected for transfer.");
        return;
    }
    
    transferring.value = true;
    try {
        console.log('Transferring asset with data:', transferData);
        console.log('Selected asset:', selectedAsset.value);
        console.log('Asset ID being sent:', selectedAsset.value.id);
        
        const assetId = selectedAsset.value.asset_id || selectedAsset.value.asset?.id;
        console.log('AssetItem ID:', selectedAsset.value.id);
        console.log('Asset ID being sent:', assetId);
        console.log('Route being called:', route('assets.transfer', assetId));
        console.log('Full URL:', route('assets.transfer', assetId));
        
        if (!assetId) {
            toast.error('Could not determine asset ID for transfer.');
            return;
        }
        
        const response = await axios.post(route('assets.transfer', assetId), {
            assignee_id: transferData.assignee.id,
            transfer_date: transferData.transfer_date,
            assignment_notes: transferData.assignment_notes,
            assignee_name: transferData.assignee.name,
            update_asset_location: true,
            region_id: transferData.region?.id,
            district_id: transferData.district?.id,
            facility_id: transferData.facility?.id,
        });

        console.log('Transfer response:', response.data);
        
        // Update the local asset data
        if (selectedAsset.value) {
            selectedAsset.value.person_assigned = transferData.assignee.name;
            selectedAsset.value.transfer_date = transferData.transfer_date;
        }
        
        toast.success("Asset transferred successfully!");
        closeTransferModal();
        
        // Reload the assets to reflect the changes
        router.reload();
    } catch (error) {
        console.error('Transfer error:', error);
        toast.error(error.response?.data?.error || "Transfer failed.");
    } finally {
        transferring.value = false;
    }
}

function getResults(page = 1) {
    props.filters.page = page;
}

                // Watch for asset location change to load sub-locations
watch(
    () => locationFilter.value,
    async (newLocation) => {
        selectedSubLocations.value = [];
        subLocationOptions.value = [];

        if (newLocation && newLocation.id) {
            // Fetch sub-locations for the selected asset location
            try {
                const response = await axios.get(
                    route("assets.locations.sub-locations", newLocation.id)
                );
                subLocationOptions.value = response.data;
            } catch (e) {
                subLocationOptions.value = [];
            }
        }
    },
    { immediate: true }
);

// Debounced search function
const debouncedSearch = debounce(() => {
    reloadAssets();
}, 300);

watch(
    [
        () => props.filters.page,
        () => per_page.value,
        () => (selectedLocation.value ? selectedLocation.value.id : null),
        () => (selectedStatus.value ? selectedStatus.value.value : null),
        () => (regionFilter.value ? regionFilter.value.id : null),
        () => (districtFilter.value ? districtFilter.value.id : null),
        () => (locationFilter.value ? locationFilter.value.id : null),
        () => (fundSourceFilter.value ? fundSourceFilter.value.id : null),
        () => (categoryFilter.value ? categoryFilter.value.id : null),
        () => (typeFilter.value ? typeFilter.value.id : null),
        () => (assigneeFilter.value ? assigneeFilter.value.id : null),
        () => (subLocationFilter.value ? subLocationFilter.value.id : null),
        () => acquisitionFrom.value,
        () => acquisitionTo.value,
        () => createdFrom.value,
        () => createdTo.value,
    ],
    () => {
        reloadAssets();
    }
);

// Watch search separately with debouncing
watch(
    () => search.value,
    () => {
        debouncedSearch();
    }
);

// Watch for changes in selectedSubLocations and reload assets
watch(
    selectedSubLocations,
    () => {
        reloadAssets();
    },
    { deep: true }
);

function reloadAssets() {
    const query = {};
    if (props.filters.page) query.page = props.filters.page;
    if (search.value) query.search = search.value;
    if (per_page.value) query.per_page = per_page.value;
    if (selectedLocation.value && selectedLocation.value.id)
        query.location_id = selectedLocation.value.id;
    if (selectedStatus.value && selectedStatus.value.value)
        query.status = selectedStatus.value.value;
    if (regionFilter.value && regionFilter.value.id)
        query.region_id = regionFilter.value.id;
    if (districtFilter.value && districtFilter.value.id)
        query.district_id = districtFilter.value.id;
    if (locationFilter.value && locationFilter.value.id)
        query.location_id = locationFilter.value.id;
    // Single sub-location filter (dependent on location)
    if (subLocationFilter.value && subLocationFilter.value.id) {
        query.sub_location_id = subLocationFilter.value.id;
    }
    if (fundSourceFilter.value && fundSourceFilter.value.id)
        query.fund_source_id = fundSourceFilter.value.id;
    if (categoryFilter.value && categoryFilter.value.id)
        query.category_id = categoryFilter.value.id;
    if (typeFilter.value && typeFilter.value.id)
        query.type_id = typeFilter.value.id;
    if (assigneeFilter.value && assigneeFilter.value.id)
        query.assignee_id = assigneeFilter.value.id;
    if (acquisitionFrom.value) query.acquisition_from = acquisitionFrom.value;
    if (acquisitionTo.value) query.acquisition_to = acquisitionTo.value;
    if (createdFrom.value) query.created_from = createdFrom.value;
    if (createdTo.value) query.created_to = createdTo.value;
    router.get(route("assets.index"), query, {
        preserveState: true,
        preserveScroll: true,
        only: ["assets", "locations"],
    });
}

// Toggle location selection
const toggleLocation = (locationId) => {
    const index = selectedLocations.value.indexOf(locationId);
    if (index === -1) {
        selectedLocations.value.push(locationId);
        // Auto-select all sub-locations
        const location = props.facilities.find((l) => l.id === locationId);
        if (location?.sub_locations) {
            location.sub_locations.forEach((sub) => {
                if (!selectedSubLocations.value.includes(sub.id)) {
                    selectedSubLocations.value.push(sub.id);
                }
            });
        }
    } else {
        selectedLocations.value.splice(index, 1);
        // Deselect all sub-locations of this location
        const location = props.facilities.find((l) => l.id === locationId);
        if (location?.sub_locations) {
            location.sub_locations.forEach((sub) => {
                const subIndex = selectedSubLocations.value.indexOf(sub.id);
                if (subIndex !== -1) {
                    selectedSubLocations.value.splice(subIndex, 1);
                }
            });
        }
    }
};

// Format money
const formatMoney = (amount) => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

// Toggle sub-location selection
const toggleSubLocation = (subLocationId, parentLocationId) => {
    const index = selectedSubLocations.value.indexOf(subLocationId);
    if (index === -1) {
        selectedSubLocations.value.push(subLocationId);
        // Ensure parent location is selected
        if (!selectedLocations.value.includes(parentLocationId)) {
            selectedLocations.value.push(parentLocationId);
        }
    } else {
        selectedSubLocations.value.splice(index, 1);
        // Check if all sub-locations are deselected
        const location = props.facilities.find((l) => l.id === parentLocationId);
        const anySubSelected = location?.sub_locations.some((sub) =>
            selectedSubLocations.value.includes(sub.id)
        );
        if (!anySubSelected) {
            const parentIndex =
                selectedLocations.value.indexOf(parentLocationId);
            if (parentIndex !== -1) {
                selectedLocations.value.splice(parentIndex, 1);
            }
        }
    }
};

// Toggle location collapse state
const toggleCollapse = (locationId) => {
    const index = collapsedLocations.value.indexOf(locationId);
    if (index === -1) {
        collapsedLocations.value.push(locationId);
    } else {
        collapsedLocations.value.splice(index, 1);
    }
};

// Computed properties for the cards
const totalAssets = computed(() => assets.value.length);
const functioningAssets = computed(
    () => assets.value.filter((asset) => asset.status === "functioning").length
);
const maintenanceAssets = computed(
    () => assets.value.filter((asset) => asset.status === "maintenance").length
);






// Clear all filters
const clearFilters = () => {
    search.value = '';
    selectedLocation.value = null;
    selectedStatus.value = null;
    regionFilter.value = null;
    districtFilter.value = null;
    locationFilter.value = null;
    subLocationFilter.value = null;
    selectedSubLocations.value = [];
    subLocationOptions.value = [];
    fundSourceFilter.value = null;
    categoryFilter.value = null;
    typeFilter.value = null;
    assigneeFilter.value = null;
    acquisitionFrom.value = '';
    acquisitionTo.value = '';
    createdFrom.value = '';
    createdTo.value = '';
    props.filters.page = 1;
    reloadAssets();
};

// Approval system
const showApprovalModal = ref(false);
const showTransferApprovalModal = ref(false);
const showRetirementModal = ref(false);
const approvalAction = ref('');
const approvalNotes = ref('');
const processingApproval = ref(false);

// Transfer approval data
const transferApprovalData = reactive({
    asset_id: null,
    approval_id: null,
    action: '',
    notes: ''
});

// Retirement data
const retirementData = reactive({
    asset_id: null,
    retirement_reason: '',
    retirement_date: ''
});

// Permission functions
function canReviewAsset(asset) {
    return page.props.auth.can.asset_review;
}

function canApproveRejectAsset(asset) {
    return page.props.auth.can.asset_approve || page.props.auth.can.asset_reject;
}

function canInitiateTransfer(asset) {
    return page.props.auth.can.transfer_initiate;
}

function canReviewTransfer(asset) {
    return page.props.auth.can.transfer_review;
}

function canApproveRejectTransfer(asset) {
    return page.props.auth.can.transfer_approve || page.props.auth.can.transfer_reject;
}

function canInitiateRetirement(asset) {
    return page.props.auth.can.retirement_initiate;
}

function canReviewRetirement(asset) {
    return page.props.auth.can.retirement_review;
}

function canApproveRejectRetirement(asset) {
    return page.props.auth.can.retirement_approve || page.props.auth.can.retirement_reject;
}

// Legacy function for backward compatibility
function canApproveAsset(asset) {
    return canApproveRejectAsset(asset);
}

function openApprovalModal(asset, action) {
    selectedAsset.value = asset;
    approvalAction.value = action;
    approvalNotes.value = '';
    showApprovalModal.value = true;
}

function openTransferApprovalModal(asset, action) {
    selectedAsset.value = asset;
    transferApprovalData.asset_id = asset.id;
    transferApprovalData.action = action;
    transferApprovalData.notes = '';
    // Get the approval ID from the asset's approvals
    if (asset.approvals && asset.approvals.length > 0) {
        transferApprovalData.approval_id = asset.approvals[0].id;
    }
    showTransferApprovalModal.value = true;
}

function openRetirementModal(asset) {
    selectedAsset.value = asset;
    retirementData.asset_id = asset.id;
    retirementData.retirement_reason = '';
    retirementData.retirement_date = '';
    showRetirementModal.value = true;
}

async function processApproval() {
    if (!selectedAsset.value) return;

    processingApproval.value = true;
    try {
        const response = await axios.post(route('assets.approve', selectedAsset.value.id), {
            action: approvalAction.value,
            notes: approvalNotes.value
        });

        toast.success(response.data.message);
        showApprovalModal.value = false;
        router.reload();
    } catch (error) {
        toast.error(error.response?.data || 'Failed to process approval');
    } finally {
        processingApproval.value = false;
    }
}

async function processTransferApproval() {
    if (!selectedAsset.value || !transferApprovalData.approval_id) return;

    processingApproval.value = true;
    try {


        toast.success(response.data.message);
        showTransferApprovalModal.value = false;
        router.reload();
    } catch (error) {
        toast.error(error.response?.data || 'Failed to process transfer approval');
    } finally {
        processingApproval.value = false;
    }
}

async function processRetirement() {
    if (!selectedAsset.value || !retirementData.retirement_reason || !retirementData.retirement_date) {
        toast.error('Retirement reason and date are required.');
        return;
    }

    processingApproval.value = true;
    try {


        toast.success(response.data.message);
        showRetirementModal.value = false;
        router.reload();
    } catch (error) {
        toast.error(error.response?.data || 'Failed to initiate retirement');
    } finally {
        processingApproval.value = false;
    }
}

// New functions for asset indicators
const getWarrantyStatus = (asset) => {
    if (!asset.warranty_expiry_date) return 'none';
    const expiryDate = moment(asset.warranty_expiry_date);
    const today = moment();
    const diffDays = expiryDate.diff(today, 'days');

    if (diffDays < 0) {
        return 'expired';
    } else if (diffDays < 30) {
        return 'expiring';
    }
    return 'valid';
};

const getValueLevel = (asset) => {
    if (asset.original_value > 100000) return 'high';
    if (asset.original_value > 10000) return 'medium';
    return 'low';
};

const getAssetAge = (asset) => {
    if (!asset.acquisition_date) return 0;
    const acquisitionDate = moment(asset.acquisition_date);
    const today = moment();
    return today.diff(acquisitionDate, 'years');
};

const isMaintenanceDue = (asset) => {
    if (!asset.maintenance_due_date) return false;
    const dueDate = moment(asset.maintenance_due_date);
    const today = moment();
    return dueDate.diff(today, 'days') < 0;
};

const isCriticalAsset = (asset) => {
    return asset.critical_asset === true;
};

// Asset Tag Validation Function
const isValidAssetTag = (assetTag) => {
    if (!assetTag) return false;

    // Standard asset tag format: letters/numbers, usually 6+ characters
    // Example patterns: AST001, COMP-2024-001, etc.
    const standardPatterns = [
        /^[A-Z]{2,4}[0-9]{3,6}$/i,              // AST001, COMP001234
        /^[A-Z]{2,4}-[0-9]{4}-[0-9]{3,6}$/i,    // AST-2024-001
        /^[A-Z0-9]{6,12}$/i,                    // ASSET001, ABC123DEF
        /^[A-Z]{2,4}_[0-9]{4}_[0-9]{3,6}$/i     // AST_2024_001
    ];

    return standardPatterns.some(pattern => pattern.test(assetTag));
};

// Serial Number Duplicate Check Function
const isDuplicateSerialNumber = (asset) => {
    if (!asset.serial_number) return false;

    // Check if any other asset in the current data has the same serial number
    const duplicates = props.assets.data.filter(a =>
        a.id !== asset.id &&
        a.serial_number &&
        a.serial_number.toLowerCase() === asset.serial_number.toLowerCase()
    );

    return duplicates.length > 0;
};

// Bulk Upload Functions
const downloadTemplate = () => {
    window.location.href = route('assets.template.download');
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || 
            file.type === 'application/vnd.ms-excel') {
            selectedFile.value = file;
            uploadErrors.value = [];
        } else {
            toast.error('Please select a valid Excel file (.xlsx or .xls)');
        }
    }
};

const handleFileDrop = (event) => {
    event.preventDefault();
    isDragOver.value = false;
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        const file = files[0];
        if (file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || 
            file.type === 'application/vnd.ms-excel') {
            selectedFile.value = file;
            uploadErrors.value = [];
        } else {
            toast.error('Please select a valid Excel file (.xlsx or .xls)');
        }
    }
};

const uploadFile = async () => {
    if (!selectedFile.value) {
        toast.error('Please select a file to upload');
        return;
    }

    uploading.value = true;
    uploadProgress.value = 0;
    uploadErrors.value = [];

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    try {
        // Simulate upload progress
        const progressInterval = setInterval(() => {
            if (uploadProgress.value < 90) {
                uploadProgress.value += 10;
            }
        }, 100);

        const response = await axios.post(route('assets.import'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            }
        });

        clearInterval(progressInterval);
        uploadProgress.value = 100;

        toast.success('Assets imported successfully!');
        showBulkUploadModal.value = false;
        selectedFile.value = null;
        uploadProgress.value = 0;
        
        // Reload the page to show new assets
        router.reload();

    } catch (error) {
        clearInterval(progressInterval);
        uploadProgress.value = 0;
        
        if (error.response?.data?.import_errors) {
            uploadErrors.value = error.response.data.import_errors;
        } else if (error.response?.data?.error) {
            uploadErrors.value = [error.response.data.error];
        } else {
            uploadErrors.value = ['An error occurred during upload. Please try again.'];
        }
        
        toast.error('Failed to import assets. Please check the errors below.');
    } finally {
        uploading.value = false;
    }
};
</script>

<style scoped>
.add-new-option {
    color: #4f46e5;
    font-weight: 500;
}
</style>
