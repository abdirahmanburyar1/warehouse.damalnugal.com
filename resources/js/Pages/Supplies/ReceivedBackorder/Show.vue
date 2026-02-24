<template>
    <Head title="Received Back Order Details" />
    <AuthenticatedLayout title="Received Back Order Details"
        description="View detailed information about received back order" img="/assets/images/supplies.png">
        
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <div class="flex items-center space-x-3 mb-2">
                        <Link 
                            :href="route('supplies.received-backorder.index')"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to List
                        </Link>
                        <h2 class="font-bold text-2xl text-gray-900 leading-tight">Received Back Order #{{ receivedBackorder.received_backorder_number }}</h2>
                    </div>
                    <p class="text-gray-600">Detailed information about this received back order</p>
                </div>
            </div>
        </div>

        <!-- Header Information Card -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Basic Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Received Back Order ID</label>
                            <p class="text-sm text-gray-900 font-semibold">#{{ receivedBackorder.received_backorder_number }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Back Order</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.back_order ? receivedBackorder.back_order.back_order_number : 'Direct Received' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.type || 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Received Date</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.received_at ? moment(receivedBackorder.received_at).format('DD/MM/YYYY HH:mm') : 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Location Information</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.warehouse ? receivedBackorder.warehouse.name : 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Facility</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.facility ? receivedBackorder.facility.name : 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Note</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.note || 'No notes' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Status Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Information</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <span 
                                :class="getStatusBadge(receivedBackorder).class"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                            >
                                <img 
                                    v-if="getStatusBadge(receivedBackorder).icon && getStatusBadge(receivedBackorder).icon.startsWith('/')" 
                                    :src="getStatusBadge(receivedBackorder).icon" 
                                    class="w-3 h-3 mr-1"
                                    alt="Status"
                                />
                                <span v-else class="mr-1">{{ getStatusBadge(receivedBackorder).icon }}</span>
                                {{ getStatusBadge(receivedBackorder).text }}
                            </span>
                        </div>
                        <div v-if="receivedBackorder.received_by">
                            <label class="block text-sm font-medium text-gray-700">Received By</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.received_by.name }}</p>
                        </div>
                        <div v-if="receivedBackorder.reviewed_by">
                            <label class="block text-sm font-medium text-gray-700">Reviewed By</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.reviewed_by.name }}</p>
                        </div>
                        <div v-if="receivedBackorder.approved_by">
                            <label class="block text-sm font-medium text-gray-700">Approved By</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.approved_by.name }}</p>
                        </div>
                        <div v-if="receivedBackorder.rejected_by">
                            <label class="block text-sm font-medium text-gray-700">Rejected By</label>
                            <p class="text-sm text-gray-900">{{ receivedBackorder.rejected_by.name }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Items ({{ receivedBackorder.items ? receivedBackorder.items.length : 0 }})</h3>
            </div>

            <div v-if="!receivedBackorder.items || receivedBackorder.items.length === 0" class="text-center py-12">
                <div class="mx-auto h-24 w-24 text-gray-400">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-full w-full">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No items found</h3>
                <p class="mt-2 text-gray-500">This received back order has no items.</p>
            </div>

            <div v-else class="overflow-auto">
                <table class="min-w-full border border-gray-300 table-fixed">
                    <colgroup>
                        <col class="w-80">
                        <col class="w-24">
                        <col class="w-28">
                        <col class="w-28">
                        <col class="w-24">
                        <col class="w-24">
                        <col class="w-32">
                    </colgroup>
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Product
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Quantity
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Batch Number
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Expiry Date
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Unit Cost
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider border-r border-gray-300">
                                Total Cost
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 capitalize tracking-wider">
                                Location
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr 
                            v-for="(item, index) in receivedBackorder.items" 
                            :key="item.id"
                            class="hover:bg-gray-50 transition-colors duration-150 border-b border-gray-300"
                        >
                            <!-- Product Information -->
                            <td class="px-4 py-3 border-r border-gray-300">
                                <div class="space-y-1">
                                    <div class="text-sm font-semibold text-gray-900 leading-tight">
                                        {{ item.product ? item.product.name : 'N/A' }}
                                    </div>
                                    <div class="space-y-0.5 text-xs text-gray-600">
                                        <div><span class="font-medium">Barcode:</span> {{ item.barcode || 'N/A' }}</div>
                                        <div><span class="font-medium">UOM:</span> {{ item.uom || 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Quantity -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-900">{{ item.quantity || 'N/A' }}</span>
                                    <span class="text-gray-500 ml-1">{{ item.uom || '' }}</span>
                                </div>
                            </td>

                            <!-- Batch Number -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ item.batch_number || 'N/A' }}
                                </div>
                            </td>

                            <!-- Expiry Date -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ item.expire_date ? moment(item.expire_date).format('DD/MM/YYYY') : 'N/A' }}
                                </div>
                            </td>

                            <!-- Unit Cost -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ item.unit_cost ? `$${parseFloat(item.unit_cost).toFixed(2)}` : 'N/A' }}
                                </div>
                            </td>

                            <!-- Total Cost -->
                            <td class="px-4 py-3 whitespace-nowrap border-r border-gray-300">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ item.total_cost ? `$${parseFloat(item.total_cost).toFixed(2)}` : 'N/A' }}
                                </div>
                            </td>

                            <!-- Location -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ item.location || 'N/A' }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Attachments Section -->
        <div v-if="parseAttachments(receivedBackorder.attachments).length > 0" class="bg-white border border-gray-200 rounded-lg p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Attachments</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                    v-for="(file, fileIndex) in parseAttachments(receivedBackorder.attachments)" 
                    :key="fileIndex"
                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200"
                >
                    <div class="flex items-center space-x-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900 truncate">{{ file.name }}</p>
                        </div>
                    </div>
                    <a 
                        :href="file.url" 
                        target="_blank"
                        class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150"
                    >
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        View
                    </a>
                </div>
            </div>
        </div>

        <!-- Status Action Buttons -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
            <div class="flex flex-wrap items-center justify-center gap-4 px-1 py-2">
                <!-- Review button -->
                <div class="relative">
                    <div class="flex flex-col">
                        <button 
                            @click="reviewReceivedBackorder()" 
                            :disabled="isType.is_reviewing || receivedBackorder.status !== 'pending' || !$page.props.auth.can.received_backorder_review"
                            :class="[
                                receivedBackorder.status === 'pending'
                                    ? 'bg-yellow-500 hover:bg-yellow-600'
                                    : ['reviewed', 'approved'].includes(receivedBackorder.status)
                                    ? 'bg-green-500'
                                    : 'bg-gray-300 cursor-not-allowed',
                            ]" 
                            class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <img src="/assets/images/review.png" class="w-5 h-5 mr-2" alt="Review" />
                            <span class="text-sm font-bold text-white">{{
                                isType.is_reviewing
                                    ? "Please Wait..."
                                    : ['reviewed', 'approved'].includes(receivedBackorder.status)
                                    ? "Reviewed"
                                    : "Review"
                            }}</span>
                        </button>
                        <span v-show="receivedBackorder?.reviewed_at" class="text-sm text-gray-600">
                            On {{ moment(receivedBackorder?.reviewed_at).format('DD/MM/YYYY HH:mm') }}
                        </span>
                        <span v-show="receivedBackorder?.reviewed_by" class="text-sm text-gray-600">
                            By {{ receivedBackorder?.reviewed_by?.name }}
                        </span>
                    </div>
                    <div v-if="receivedBackorder.status === 'pending'"
                        class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                </div>

                <!-- Approve button -->
                <div class="relative" v-if="receivedBackorder.status !== 'rejected'">
                    <div class="flex flex-col">
                        <button 
                            @click="approveReceivedBackorder()" 
                            :disabled="isType.is_approve || receivedBackorder.status !== 'reviewed' || !$page.props.auth.can.received_backorder_approve"
                            :class="[
                                receivedBackorder.status === 'reviewed'
                                ? 'bg-yellow-500 hover:bg-yellow-600'
                                : receivedBackorder.status === 'approved'
                                ? 'bg-green-500'
                                : 'bg-gray-300 cursor-not-allowed',
                        ]" 
                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <svg v-if="isType.is_approve" 
                                class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4">
                                </circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <template v-else>
                                <img src="/assets/images/approved.png" class="w-5 h-5 mr-2" alt="Approve" />
                                <span class="text-sm font-bold text-white">{{
                                    isType.is_approve 
                                        ? "Please Wait..." 
                                        : receivedBackorder.status === 'approved'
                                        ? "Approved"
                                        : "Approve"
                                }}</span>
                            </template>
                        </button>
                        <span v-show="receivedBackorder?.approved_by" class="text-sm text-gray-600">
                            On {{ moment(receivedBackorder?.approved_at).format('DD/MM/YYYY HH:mm') }}
                        </span>
                        <span v-show="receivedBackorder?.approved_by" class="text-sm text-gray-600">
                            By {{ receivedBackorder?.approved_by?.name }}
                        </span>
                    </div>
                    <div v-if="receivedBackorder.status === 'reviewed'"
                        class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                </div>

                <!-- Reject button -->
                <div class="relative" v-if="receivedBackorder.status === 'reviewed'">
                    <div class="flex flex-col">
                        <button 
                            @click="rejectReceivedBackorder()" 
                            :disabled="isType.is_reject || !$page.props.auth.can.received_backorder_reject"
                            class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] bg-red-500 hover:bg-red-600 disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <img src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Reject" />
                            <span class="text-sm font-bold text-white">{{
                                isType.is_reject ? "Please Wait..." : "Reject"
                            }}</span>
                        </button>
                        <span v-show="receivedBackorder?.rejected_at" class="text-sm text-gray-600">
                            On {{ moment(receivedBackorder?.rejected_at).format('DD/MM/YYYY HH:mm') }}
                        </span>
                        <span v-show="receivedBackorder?.rejected_by" class="text-sm text-gray-600">
                            By {{ receivedBackorder?.rejected_by?.name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import moment from 'moment';
import Swal from 'sweetalert2';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    receivedBackorder: Object,
});

// Loading states for action buttons
const isType = ref({
    is_reviewing: false,
    is_approve: false,
    is_reject: false
});

const getStatusBadge = (receivedBackorder) => {
    if (receivedBackorder.approved_at) {
        return { class: 'bg-green-100 text-green-800 border-green-200', text: 'Approved', icon: '/assets/images/approve.png' };
    } else if (receivedBackorder.rejected_at) {
        return { class: 'bg-red-100 text-red-800 border-red-200', text: 'Rejected', icon: '/assets/images/rejected.png' };
    } else if (receivedBackorder.reviewed_at) {
        return { class: 'bg-blue-100 text-blue-800 border-blue-200', text: 'Reviewed', icon: '/assets/images/review.png' };
    } else {
        return { class: 'bg-yellow-100 text-yellow-800 border-yellow-200', text: 'Pending', icon: '⏳' };
    }
};

const parseAttachments = (attachments) => {
    if (!attachments) {
        return [];
    }
    const files = typeof attachments === 'string' ? JSON.parse(attachments) : attachments;
    return files.map(file => ({
        name: file.name || file.path.split('/').pop(),
        url: `${file.path}`
    }));
};

// Action functions
const reviewReceivedBackorder = () => {
    Swal.fire({
        title: 'Review Received Back Order?',
        text: 'Are you sure you want to review this received back order?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3B82F6',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, review it!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            isType.value.is_reviewing = true;
            await axios.post(route('supplies.received-backorder.review', props.receivedBackorder.id))
                .then((response) => {
                    isType.value.is_reviewing = false;
                    Swal.fire({
                        title: 'Success!',
                        text: 'Received back order reviewed successfully',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        router.get(route('supplies.received-backorder.show', props.receivedBackorder.id), {}, {
                            preserveState: true, 
                            preserveScroll: true, 
                            only: []
                        });
                    });
                })
                .catch((error) => {
                    isType.value.is_reviewing = false;
                    console.error('Error reviewing received back order:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: error.response?.data?.message || 'An error occurred while reviewing the received back order',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        }
    });
};

const approveReceivedBackorder = () => {
    Swal.fire({
        title: 'Approve Received Back Order?',
        text: 'Are you sure you want to approve this received back order?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, approve it!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            isType.value.is_approve = true;
            await axios.post(route('supplies.received-backorder.approve', props.receivedBackorder.id))
                .then((response) => {
                    isType.value.is_approve = false;
                    Swal.fire({
                        title: 'Success!',
                        text: 'Received back order approved successfully',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        router.get(route('supplies.received-backorder.show', props.receivedBackorder.id), {}, {
                            preserveState: true, 
                            preserveScroll: true, 
                            only: []
                        });
                    });
                })
                .catch((error) => {
                    isType.value.is_approve = false;
                    console.error('Error approving received back order:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: error.response?.data?.message || 'An error occurred while approving the received back order',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
        }
    });
};

const rejectReceivedBackorder = () => {
    Swal.fire({
        title: 'Reject Received Back Order',
        icon: 'warning',
        html: '<div class="mb-3 flex flex-col"><label class="form-label">Reason for rejection</label><textarea id="rejection-reason" class="form-control" rows="3" placeholder="Enter your reason here..."></textarea></div>',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Reject',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const reason = document.getElementById('rejection-reason').value;
            if (!reason.trim()) {
                Swal.showValidationMessage('Please provide a reason for rejection');
                return false;
            }
            return reason;
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then(async (result) => {
        if (result.isConfirmed && result.value) {
            isType.value.is_reject = true;
            await axios.post(route('supplies.received-backorder.reject', props.receivedBackorder.id), {
                rejection_reason: result.value
            })
            .then((response) => {
                isType.value.is_reject = false;
                Swal.fire({
                    title: 'Success!',
                    text: 'Received back order rejected successfully',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    router.get(route('supplies.received-backorder.show', props.receivedBackorder.id), {}, {
                        preserveState: true, 
                        preserveScroll: true, 
                        only: []
                    });
                });
            })
            .catch((error) => {
                isType.value.is_reject = false;
                console.error('Error rejecting received back order:', error);
                Swal.fire({
                    title: 'Error!',
                    text: error.response?.data?.message || 'Failed to reject received back order',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }
    });
};
</script> 