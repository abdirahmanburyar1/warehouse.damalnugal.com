<template>
    <Head title="Purchase Orders" />
    <AuthenticatedLayout title="Manage Your Purchase Orders"
        description="Ensuring an Optimal Flow of Essential Resources" img="/assets/images/supplies.png">
        <div class="mb-[80px]">
            <div class="text-gray-900">
                <!-- Action Buttons Row -->
                <div class="flex flex-wrap items-center justify-end gap-3 mb-8">
                    <div class="relative inline-block text-left z-20" ref="backOrderDropdownRef">
                        <button type="button"
                            class="inline-flex items-center px-4 py-2.5 bg-blue-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 shadow-sm"
                            id="back-order-menu" :aria-expanded="showBackOrderDropdown" aria-haspopup="true"
                            @click.stop="toggleBackOrderDropdown">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Back Orders
                        </button>
                        <transition enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95">
                            <div v-if="showBackOrderDropdown"
                                class="origin-top-right absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200"
                                role="menu" aria-orientation="vertical" aria-labelledby="back-order-menu">
                                <div class="py-1" role="none">
                                    <a @click="router.visit(route('supplies.back-order'))"
                                        class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 cursor-pointer transition-colors duration-150"
                                        role="menuitem">
                                        Back Order
                                    </a>
                                    <a @click="router.visit(route('supplies.showBackOrder'))"
                                        class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 cursor-pointer transition-colors duration-150"
                                        role="menuitem">
                                        View Back Order
                                    </a>
                                    <a v-if="$page.props.auth.can.received_backorder_view"
                                        @click="router.visit(route('supplies.received-backorder.index'))"
                                        class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 cursor-pointer transition-colors duration-150"
                                        role="menuitem">
                                        Received Back Order
                                    </a>
                                </div>
                            </div>
                        </transition>
                    </div>
                    <div v-if="$page.props.auth.can.packing_list_view || $page.props.auth.can.packing_list_create" class="relative inline-block text-left z-20" ref="supplyDropdownRef">
                        <button type="button"
                            class="inline-flex items-center px-4 py-2.5 bg-purple-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors duration-200 shadow-sm"
                            id="supply-menu" :aria-expanded="showSupplyDropdown" aria-haspopup="true"
                            @click.stop="toggleSupplyDropdown">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Supplies
                        </button>
                        <transition enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95">
                            <div v-if="showSupplyDropdown"
                                class="origin-top-right absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200"
                                role="menu" aria-orientation="vertical" aria-labelledby="supply-menu">
                                <div class="py-1" role="none">
                                    <a v-if="$page.props.auth.can.packing_list_create" @click="router.get(route('supplies.packing-list'))"
                                        class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 cursor-pointer transition-colors duration-150"
                                        role="menuitem">
                                        Receive New PL
                                    </a>
                                    <a v-if="$page.props.auth.can.packing_list_view" @click="router.get(route('supplies.packing-list.showPK'))"
                                        class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 cursor-pointer transition-colors duration-150"
                                        role="menuitem">
                                        View PL Lists
                                    </a>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <button v-if="$page.props.auth.can.purchase_order_create" @click="router.get(route('supplies.purchase_order'))"
                        class="inline-flex items-center px-4 py-2.5 bg-orange-600 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors duration-200 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create New Purchase Order
                    </button>
                    <div class="relative inline-block text-left z-20" ref="dropdownRef">
                        <button type="button"
                            class="inline-flex items-center px-4 py-2.5 bg-gray-800 text-white hover:bg-gray-700 transition-colors rounded-lg font-medium text-sm shadow-sm"
                            id="options-menu" :aria-expanded="showDropdown" aria-haspopup="true"
                            @click.stop="toggleDropdown">
                            <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                            List of Suppliers
                        </button>
                        <transition enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95">
                            <div v-if="showDropdown"
                                class="origin-top-right absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200"
                                role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
                                <div class="py-1" role="none">
                                    <a @click="navigateToCreateSupplier"
                                        class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 cursor-pointer transition-colors duration-150"
                                        role="menuitem">
                                        Create Supplier
                                    </a>
                                    <a href="#" @click="navigateToViewSupplier"
                                        class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150"
                                        role="menuitem">
                                        View Suppliers
                                    </a>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <!-- Search and Filter Row -->
                <div class="mb-3">
                    <div class="">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <input type="text" v-model="search" placeholder="Search by PO number, supplier"
                                        class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm" />
                                </div>
                            </div>
                            <div>
                                <label for="supplier" class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                                <Multiselect
                                    v-model="supplier"
                                    :options="props.suppliers"
                                    :searchable="true"
                                    :close-on-select="true"
                                    :show-labels="false"
                                    :allow-empty="true"
                                    placeholder="Select Supplier"
                                    class="text-sm"
                                />
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select v-model="status"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm">
                                    <option value="">Filter by Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="reviewed">Reviewed</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Supply Received Card -->
                    <div class="relative overflow-hidden bg-white rounded-2xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 group">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 bg-gradient-to-br from-amber-50 to-orange-50 opacity-50"></div>
                        
                        <!-- Content -->
                        <div class="relative p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="w-2 h-2 bg-amber-500 rounded-full mr-3"></div>
                                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Supply Received</p>
                                    </div>
                                    <p class="text-3xl font-bold text-gray-900">{{ stats.total_items }}</p>
                                </div>
                                
                                <!-- Icon Container -->
                                <div class="relative">
                                    <div class="w-14 h-14 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                    </div>
                                    <!-- Decorative Element -->
                                    <div class="absolute -top-2 -right-2 w-5 h-5 bg-amber-200 rounded-full opacity-60"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bottom Border -->
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
                    </div>

                    <!-- Cost of Supplies Card -->
                    <div class="relative overflow-hidden bg-white rounded-2xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 group">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-teal-50 opacity-50"></div>
                        
                        <!-- Content -->
                        <div class="relative p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <div class="w-2 h-2 bg-emerald-500 rounded-full mr-3"></div>
                                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Cost of Supplies</p>
                                    </div>
                                    <p class="text-3xl font-bold text-gray-900">{{ formatCurrency(stats.total_cost) }}</p>
                                </div>
                                
                                <!-- Icon Container -->
                                <div class="relative">
                                    <div class="w-14 h-14 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <!-- Decorative Element -->
                                    <div class="absolute -top-2 -right-2 w-5 h-5 bg-emerald-200 rounded-full opacity-60"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bottom Border -->
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 to-teal-500"></div>
                    </div>
                </div>



                <!-- Purchase Orders Table and Statistics Row -->
                <div class="mb-6">
                    <div class="grid grid-cols-12 gap-6">
                        <!-- Table Column (10/12) -->
                        <div class="col-span-12 lg:col-span-10">
                            <!-- Icon Legend Button and Per Page Selector -->
                            <div class="flex items-center justify-end gap-4 mb-4">
                                <div class="w-48">
                                    <select v-model="per_page" @change="props.filters.page = 1"
                                        class="w-full px-3 py-2.5 border border-gray-300 rounded-3xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-sm">
                                        <option value="10">10 per page</option>
                                        <option value="25">25 per page</option>
                                        <option value="50">50 per page</option>
                                        <option value="100">100 per page</option>
                                    </select>
                                </div>
                                <button @click="showIconLegend = true"
                                    class="inline-flex items-center justify-center p-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                    title="Icon Legend">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-[#EFF6FF]">
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-[#979ECD] uppercase tracking-wider border-b border-gray-300 rounded-tl-3xl">
                                                SN#
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-[#979ECD] uppercase tracking-wider border-b border-gray-300">
                                                PO Number
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-[#979ECD] uppercase tracking-wider border-b border-gray-300">
                                                Supplier
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-[#979ECD] uppercase tracking-wider border-b border-gray-300">
                                                P.O Date
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-[#979ECD] uppercase tracking-wider border-b border-gray-300">
                                                Total Amount
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-[#979ECD] uppercase tracking-wider border-b border-gray-300">
                                                Status
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-semibold text-[#979ECD] uppercase tracking-wider border-b border-gray-300 rounded-tr-3xl">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        <tr v-for="(po, i) in props.purchaseOrders.data" :key="po.id" class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-b border-gray-300">
                                                {{ i + 1 }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-b border-gray-300">
                                                <Link :href="route('supplies.editPO', po.id)"
                                                    class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                    {{ po.po_number }}
                                                </Link>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 border-b border-gray-300">
                                                {{ po.supplier?.name || 'No supplier' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 border-b border-gray-300">
                                                {{ moment(po.po_date).format('DD/MM/YYYY') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 border-b border-gray-300">
                                                {{ formatCurrency(po.items_sum_total_cost || 0) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm border-b border-gray-300">
                                                <div class="flex items-center space-x-2">
                                                    <!-- Completed: show completed only (not workflow icons) -->
                                                    <template v-if="(po.status || '').toLowerCase() === 'completed'">
                                                        <svg class="w-6 h-6 text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span class="text-gray-700 font-medium">Completed</span>
                                                    </template>
                                                    <!-- Original workflow: pending → reviewed → approved/rejected -->
                                                    <template v-else>
                                                        <!-- Pending icon -->
                                                        <img src="/assets/images/pending.png" class="w-6 h-6" alt="Pending" />
                                                        <img src="/assets/images/review.png" class="w-8 h-8" v-if="po.status === 'reviewed' || po.status === 'approved' || po.status === 'rejected'" alt="Review" />
                                                        <img v-if="po.status === 'approved'" src="/assets/images/approved.png" class="w-6 h-6" alt="Approved" />
                                                        <svg v-if="po.status === 'rejected'" class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium border-b border-gray-300">
                                                <div class="flex items-center space-x-3">
                                                    <button @click="router.visit(route('supplies.po-show', po.id))"
                                                        class="text-gray-600 hover:text-gray-900 transition-colors duration-200 p-1 rounded-md hover:bg-gray-100"
                                                        title="View Purchase Order">
                                                        <EyeIcon class="h-4 w-4" />
                                                    </button>
                                                    <button v-if="(po.status || '').toLowerCase() !== 'approved' && (po.status || '').toLowerCase() !== 'completed'"
                                                        @click="router.visit(route('supplies.editPO', po.id))"
                                                        class="text-blue-600 hover:text-blue-900 transition-colors duration-200 p-1 rounded-md hover:bg-blue-50"
                                                        title="Edit Purchase Order">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                            viewBox="0 0 20 20" fill="currentColor">
                                                            <path
                                                                d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                                            <path fill-rule="evenodd"
                                                                d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    <button v-if="(po.status || '').toLowerCase() !== 'approved' && (po.status || '').toLowerCase() !== 'completed'"
                                                        @click="confirmDelete(po.id)"
                                                        class="text-red-600 hover:text-red-900 transition-colors duration-200 p-1 rounded-md hover:bg-red-50"
                                                        title="Delete Purchase Order">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!purchaseOrders?.data?.length">
                                            <td colspan="7" class="px-6 py-12 text-center border-b border-gray-300">
                                                <div class="text-center">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                    </svg>
                                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No purchase orders</h3>
                                                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new purchase order.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-6">
                                <TailwindPagination
                                    :data="props.purchaseOrders"
                                    @pagination-change-page="getResults"
                                    :limit="2"
                                />
                            </div>
                        </div>

                        <!-- Statistics Column (2/12) -->
                        <div class="col-span-12 lg:col-span-2">
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-4">
                                <h3 class="text-sm font-semibold text-gray-900 mb-6">PO Statistics</h3>
                                <div class="space-y-8">
                                    <!-- Pending -->
                                    <div class="relative">
                                        <div class="flex items-center mb-3">
                                            <div class="w-16 h-16 relative mr-4">
                                                <svg class="w-16 h-16 transform -rotate-90">
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#e2e8f0"
                                                        stroke-width="4" />
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#eab308"
                                                        stroke-width="4"
                                                        :stroke-dasharray="`${(stats.pending_orders / (stats.total_orders || 1)) * 175.9} 175.9`" />
                                                </svg>
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="text-sm font-bold text-yellow-600">{{
                                                        stats.total_orders > 0 ?
                                                            Math.round((stats.pending_orders / stats.total_orders) * 100) :
                                                        0 }}%</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold text-gray-900">{{ stats.pending_orders ||
                                                    0 }}</div>
                                                <div class="text-sm text-gray-600">Pending</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reviewed -->
                                    <div class="relative">
                                        <div class="flex items-center mb-3">
                                            <div class="w-16 h-16 relative mr-4">
                                                <svg class="w-16 h-16 transform -rotate-90">
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#e2e8f0"
                                                        stroke-width="4" />
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#3b82f6"
                                                        stroke-width="4"
                                                        :stroke-dasharray="`${(stats.reviewed_orders / (stats.total_orders || 1)) * 175.9} 175.9`" />
                                                </svg>
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="text-sm font-bold text-blue-600">{{
                                                        stats.total_orders > 0 ?
                                                            Math.round((stats.reviewed_orders / stats.total_orders) * 100) :
                                                        0 }}%</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold text-gray-900">{{ stats.reviewed_orders ||
                                                    0 }}</div>
                                                <div class="text-sm text-gray-600">Reviewed</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Approved -->
                                    <div class="relative">
                                        <div class="flex items-center mb-3">
                                            <div class="w-16 h-16 relative mr-4">
                                                <svg class="w-16 h-16 transform -rotate-90">
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#e2e8f0"
                                                        stroke-width="4" />
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#22c55e"
                                                        stroke-width="4"
                                                        :stroke-dasharray="`${(stats.approved_orders / (stats.total_orders || 1)) * 175.9} 175.9`" />
                                                </svg>
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="text-sm font-bold text-green-600">{{
                                                        stats.total_orders > 0 ?
                                                            Math.round((stats.approved_orders / stats.total_orders) * 100) :
                                                        0 }}%</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold text-gray-900">{{ stats.approved_orders ||
                                                    0 }}</div>
                                                <div class="text-sm text-gray-600">Approved</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Rejected -->
                                    <div class="relative">
                                        <div class="flex items-center mb-3">
                                            <div class="w-16 h-16 relative mr-4">
                                                <svg class="w-16 h-16 transform -rotate-90">
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#e2e8f0"
                                                        stroke-width="4" />
                                                    <circle cx="32" cy="32" r="28" fill="none" stroke="#ef4444"
                                                        stroke-width="4"
                                                        :stroke-dasharray="`${(stats.rejected_orders / (stats.total_orders || 1)) * 175.9} 175.9`" />
                                                </svg>
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="text-sm font-bold text-red-600">{{ stats.total_orders
                                                        > 0 ?
                                                        Math.round((stats.rejected_orders / stats.total_orders) * 100) :
                                                        0 }}%</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold text-gray-900">{{ stats.rejected_orders ||
                                                    0 }}</div>
                                                <div class="text-sm text-gray-600">Rejected</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Icon Legend Slideover -->
                <TransitionRoot as="template" :show="showIconLegend">
                    <Dialog as="div" class="relative z-50" @close="showIconLegend = false">
                        <TransitionChild as="template" enter="ease-in-out duration-500" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in-out duration-500" leave-from="opacity-100" leave-to="opacity-0">
                            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                        </TransitionChild>

                        <div class="fixed inset-0 overflow-hidden">
                            <div class="absolute inset-0 overflow-hidden">
                                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                                    <TransitionChild as="template" enter="transform transition ease-in-out duration-500 sm:duration-700" enter-from="translate-x-full" enter-to="translate-x-0" leave="transform transition ease-in-out duration-500 sm:duration-700" leave-from="translate-x-0" leave-to="translate-x-full">
                                        <DialogPanel class="pointer-events-auto w-screen max-w-md">
                                            <div class="flex h-full flex-col overflow-y-scroll bg-white py-6 shadow-xl">
                                                <div class="px-4 sm:px-6">
                                                    <div class="flex items-start justify-between">
                                                        <DialogTitle class="text-lg font-semibold text-gray-900">Icon Legend</DialogTitle>
                                                        <div class="ml-3 flex h-7 items-center">
                                                            <button type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="showIconLegend = false">
                                                                <span class="sr-only">Close panel</span>
                                                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="relative mt-6 flex-1 px-4 sm:px-6">
                                                    <div class="space-y-6">
                                                        <!-- Status Icons -->
                                                        <div>
                                                            <h3 class="text-sm font-medium text-gray-900 mb-4">Purchase Order Status</h3>
                                                            <div class="space-y-4">
                                                                <div class="flex items-center space-x-3">
                                                                    <img src="/assets/images/pending.png" class="w-6 h-6" alt="Pending" />
                                                                    <div>
                                                                        <p class="text-sm font-medium text-gray-900">Pending</p>
                                                                        <p class="text-xs text-gray-500">Order is waiting for review</p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center space-x-3">
                                                                    <img src="/assets/images/review.png" class="w-8 h-8" alt="Reviewed" />
                                                                    <div>
                                                                        <p class="text-sm font-medium text-gray-900">Reviewed</p>
                                                                        <p class="text-xs text-gray-500">Order has been reviewed</p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center space-x-3">
                                                                    <img src="/assets/images/approved.png" class="w-6 h-6" alt="Approved" />
                                                                    <div>
                                                                        <p class="text-sm font-medium text-gray-900">Approved</p>
                                                                        <p class="text-xs text-gray-500">Order has been approved</p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center space-x-3">
                                                                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                    <div>
                                                                        <p class="text-sm font-medium text-gray-900">Rejected</p>
                                                                        <p class="text-xs text-gray-500">Order has been rejected</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Action Icons -->
                                                        <div>
                                                            <h3 class="text-sm font-medium text-gray-900 mb-4">Actions</h3>
                                                            <div class="space-y-4">
                                                                <div class="flex items-center space-x-3">
                                                                    <EyeIcon class="h-4 w-4 text-gray-600" />
                                                                    <div>
                                                                        <p class="text-sm font-medium text-gray-900">View</p>
                                                                        <p class="text-xs text-gray-500">View purchase order details</p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center space-x-3">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                                                        <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                                                                    </svg>
                                                                    <div>
                                                                        <p class="text-sm font-medium text-gray-900">Edit</p>
                                                                        <p class="text-xs text-gray-500">Edit purchase order</p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center space-x-3">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                    <div>
                                                                        <p class="text-sm font-medium text-gray-900">Delete</p>
                                                                        <p class="text-xs text-gray-500">Delete purchase order</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </DialogPanel>
                                    </TransitionChild>
                                </div>
                            </div>
                        </div>
                    </Dialog>
                </TransitionRoot>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, watch, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import Swal from 'sweetalert2';
import axios from 'axios';
import { TailwindPagination } from 'laravel-vue-pagination';
import moment from 'moment';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { EyeIcon } from '@heroicons/vue/24/outline';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";

const toast = useToast();
const dropdownRef = ref(null);
const showDropdown = ref(false);
const showSupplyDropdown = ref(false);
const supplyDropdownRef = ref(null);
const backOrderDropdownRef = ref(null);
const showBackOrderDropdown = ref(false);
const showIconLegend = ref(false);

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
    showSupplyDropdown.value = false;
    showBackOrderDropdown.value = false;
};

const toggleSupplyDropdown = () => {
    showSupplyDropdown.value = !showSupplyDropdown.value;
    showDropdown.value = false;
    showBackOrderDropdown.value = false;
};

const toggleBackOrderDropdown = () => {
    showBackOrderDropdown.value = !showBackOrderDropdown.value;
    showDropdown.value = false;
    showSupplyDropdown.value = false;
};

onMounted(() => {
    document.addEventListener('click', (e) => {
        if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
            showDropdown.value = false;
        }
        if (supplyDropdownRef.value && !supplyDropdownRef.value.contains(e.target)) {
            showSupplyDropdown.value = false;
        }
        if (backOrderDropdownRef.value && !backOrderDropdownRef.value.contains(e.target)) {
            showBackOrderDropdown.value = false;
        }
    });
});

function getResults(page = 1){
    props.filters.page = page;
}

const props = defineProps({
    purchaseOrders: {
        type: Object,
        required: true,
    },
    filters: Object,
    suppliers: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            total_items: 0,
            total_cost: 0,
            lead_times: {
                max: '0 Months',
                min: '0 Months',
                avg: '0 Months'
            },
            pending_orders: 0,
            reviewed_orders: 0,
            approved_orders: 0,
            rejected_orders: 0,
            total_orders: 0,
            back_orders: 0,
        })
    },
    suppliers: {
        required: true,
        type: Array
    }
});

const search = ref(props.filters?.search || '');
const supplier = ref(props.filters?.supplier || '')
const status = ref(props.filters?.status || '')
const per_page = ref(props.filters?.per_page || 25)

function formatCurrency(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value);
}

function reloadPO() {
    const query = {}
    if (search.value) query.search = search.value;
    if (supplier.value) query.supplier = supplier.value;
    if (status.value) query.status = status.value;
    if (per_page.value) {
        query.per_page = per_page.value;
    }
    if(props.filters.page) query.page = props.filters.page;
    router.get(route('supplies.index'), query, {
        preserveScroll: false,
        preserveState: false,
        only: [
            'purchaseOrders', 'filters'
        ]
    })
}

const confirmDelete = async (id) => {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        await axios.get(route('supplies.deletePO', id))
            .then((response) => {
                toast.success(response.data);
                reloadPO();
            })
            .catch((error) => {
                console.log(error);
                toast.error(error.response.data)
            });
    }
};

// Calculate order statistics based on purchaseOrders status
const orderStats = computed(() => {
    const orders = props.purchaseOrders?.data || [];
    const pending = orders.filter(order => order.status?.toLowerCase() === 'pending').length;
    const reviewed = orders.filter(order => order.status?.toLowerCase() === 'reviewed').length;
    const approved = orders.filter(order => order.status?.toLowerCase() === 'approved').length;
    const rejected = orders.filter(order => order.status?.toLowerCase() === 'rejected').length;
    const total = orders.length;

    return {
        pending_orders: pending,
        reviewed_orders: reviewed,
        approved_orders: approved,
        rejected_orders: rejected,
        total_orders: total
    };
});

const stats = computed(() => {
    return {
        ...props.stats || {
            total_items: 0,
            total_cost: 0,
            lead_times: {
                max: '0 Months',
                min: '0 Months',
                avg: '0 Months'
            },
            back_orders: 0
        },
        ...orderStats.value
    };
});


const navigateToCreateSupplier = () => {
    router.get(route('supplies.create'));
}

const navigateToViewSupplier = () => {
    router.get(route('supplies.show'));
}

watch([
    () => search.value,
    () => supplier.value,
    () => status.value,
    () => status.per_page,
    () => props.filters.page
], () => {
    reloadPO();
});
</script>