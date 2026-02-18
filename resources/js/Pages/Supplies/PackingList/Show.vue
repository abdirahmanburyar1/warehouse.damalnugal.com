<template>
    <AuthenticatedLayout
        title="Packing Lists"
        description="Manage and view all packing lists"
        img="/assets/images/orders.png"
    >
        <Head>
            <title>Packing Lists</title>
        </Head>

        <!-- Header Section -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg p-3">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Packing Lists</h1>
                        <p class="text-blue-100">Manage and track all packing list activities</p>
                    </div>
                </div>
                <div v-if="$page.props.auth.can.packing_list_create" class="flex items-center space-x-3">
                    <Link
                        :href="route('supplies.packing-list.create')"
                        class="inline-flex items-center px-4 py-2 bg-white text-blue-600 rounded-lg font-semibold hover:bg-blue-50 transition-colors duration-200"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        New Packing List
                    </Link>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search packing list number, supplier..."
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>

                <!-- Supplier Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                    <Multiselect
                        v-model="supplier"
                        :options="props.suppliers"
                        :searchable="true"
                        :create-option="false"
                        :show-labels="false"
                        placeholder="Select supplier"
                    ></Multiselect>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select
                        v-model="filters.status"
                        @change="props.filters.page = 1"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                    <input
                        v-model="filters.date_from"
                        type="date"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                    <input
                        v-model="filters.date_to"
                        type="date"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
            </div>
        </div>

        <!-- Packing Lists Table -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-tl-lg rounded-tr-lg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Packing Lists</h3>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <select
                            v-model="per_page"
                            @change="props.filters.page = 1"
                            class="text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-[120px] border border-gray-300 rounded px-2 py-1"
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
        <div class="mb-[80px]">

            <div class="overflow-auto">
                <table class="w-full table-sm">
                    <thead style="background-color: #F4F7FB;">
                        <tr>
                            <th class="text-left text-xs font-bold uppercase rounded-tl-lg px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Packing List
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Supplier
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Date
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Items
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Total Cost
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Fulfillment Rate
                            </th>
                            <th class="text-left text-xs font-bold uppercase px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Status
                            </th>
                            <th class="text-left text-xs font-bold uppercase rounded-tr-lg px-6 py-4" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-for="packingList in props.packingLists.data" :key="packingList.id" class="hover:bg-gray-50 border-b" style="border-bottom: 1px solid #B7C6E6;">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <Link v-if="$page.props.auth.can.packing_list_edit"
                                    :href="route('supplies.packing-list.edit', packingList.id)"
                                    class="flex flex-col text-blue-600 hover:text-blue-500"
                                >
                                    <div class="text-sm font-medium text-blue-600">{{ packingList.packing_list_number }}</div>
                                    <div class="text-sm text-blue-500">{{ packingList.ref_no }}</div>
                                </Link>
                                <Link v-else-if="$page.props.auth.can.packing_list_view"
                                    :href="route('supplies.packing-list.show', packingList.id)"
                                    class="flex flex-col text-blue-600 hover:text-blue-500"
                                >
                                    <div class="text-sm font-medium text-blue-600">{{ packingList.packing_list_number }}</div>
                                    <div class="text-sm text-blue-500">{{ packingList.ref_no }}</div>
                                </Link>
                                <span v-else class="flex flex-col">
                                    <div class="text-sm font-medium text-gray-900">{{ packingList.packing_list_number }}</div>
                                    <div class="text-sm text-gray-500">{{ packingList.ref_no }}</div>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="text-sm text-gray-900">{{ packingList.purchase_order?.supplier?.name }}</div>
                                <div class="text-sm text-gray-500">{{ packingList.purchase_order?.supplier?.contact_person }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ formatDate(packingList.pk_date) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ packingList.items?.length || 0 }} items
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${{ formatNumber(calculateTotalCost(packingList)) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ packingList.fulfillment_rate }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="getStatusClasses(packingList.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full capitalize">
                                    {{ packingList.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div v-if="$page.props.auth.can.packing_list_view" class="flex items-center space-x-2">
                                    <Link
                                        :href="route('supplies.packing-list.show', packingList.id)"
                                        class="text-blue-600 hover:text-blue-900 inline-flex items-center"
                                    >
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        View
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="props.packingLists.data.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No packing lists found</h3>
                <p class="mt-1 text-sm text-gray-500">Try adjusting your filters or create a new packing list.</p>
            </div>

            <!-- Pagination -->
            <div class="flex justify-end mt-2 p-4">
                <TailwindPagination
                :data="props.packingLists"
                :limit="2"
                @pagination-change-page="getResults"
            />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import moment from 'moment'
import { TailwindPagination } from 'laravel-vue-pagination'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import '@/Components/multiselect.css'

const props = defineProps({
    packingLists: Object,
    suppliers: Array,
    filters: Object
})

function getResults(page = 1) {
    props.filters.page = page
}

// Methods
const formatDate = (date) => {
    if (!date) return 'N/A'
    return moment(date).format('DD/MM/YYYY')
}

const formatNumber = (number) => {
    return Number(number).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const calculateTotalCost = (packingList) => {
    if (!packingList.items) return 0
    return packingList.items.reduce((total, item) => total + Number(item.total_cost || 0), 0)
}

const getStatusClasses = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-blue-100 text-blue-800',
        reviewed: 'bg-indigo-100 text-indigo-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800'
    }
    return classes[status] || 'bg-gray-100 text-gray-800'
}

const search = ref(props.filters.search);
const per_page = ref(props.filters.per_page || 25);
const supplier = ref(props.filters.supplier || '');
const status = ref(props.filters.status || '');
const date_from = ref(props.filters.date_from || '');
const date_to = ref(props.filters.date_to || '');

watch([
    () => search.value,
    () => supplier.value,
    () => status.value,
    () => date_from.value,
    () => date_to.value,
    () => per_page.value,
    () => props.filters.page
], () => {
    updateURL();
})

const updateURL = () => {
    const query = {}
    
    if (search.value) query.search = search.value
    if (supplier.value) query.supplier = supplier.value
    if (status.value) query.status = status.value
    if (date_from.value) query.date_from = date_from.value
    if (date_to.value) query.date_to = date_to.value
    if (per_page.value) query.per_page = per_page.value
    if (props.filters.page) query.page = props.filters.page

    router.get(route('supplies.packing-list.showPK'), query, {
        preserveScroll: true,
        only: ['packingLists', 'filters']
    })
}


</script>