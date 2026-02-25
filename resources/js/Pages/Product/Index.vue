<template>
    <AuthenticatedLayout title="Products" description="Manage and track all products" img="/assets/images/products.png">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                </div>
                <div class="flex items-center space-x-3">
                    <button
                        v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                        @click="openUploadModal"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
                        :disabled="isUploading"
                    >
                        <svg v-if="!isUploading" class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <svg v-else class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isUploading ? 'Uploading...' : 'Upload Excel' }}
                    </button>

                    <Link
                        v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                        :href="route('products.create')"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 shadow-sm"
                    >
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create Product
                    </Link>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('products.categories.index')"
                    class="inline-flex items-center px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg class="h-4 w-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Categories
                </Link>
                <Link
                    :href="route('products.dosages.index')"
                    class="inline-flex items-center px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg class="h-4 w-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Dosage Forms
                </Link>
                <Link
                    :href="route('products.eligible.index')"
                    class="inline-flex items-center px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg class="h-4 w-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Eligible Items
                </Link>
                <Link
                    :href="route('products.uom.index')"
                    class="inline-flex items-center px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg class="h-4 w-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 0h10m-10 0a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2"></path>
                    </svg>
                    UOM
                </Link>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <!-- Search -->
                <div class="lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name"
                            class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                        />
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <Multiselect
                        v-model="category"
                        :options="props.categories"
                        :searchable="true"
                        :close-on-select="true"
                        :show-labels="false"
                        :allow-empty="true"
                        placeholder="Select Category"
                        class="text-sm"
                    />
                </div>

                <!-- Dosage Filter -->
                <div class="lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Dosage Form</label>
                    <Multiselect
                        v-model="dosage"
                        :options="props.dosages"
                        :searchable="true"
                        :close-on-select="true"
                        :show-labels="false"
                        :allow-empty="true"
                        placeholder="Select Dosage Form"
                        class="text-sm"
                    />
                </div>

                <!-- Status Filter -->
                <div class="lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select
                        v-model="status"
                        class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                    >
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Eligible Filter -->
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Eligibility Level</label>
                    <Multiselect
                        v-model="eligible"
                        :options="['All', ...props.eligibleItems]"
                        :searchable="true"
                        :close-on-select="true"
                        :show-labels="false"
                        :allow-empty="true"
                        placeholder="Select Eligibility"
                        class="text-sm"
                    />
                </div>

                <!-- Per Page Filter -->
                <div class="md:col-span-1 flex justify-end items-end">
                    <div class="w-48">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
                        <select
                            v-model="perPage"
                            @change="props.filters.page = 1"
                            class="block w-full px-3 py-2.5 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                        >
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <!-- Empty State -->
            <div
                v-if="!products.data.length"
                class="flex flex-col items-center justify-center py-16"
            >
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Products Found</h3>
                <p class="text-gray-500 text-center mb-6 max-w-md">
                    There are no products matching your search criteria. Try adjusting your filters or add a new product.
                </p>
                <Link
                    :href="route('products.create')"
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200"
                >
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add New Product
                </Link>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full overflow-hidden text-sm text-left table-sm rounded-t-lg">
                    <thead>
                        <tr style="background-color: #F4F7FB;">
                            <th class="w-[50px] px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">No.</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Item</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Category</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Dosage Form</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Eligibility Level</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Tracertable</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Supply Class</th>
                            <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Status</th>
                            <th class="px-3 py-2 text-xs font-bold text-right" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(product, index) in products.data"
                            :key="product.id"
                            class="hover:bg-gray-50 transition-colors duration-150 border-b"
                            style="border-bottom: 1px solid #B7C6E6;"
                        >
                            <td class="px-3 py-2 w-[50px] max-w-[50px] text-center">
                                {{ index + 1 }}
                            </td>
                            <td class="px-3 py-2 w-[200px] max-w-[200px]">
                                <div class="text-xs font-medium text-gray-800 capitalize">
                                    {{ product.name }}
                                </div>
                                <div class="text-xs text-gray-500 capitalize">
                                    ID: {{ product.productID || 'N/A' }}
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ product.category?.name || "N/A" }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ product.dosage?.name || "N/A" }}
                                </span>
                            </td>                            
                            <td class="px-3 py-2 text-xs text-gray-800">
                                <div v-if="product.eligible && product.eligible.length > 0" class="space-y-1">
                                    <span v-for="(item, index) in product.eligible" :key="index" class="inline-block px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-md mr-1 mb-1">
                                        {{ item.facility_type }}
                                    </span>
                                </div>
                                <span v-else class="text-gray-500">N/A</span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ product.tracert_type }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-800">
                                {{ product.supply_class || '—' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                    :class="{
                                        'bg-green-100 text-green-800': product.is_active,
                                        'bg-red-100 text-red-800': !product.is_active,
                                    }"
                                >
                                    <span class="w-2 h-2 rounded-full mr-1.5" :class="{
                                        'bg-green-400': product.is_active,
                                        'bg-red-400': !product.is_active,
                                    }"></span>
                                    {{ product.is_active ? "Active" : "Inactive" }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-center">
                                <div class="flex items-end justify-end space-x-2">
                                    <Link
                                        v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                                        :href="route('products.edit', product.id)"
                                        class="inline-flex items-center p-1.5 border border-transparent rounded-lg text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200"
                                        title="Edit Product"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </Link>
                                    <button
                                        v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                                        @click="confirmToggleStatus(product)"
                                        :disabled="loadingProducts.has(product.id)"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        :class="{
                                            'opacity-50 cursor-not-allowed': loadingProducts.has(product.id),
                                            'bg-red-500': !product.is_active,
                                            'bg-green-500': product.is_active,
                                        }"
                                        :title="product.is_active ? 'Deactivate Product' : 'Activate Product'"
                                    >
                                        <span
                                            :class="{
                                                'translate-x-5': product.is_active,
                                                'translate-x-0': !product.is_active,
                                                'bg-gray-400 animate-pulse': loadingProducts.has(product.id),
                                            }"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        ></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
           <div class="flex justify-between items-center p-3">
            <div>
                <p class="text-sm text-gray-500">
                    Showing {{ products.meta.from }} to {{ products.meta.to }} of {{ products.meta.total }} products
                </p>
            </div>
            <div v-if="products.data.length" class="flex justify-end mb-4 bg-white px-6 py-4 border-t border-gray-200">
                <TailwindPagination
                    :data="props.products"
                    :limit="2"
                    @pagination-change-page="getResults"
                />
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
                        <h3 class="text-lg font-semibold text-gray-900">Upload Products</h3>
                        <p class="text-sm text-gray-500 mt-1">Import products from Excel file</p>
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
                                    Download our template to see the correct format for uploading products.
                                </p>
                                <button
                                    v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                                    @click="downloadTemplate"
                                    class="mt-3 inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-medium text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Download Template
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Required Columns</h4>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-indigo-500 rounded-full mr-3"></span>
                                    <span class="font-medium">item description</span>
                                    <span class="text-gray-400 ml-2">(required)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full mr-3"></span>
                                    <span class="font-medium">category</span>
                                    <span class="text-gray-400 ml-2">(optional)</span>
                                </li>
                                <li class="flex items-center">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full mr-3"></span>
                                    <span class="font-medium">dosage form</span>
                                    <span class="text-gray-400 ml-2">(optional)</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-6">
                        <div
                            v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                            class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:bg-gray-50 transition-colors cursor-pointer"
                            @click="triggerFileInput"
                        >
                            <input
                                type="file"
                                ref="fileInput"
                                class="hidden"
                                @change="handleFileUpload"
                                accept=".xlsx,.xls,.csv"
                            />
                            <svg class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="text-lg font-medium text-gray-900 mb-2">
                                {{ selectedFile ? 'File Selected' : 'Choose File' }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ selectedFile ? selectedFile.name : 'Click to select or drag and drop file here' }}
                            </p>
                            <p class="text-xs text-gray-400 mt-2">
                                Supports .xlsx, .xls, and .csv files (max 5MB)
                            </p>
                        </div>

                        <div
                            v-if="selectedFile"
                            class="mt-4 flex items-center justify-between bg-blue-50 p-4 rounded-lg border border-blue-200"
                        >
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-blue-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-blue-900">{{ selectedFile.name }}</p>
                                    <p class="text-xs text-blue-700">{{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB</p>
                                </div>
                            </div>
                            <button
                                v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                                @click.stop="removeSelectedFile"
                                class="text-red-500 hover:text-red-700 transition-colors duration-200"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
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
                        v-if="$page.props.auth.can.product_manage || $page.props.auth.isAdmin"
                        @click="uploadFile"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200"
                        :disabled="!selectedFile || isUploading"
                    >
                        <svg v-if="isUploading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        {{ isUploading ? 'Uploading...' : 'Upload File' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { defineProps, ref, h, watch, onMounted } from "vue";
import { Link, router } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import { useToast } from "vue-toastification";
import axios from "axios";
import { TailwindPagination } from "laravel-vue-pagination";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";

const toast = useToast();

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    dosages: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    eligibleItems: {
        type: Array,
        required: true,
    },
});

const eligible = ref(props.filters.eligible || "");

const search = ref(props.filters.search || "");
const category = ref(props.filters.category || "");
const dosage = ref(props.filters.dosage || "");
const perPage = ref(props.filters.per_page || 25);
const status = ref(props.filters.status || 1);
const fileInput = ref(null);
const showUploadModal = ref(false);
const loadingProducts = ref(new Set());
const selectedFile = ref(null);
const isUploading = ref(false);

function updateRoute() {
    const query = {};
    if (search.value) query.search = search.value;
    if (category.value) query.category = category.value;
    if (dosage.value) query.dosage = dosage.value;
    if (perPage.value) query.per_page = perPage.value;
    if (status.value) query.status = status.value;
    if (eligible.value) query.eligible = eligible.value;
    if (props.filters.page) query.page = props.filters.page;

    router.get(route("products.index"), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ["products"],
    });
}

watch(
    [
        () => search.value,
        () => category.value,
        () => dosage.value,
        () => perPage.value,
        () => status.value,
        () => eligible.value,
        () => props.filters.page,
    ],
    () => {
        updateRoute();
    }
);

function getResults(page = 1) {
    props.filters.page = page;
}

const openUploadModal = () => {
    showUploadModal.value = true;
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    selectedFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = null;
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Check file type - allow .xlsx and .csv
    const fileExtension = "." + file.name.split(".").pop().toLowerCase();
    const validExtensions = [".xlsx", ".csv"];

    if (!validExtensions.includes(fileExtension)) {
        toast.error(
            "Invalid file type. Please upload an Excel file (.xlsx) or CSV file (.csv)"
        );
        event.target.value = null; // Clear the file input
        selectedFile.value = null;
        return;
    }

    // Check file size (max 5MB)
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        toast.error("File is too large. Maximum file size is 5MB.");
        event.target.value = null;
        selectedFile.value = null;
        return;
    }

    selectedFile.value = file;
};

const removeSelectedFile = () => {
    selectedFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = null;
    }
};

const importId = ref(null);

onMounted(() => {
    Echo.private(`import-progress.${importId}`)
        .listen('.ImportProgressUpdated', (e) => {
            console.log(e);
        });
});

const uploadFile = async () => {
    if (!selectedFile.value) {
        toast.error("Please select a file to upload");
        return;
    }

    // Show loading toast
    const loadingToast = toast.info("Preparing to upload file...", {
        timeout: false,
        closeOnClick: false,
        draggable: false,
    });

    isUploading.value = true;
    const formData = new FormData();
    formData.append("file", selectedFile.value);

    await axios.post(
        route("products.import-excel"),
        formData,
    )
    .then(response => {
        isUploading.value = false;
        importId.value = response.data.import_id;
        console.log('Upload response:', response.data);
    })
    .catch(error => {
        isUploading.value = false;
        console.error('Upload error:', error);
        closeUploadModal();
        toast.dismiss(loadingToast);
        toast.error(error.response.data.message || "Failed to start import");
    });

};

const confirmToggleStatus = (product) => {
    const action = product.is_active ? "deactivate" : "activate";

    Swal.fire({
        title: "Are you sure?",
        html: `<p>Do you want to ${action} ${product.name}?</p>`,
        showConfirmButton: true,
        icon: undefined,
        showCancelButton: true,
        confirmButtonColor: product.is_active ? "#d33" : "#3085d6",
        cancelButtonColor: "#6b7280",
        confirmButtonText: product.is_active
            ? "Yes, deactivate!"
            : "Yes, activate!",
    }).then(async (result) => {
        if (result.isConfirmed) {
            loadingProducts.value.add(product.id);
            try {
                await axios.get(route("products.toggle-status", product.id));
                updateRoute();
                Swal.fire(
                    action === "activate" ? "Activated!" : "Deactivated!",
                    `Product has been ${action}d.`,
                    "success"
                );
            } catch (error) {
                toast.error(error.response?.data || "An error occurred");
            } finally {
                loadingProducts.value.delete(product.id);
            }
        }
    });
};

// Download template function
const downloadTemplate = () => {
    // Create a proper Excel file using HTML table format that Excel can interpret
    const headers = ['item description', 'category', 'dosage form'];
    
    // Create an HTML table that Excel can open as XLSX
    const htmlContent = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8">
            <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Sheet1</x:Name><x:WorksheetSource HRef="sheet001.htm"/></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
        </head>
        <body>
            <table>
                <tr>
                    ${headers.map(header => `<th>${header}</th>`).join('')}
                </tr>
            </table>
        </body>
        </html>
    `;
    
    // Create blob with proper Excel MIME type
    const blob = new Blob([htmlContent], { 
        type: 'application/vnd.ms-excel' 
    });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'products_import_template.xlsx');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    toast.success('Empty template downloaded successfully!');
};

</script>
