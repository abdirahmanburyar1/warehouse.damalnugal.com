<template>
    <AuthenticatedLayout title="Manage Facilities" description="Manage facilities" img="/assets/images/facility.png">
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-8 py-8">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="mb-6 lg:mb-0">
                        <h1 class="text-3xl font-bold text-white mb-2">Facilities Management</h1>
                        <p class="text-blue-100 text-lg">Manage and monitor all healthcare facilities in the system</p>
                        <div class="flex items-center mt-4 space-x-6 text-blue-100">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                                <span class="text-sm">{{ activeCount }} Active</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-red-400 rounded-full mr-2"></div>
                                <span class="text-sm">{{ inactiveCount }} Inactive</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-blue-400 rounded-full mr-2"></div>
                                <span class="text-sm">{{ totalCount }} Total</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                        <!-- Excel Upload Button -->
                        <button v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin" @click="openUploadModal" 
                            class="inline-flex items-center px-6 py-3 bg-white text-blue-600 font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 cursor-pointer border-2 border-transparent hover:border-blue-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Import Excel
                        </button>

                        <!-- Add Facility Type Button -->
                        <button
                            v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                            @click="openFacilityTypeModal"
                            class="inline-flex items-center px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Facility Type
                        </button>
                        
                        <!-- Add Facility Button -->
                        <Link v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin" :href="route('facilities.create')"
                            class="inline-flex items-center px-6 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Facility
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filters Section -->
            <div class="bg-gray-50 px-8 py-6 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                    <!-- Search Bar -->
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Search Facilities</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input
                                type="text"
                                v-model="search"
                                placeholder="Search by name, type, manager, or district..."
                                class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white shadow-sm transition-all duration-200"
                            >
                        </div>
                    </div>
                    
                    <!-- District Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Filter by District</label>
                        <Multiselect
                            v-model="district"
                            :options="props.districts"
                            placeholder="All Districts"
                            :searchable="true"
                            :allow-empty="true"
                            :show-labels="false"
                            class="multiselect-professional"
                        />
                    </div>
                    
                    <!-- Facility Type Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Filter by Type</label>
                        <Multiselect
                            v-model="facilityType"
                            :options="[...facilityTypes, ADD_NEW_FACILITY_TYPE_OPTION]"
                            placeholder="All Types"
                            :searchable="true"
                            :allow-empty="true"
                            :show-labels="false"
                            @select="handleFacilityTypeSelect"
                            class="multiselect-professional"
                        >
                            <template v-slot:option="{ option }">
                                <div>
                                    <span
                                        v-if="option === ADD_NEW_FACILITY_TYPE_OPTION"
                                        class="text-indigo-600 font-medium"
                                    >
                                        + Add New Facility Type
                                    </span>
                                    <span v-else>{{ option }}</span>
                                </div>
                            </template>
                        </Multiselect>
                    </div>
                    
                    <!-- Per Page Selection -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Items per page</label>
                        <select
                            v-model="per_page"
                            @change="props.filters.page = 1"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white shadow-sm transition-all duration-200"
                        >
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Professional Table Section -->
            <div v-if="!props.facilities.data.length" class="text-center py-12">
                <div class="max-w-md mx-auto">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No facilities found</h3>
                    <p class="text-gray-500 mb-6">Get started by creating your first facility or upload an Excel file.</p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <Link
                            v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                            :href="route('facilities.create')"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
                        >
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Facility
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Professional Table Section -->
            <div v-else class="overflow-x-auto">
                <table class="w-full overflow-hidden text-sm text-left table-sm rounded-t-lg">
                    <thead>
                        <tr style="background-color: #F4F7FB;">
                            <th class="w-[50px] px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">No.</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Facility</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Type</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Manager</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Handled By</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">District</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Status</th>
                            <th class="px-3 py-2 text-xs font-bold text-right" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(facility, index) in props.facilities.data"
                            :key="facility.id"
                            class="hover:bg-gray-50 transition-colors duration-150 border-b"
                            style="border-bottom: 1px solid #B7C6E6;"
                        >
                            <td class="px-3 py-2 w-[50px] max-w-[50px] text-center">
                                {{ index + 1 }}
                            </td>
                            <td class="px-3 py-2 w-[200px] max-w-[200px]">
                                <div class="text-xs font-medium text-gray-800 capitalize">
                                    {{ facility.name }}
                                </div>
                                <div class="text-xs text-gray-500 capitalize">
                                    ID: {{ facility.id || 'N/A' }}
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ facility.facility_type || "N/A" }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ facility.user?.name || "N/A" }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                    {{ facility.handledby?.name || "N/A" }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    {{ facility.district || "N/A" }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                    :class="{
                                        'bg-green-100 text-green-800': facility.is_active,
                                        'bg-red-100 text-red-800': !facility.is_active,
                                    }"
                                >
                                    <span class="w-2 h-2 rounded-full mr-1.5" :class="{
                                        'bg-green-400': facility.is_active,
                                        'bg-red-400': !facility.is_active,
                                    }"></span>
                                    {{ facility.is_active ? "Active" : "Inactive" }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-center">
                                <div class="flex items-end justify-end space-x-2">
                                    <Link
                                        v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                                        :href="route('facilities.edit', facility.id)"
                                        class="inline-flex items-center p-1.5 border border-transparent rounded-lg text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200"
                                        title="Edit Facility"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </Link>
                                    <button
                                        v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                                        @click="confirmToggleStatus(facility)"
                                        :disabled="loadingProducts.has(facility.id)"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        :class="{
                                            'opacity-50 cursor-not-allowed': loadingProducts.has(facility.id),
                                            'bg-red-500': !facility.is_active,
                                            'bg-green-500': facility.is_active,
                                        }"
                                        :title="facility.is_active ? 'Deactivate Facility' : 'Activate Facility'"
                                    >
                                        <span
                                            :class="{
                                                'translate-x-5': facility.is_active,
                                                'translate-x-0': !facility.is_active,
                                                'bg-gray-400 animate-pulse': loadingProducts.has(facility.id),
                                            }"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        ></span>
                                    </button>
                                    
                                    <!-- View-only message for users without edit permissions -->
                                    <span
                                        v-if="!$page.props.auth.can.facility_manage && !$page.props.auth.isAdmin"
                                        class="text-xs text-gray-500 italic"
                                    >
                                        View Only
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Enhanced Pagination -->
            <div class="bg-gray-50 px-8 py-6 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                    <div class="text-sm text-gray-700 font-medium">
                        Showing <span class="font-bold text-gray-900">{{ props.facilities.meta.from || 0 }}</span> to 
                        <span class="font-bold text-gray-900">{{ props.facilities.meta.to || 0 }}</span> of 
                        <span class="font-bold text-gray-900">{{ props.facilities.meta.total || 0 }}</span> facilities
                    </div>
                    <TailwindPagination 
                        :data="props.facilities"
                        :limit="2"
                        class="flex items-center space-x-2"
                        @pagination-change-page="getResults"
                    />
                </div>
            </div>
        </div>

        <!-- New Facility Type Modal -->
        <div
            v-if="showFacilityTypeModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click="closeFacilityTypeModal"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg" @click.stop>
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Add Facility Type</h3>
                        <p class="text-sm text-gray-500 mt-1">Create a new facility type</p>
                    </div>
                    <button
                        @click="closeFacilityTypeModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                        :disabled="isCreatingFacilityType"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Facility Type Name</label>
                    <input
                        v-model="newFacilityType"
                        type="text"
                        class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white shadow-sm transition-all duration-200"
                        placeholder="Enter facility type name"
                        :disabled="isCreatingFacilityType"
                        @keyup.enter="createFacilityType"
                    />
                </div>

                <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
                    <button
                        @click="closeFacilityTypeModal"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                        :disabled="isCreatingFacilityType"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                        @click="createFacilityType"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-amber-600 hover:to-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!newFacilityType || isCreatingFacilityType"
                    >
                        <svg v-if="isCreatingFacilityType" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isCreatingFacilityType ? 'Creating...' : 'Create Facility Type' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Excel Upload Modal -->
        <div
            v-if="showUploadModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click="closeUploadModal"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Upload Facilities</h3>
                        <p class="text-sm text-gray-500 mt-1">Import facilities from Excel file</p>
                    </div>
                    <button
                        @click="closeUploadModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <!-- Download Template Section -->
                    <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-green-800">Need a template?</h4>
                                <p class="text-sm text-green-700 mt-1">
                                    Download our XLSX template with the correct column format for uploading facilities. The template includes only the required headers.
                                </p>
                                <button
                                    @click="downloadTemplate"
                                    :disabled="isDownloadingTemplate"
                                    class="mt-3 inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-medium text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg v-if="isDownloadingTemplate" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ isDownloadingTemplate ? 'Generating...' : 'Download XLSX Template' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Required Columns</h3>
                        <p class="text-sm text-gray-600 mb-3">Your Excel file must include these columns (first row should be headers):</p>
                        <ul class="space-y-2 text-sm">
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-3"></span>
                                    <span class="font-medium">facility_name</span>
                                    <span class="text-gray-400 ml-2">(required)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-3"></span>
                                    <span class="font-medium">facility_type</span>
                                    <span class="text-gray-400 ml-2">(required)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-3"></span>
                                    <span class="font-medium">district</span>
                                    <span class="text-gray-400 ml-2">(required)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full mr-3"></span>
                                    <span class="font-medium">region</span>
                                    <span class="text-gray-400 ml-2">(optional)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full mr-3"></span>
                                    <span class="font-medium">email</span>
                                    <span class="text-gray-400 ml-2">(optional)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full mr-3"></span>
                                    <span class="font-medium">phone</span>
                                    <span class="text-gray-400 ml-2">(optional)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full mr-3"></span>
                                    <span class="font-medium">address</span>
                                    <span class="text-gray-400 ml-2">(optional)</span>
                                </li>
                            </ul>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Select File</h3>
                        <p class="text-sm text-gray-600 mb-3">Choose an Excel file (.xlsx, .xls) or CSV file to upload:</p>
                        
                        <div class="flex items-center space-x-4">
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="hidden"
                                @change="handleFileUpload"
                            />
                            <button
                                v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                                @click="triggerFileInput"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 transition ease-in-out duration-150"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Choose File
                            </button>
                            
                            <span v-if="fileInput?.files?.[0]" class="text-sm text-gray-600">
                                Selected: {{ fileInput.files[0].name }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="fileInput?.files?.[0]"
                        class="mt-4 flex items-center justify-between bg-blue-50 p-4 rounded-lg border border-blue-200"
                    >
                        <div class="flex items-center space-x-3">
                            <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-blue-900">{{ fileInput.files[0].name }}</p>
                                <p class="text-xs text-blue-700">{{ (fileInput.files[0].size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                        </div>
                        <button
                            v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                            @click="removeSelectedFile"
                            class="text-blue-500 hover:text-blue-700 transition-colors"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Upload Progress (while request is in flight) -->
                    <div v-if="isUploading" class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Importing facilities...</h4>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">{{ uploadProgress < 100 ? 'Uploading file...' : 'Processing rows...' }} {{ uploadProgress }}%</p>
                    </div>

                    <!-- Upload Results (synchronous import completed) -->
                    <div v-if="uploadResults && !isUploading" class="mb-6">
                        <div class="bg-green-50 border border-green-200 rounded-md p-4">
                            <h3 class="text-sm font-medium text-green-800">Import Results</h3>
                            <p class="text-sm text-green-700 mt-1">{{ uploadResults.message }}</p>
                            <div class="mt-2 text-xs text-gray-600">
                                <p v-if="uploadResults.imported != null">Imported: {{ uploadResults.imported }}</p>
                                <p v-if="uploadResults.skipped != null">Skipped: {{ uploadResults.skipped }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upload / validation errors -->
                    <div v-if="uploadErrors.length > 0" class="mb-6">
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <h3 class="text-sm font-medium text-red-800">Upload Errors</h3>
                            <ul class="mt-2 text-sm text-red-700 space-y-1">
                                <li v-for="(error, index) in uploadErrors" :key="index" class="flex items-start">
                                    <span class="w-2 h-2 bg-red-400 rounded-full mr-2 mt-2 flex-shrink-0"></span>
                                    {{ error }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
                    <button
                        @click="closeUploadModal"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                    >
                        Cancel
                    </button>
                    <button
                        v-if="$page.props.auth.can.facility_manage || $page.props.auth.isAdmin"
                        @click="uploadFile"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-amber-600 hover:to-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all duration-200"
                        :disabled="!fileInput?.files?.[0] || isUploading"
                    >
                        <svg v-if="isUploading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        {{ isUploading ? 'Importing...' : 'Upload & Import' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import '@/Components/multiselect.css'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Swal from 'sweetalert2'
import { useToast } from 'vue-toastification'
import { TailwindPagination } from "laravel-vue-pagination"
import moment from "moment"

// Permissions are now handled directly via $page.props.auth.can

// Toast notifications
const toast = useToast()
const isUploading = ref(false)
const uploadErrors = ref([])

// Upload modal states
const showUploadModal = ref(false)
const fileInput = ref(null)
const uploadProgress = ref(0)
const uploadResults = ref(null)
const isDownloadingTemplate = ref(false)

// Facility type creation modal
const showFacilityTypeModal = ref(false)
const newFacilityType = ref('')
const isCreatingFacilityType = ref(false)

const props = defineProps({
    facilities: {
        type: Object,
        required: true
    },
    facilityCounts: {
        type: Object,
        required: true,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0
        })
    },
    users: {
        type: Array,
        required: true
    },
    filters: {
        type: Object,
        required: true
    },
    districts: {
        type: Array,
        required: true
    },
    facilityTypes: {
        type: Array,
        required: true
    }
})

// Local, reactive list of facility types (strings)
const facilityTypes = ref([...(props.facilityTypes || [])])
const ADD_NEW_FACILITY_TYPE_OPTION = '+ Add New Facility Type'

// Computed properties for statistics - now using independent counts from controller
const activeCount = computed(() => {
    return props.facilityCounts?.active || 0
})

const inactiveCount = computed(() => {
    return props.facilityCounts?.inactive || 0
})

const totalCount = computed(() => {
    return props.facilityCounts?.total || 0
})

const per_page = ref(props.filters.per_page || 25)
const search = ref(props.filters.search)
const district = ref(props.filters.district)
const facilityType = ref(props.filters.facility_type)
const loadingProducts = ref(new Set());

const openFacilityTypeModal = () => {
    showFacilityTypeModal.value = true
}

const closeFacilityTypeModal = () => {
    if (isCreatingFacilityType.value) return
    showFacilityTypeModal.value = false
    newFacilityType.value = ''
}

async function handleFacilityTypeSelect(option) {
    if (option === ADD_NEW_FACILITY_TYPE_OPTION) {
        facilityType.value = null
        openFacilityTypeModal()
    } else {
        facilityType.value = option
    }
}

const createFacilityType = async () => {
    if (!newFacilityType.value) {
        toast.error('Please enter a facility type name')
        return
    }

    isCreatingFacilityType.value = true
    try {
        const response = await axios.post(route('products.facility-types.store'), {
            name: newFacilityType.value
        })

        const createdName = response?.data
        if (typeof createdName === 'string' && createdName.length) {
            if (!facilityTypes.value.includes(createdName)) {
                facilityTypes.value.push(createdName)
            }
            facilityType.value = createdName
        }

        toast.success('Facility type created successfully')
        closeFacilityTypeModal()
    } catch (error) {
        toast.error(error?.response?.data?.message || error?.response?.data || 'Failed to create facility type')
        console.error(error)
    } finally {
        isCreatingFacilityType.value = false
    }
}

// Handle file selection
const handleFileUpload = (event) => {
    const file = event.target.files[0]
    
    if (!file) {
        event.target.value = null
        return
    }

    // Validate file type
    const allowedTypes = ['.xlsx', '.xls', '.csv']
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase()
    
    if (!allowedTypes.includes(fileExtension)) {
        toast.error("Please select a valid file type (.xlsx, .xls, .csv)")
        event.target.value = null
        return
    }

    // Validate file size (max 50MB)
    const maxSize = 50 * 1024 * 1024 // 50MB in bytes
    if (file.size > maxSize) {
        toast.error("File is too large. Maximum file size is 50MB.")
        event.target.value = null
        return
    }

    // Clear any previous errors
    uploadErrors.value = []
}

// Upload file function
const uploadFile = async () => {
    if (!fileInput.value.files[0]) {
        toast.error('Please select a file first');
        return;
    }

    const file = fileInput.value.files[0];
    
    // Validate file type
    const allowedTypes = ['.xlsx', '.xls', '.csv'];
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
    
    if (!allowedTypes.includes(fileExtension)) {
        toast.error('Please select a valid file type (.xlsx, .xls, .csv)');
        return;
    }

    // Validate file size (max 50MB)
    const maxSize = 50 * 1024 * 1024; // 50MB in bytes
    if (file.size > maxSize) {
        toast.error('File size too large. Maximum allowed size is 50MB');
        return;
    }

    try {
        isUploading.value = true;
        uploadProgress.value = 0;
        uploadResults.value = null;
        uploadErrors.value = [];

        const formData = new FormData();
        formData.append('file', file);

        // Show loading toast (import runs synchronously; may take 10–60s for large files)
        const loadingToast = toast.info("Importing facilities... This may take a moment for large files.", {
            timeout: false,
            closeOnClick: false,
            draggable: false,
        });

        const response = await axios.post(route('facilities.import'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            timeout: 120000, // 2 minutes for large imports (e.g. 400+ rows)
            onUploadProgress: (progressEvent) => {
                uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            }
        });

        toast.dismiss(loadingToast);

        if (response.data.success) {
            uploadResults.value = {
                message: response.data.message,
                imported: response.data.imported,
                skipped: response.data.skipped,
            };
            uploadErrors.value = Array.isArray(response.data.errors) ? response.data.errors : [];

            toast.success(response.data.message);
            if (uploadErrors.value.length > 0) {
                toast.warning(`${uploadErrors.value.length} warning(s) or message(s) — see details below.`);
            }

            // Clear file input
            fileInput.value.value = '';

            // Close modal after a short delay so user can see results
            setTimeout(() => {
                closeUploadModal();
            }, 3000);
        } else {
            uploadErrors.value = [response.data.message];
            toast.error(response.data.message);
        }
    } catch (error) {
        console.error('Upload error:', error);

        if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
            uploadErrors.value = ['Import took too long. Try a smaller file or try again.'];
            toast.error('Import timed out. Try fewer rows or try again.');
        } else if (error.response?.data?.message) {
            uploadErrors.value = [error.response.data.message];
            toast.error(error.response.data.message);
        } else {
            uploadErrors.value = ['An unexpected error occurred during import.'];
            toast.error('Import failed. Please try again.');
        }
    } finally {
        isUploading.value = false;
    }
};

// Download template function
const downloadTemplate = async () => {
    if (isDownloadingTemplate.value) return; // Prevent multiple downloads
    
    try {
        isDownloadingTemplate.value = true;
        
        // Show loading state
        const loadingToast = toast.info("Generating XLSX template...", {
            timeout: false,
            closeOnClick: false,
            draggable: false,
        });
        
        // Import XLSX library dynamically
        const XLSX = await import('xlsx');
        
        // Define headers that match FacilitiesImport expectations
        const headers = ['facility_name', 'facility_type', 'district', 'region', 'email', 'phone', 'address'];
        
        // Create workbook and worksheet
        const workbook = XLSX.utils.book_new();
        const worksheet = XLSX.utils.aoa_to_sheet([headers]);
        
        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Facilities Template');
        
        // Generate XLSX file and download
        XLSX.writeFile(workbook, 'facilities_import_template.xlsx');
        
        // Dismiss loading toast and show success
        toast.dismiss(loadingToast);
        toast.success('XLSX template downloaded successfully! Open with Excel to use.');
        
    } catch (error) {
        console.error('Error generating XLSX template:', error);
        toast.error('Failed to generate template. Please try again.');
    } finally {
        isDownloadingTemplate.value = false;
    }
};

// Open upload modal
const openUploadModal = () => {
    showUploadModal.value = true;
    // Clear file input value when opening modal
    if (fileInput.value) {
        fileInput.value.value = null;
    }
    uploadErrors.value = [];
    uploadResults.value = null;
    uploadProgress.value = 0;
};

// Close upload modal
const closeUploadModal = () => {
    showUploadModal.value = false;
    // Clear file input value when closing modal
    if (fileInput.value) {
        fileInput.value.value = null;
    }
    uploadErrors.value = [];
    uploadResults.value = null;
    uploadProgress.value = 0;
};

// Trigger file input click
const triggerFileInput = () => {
    fileInput.value.click();
};

// Remove selected file
const removeSelectedFile = () => {
    // Clear the file input value
    if (fileInput.value) {
        fileInput.value.value = null;
    }
    uploadErrors.value = [];
};

// Format date for upload results
const formatDate = (date) => {
    if (!date) return "N/A";
    return moment(date).format("DD/MM/YYYY");
};

watch([
    () => per_page.value,
    () => props.filters.page,
    () => search.value,
    () => district.value,
    () => facilityType.value
], () => {
    reloadFacility();
})

const reloadFacility = () => {
    const query = {}
    if (per_page.value) query.per_page = per_page.value
    if (props.filters.page) query.page = props.filters.page
    if (search.value) query.search = search.value
    if (district.value) query.district = district.value
    if (facilityType.value) query.facility_type = facilityType.value
    
    router.get(route('facilities.index'), query, {
        preserveScroll: true,
        preserveState: true,
        only: ['facilities', 'facilityCounts', 'users', 'districts', 'facilityTypes']
    })
}

const getResults = (page) => {
   props.filters.page = page;
}

const confirmToggleStatus = (product) => {
    const action = product.is_active ? 'deactivate' : 'activate';
    
    Swal.fire({
        title: 'Are you sure?',
        html: `<p>Do you want to ${action} ${product.name}?</p>`,
        showConfirmButton: true,
        icon: undefined,
        showCancelButton: true,
        confirmButtonColor: product.is_active ? '#d33' : '#3085d6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: product.is_active ? 'Yes, deactivate!' : 'Yes, activate!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            loadingProducts.value.add(product.id);
            try {
                await axios.get(route('facilities.toggle-status', product.id));
                reloadFacility();
                Swal.fire(
                    action === 'activate' ? 'Activated!' : 'Deactivated!',
                    `Facility has been ${action}d.`,
                    'success'
                );
            } catch (error) {
                toast.error(error.response?.data || 'An error occurred');
            } finally {
                loadingProducts.value.delete(product.id);
            }
        }
    });
};
</script>

<style>
/* Custom multiselect styling */
.multiselect-professional .multiselect__tags {
    @apply border-gray-300 rounded-xl shadow-sm;
}

.multiselect-professional .multiselect__input {
    @apply text-sm;
}

.multiselect-professional .multiselect__single {
    @apply text-sm text-gray-700;
}

.multiselect-professional .multiselect__content-wrapper {
    @apply border-gray-300 rounded-lg shadow-lg;
}

.multiselect-professional .multiselect__option--highlight {
    @apply bg-blue-500;
}

.multiselect-professional .multiselect__option--selected {
    @apply bg-blue-100 text-blue-800;
}
</style>
