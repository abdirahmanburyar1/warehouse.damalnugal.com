<template>
    <AuthenticatedLayout 
        title="Liquidation Details" 
        description="Manage liquidation workflow"
        img="/assets/images/orders.png"
    >
        <div v-if="props.error">
            {{ props.error }}
        </div>
        <div v-else>
            <!-- Liquidation Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <Link :href="route('liquidate-disposal.index')" class="text-blue-600 hover:text-blue-800">Back to liquidations</Link>
                        <h1 class="text-xs font-semibold text-gray-900">
                            Liquidation ID. {{ props.liquidate.liquidate_id }}
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span :class="[
                            statusClasses[props.liquidate.status] ||
                            statusClasses.default,
                        ]" class="flex items-center text-xs font-bold px-4 py-2">
                            <!-- Status Icon -->
                            <span class="mr-3">
                                <!-- Pending Icon -->
                                <img v-if="props.liquidate.status === 'pending'" src="/assets/images/pending.png"
                                    class="w-4 h-4" alt="Pending" />

                                <!-- Reviewed Icon -->
                                <img v-else-if="props.liquidate.status === 'reviewed'" src="/assets/images/review.png"
                                    class="w-4 h-4" alt="Reviewed" />

                                <!-- Approved Icon -->
                                <img v-else-if="props.liquidate.status === 'approved'" src="/assets/images/approved.png"
                                    class="w-4 h-4" alt="Approved" />

                                <!-- Rejected Icon -->
                                <img v-else-if="props.liquidate.status === 'rejected'" src="/assets/images/rejected.png"
                                class="w-4 h-4" alt="Rejected" />
                            </span>
                            {{ props.liquidate.status.toUpperCase() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                <!-- Liquidation Information -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <h2 class="text-lg font-medium text-gray-900">
                        Liquidation Details
                    </h2>
                    <div class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Liquidation ID: {{ props.liquidate.liquidate_id }}</span>
                    </div>
                    <div class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Source: {{ props.liquidate.source?.replace('_', ' ') || 'N/A' }}</span>
                    </div>
                    <div v-if="props.liquidate.liquidatedBy?.name" class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Liquidated By: {{ props.liquidate.liquidatedBy.name }}</span>
                    </div>
                    <div class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Date: {{ formatDate(props.liquidate.liquidated_at) }}</span>
                    </div>
                </div>
                
                <!-- Approval Information -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <h2 class="text-lg font-medium text-gray-900">
                        Approval Information
                    </h2>
                    <div v-if="props.liquidate.reviewedBy" class="flex items-center">
                        <svg class="h-4 w-4 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Reviewed By: {{ props.liquidate.reviewedBy.name }}</span>
                    </div>
                    <div v-if="props.liquidate.reviewed_at" class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Reviewed At: {{ formatDate(props.liquidate.reviewed_at) }}</span>
                    </div>
                    <div v-if="props.liquidate.approvedBy" class="flex items-center">
                        <svg class="h-4 w-4 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Approved By: {{ props.liquidate.approvedBy.name }}</span>
                    </div>
                    <div v-if="props.liquidate.approved_at" class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Approved At: {{ formatDate(props.liquidate.approved_at) }}</span>
                    </div>
                    <div v-if="props.liquidate.rejectedBy" class="flex items-center">
                        <svg class="h-4 w-4 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Rejected By: {{ props.liquidate.rejectedBy.name }}</span>
                    </div>
                    <div v-if="props.liquidate.rejected_at" class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Rejected At: {{ formatDate(props.liquidate.rejected_at) }}</span>
                    </div>
                    <div v-if="props.liquidate.rejection_reason" class="flex items-start">
                        <svg class="h-4 w-4 text-red-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Reason: {{ props.liquidate.rejection_reason }}</span>
                    </div>
                    <div v-if="!props.liquidate.reviewed_at && !props.liquidate.approved_at && !props.liquidate.rejected_at" class="flex items-center">
                        <svg class="h-4 w-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-sm text-gray-500">Awaiting approval process</span>
                    </div>
                </div>
                
                <!-- Summary -->
                <div class="md:col-span-2">
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        <h2 class="text-xs font-medium text-gray-900">
                            Summary
                        </h2>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-medium text-gray-500">
                                    Total Items
                                </p>
                                <p class="text-xs text-gray-900">
                                    {{ props.liquidate.items?.length || 0 }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500">
                                    Total Cost
                                </p>
                                <p class="text-xs text-gray-900">
                                    ${{ formatNumber(calculateTotalCost()) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Stage Timeline -->
            <div v-if="props.liquidate.status == 'rejected'">
                <div class="flex flex-col items-center">
                    <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10 bg-white border-red-500">
                        <img src="/assets/images/rejected.png" class="w-7 h-7" alt="Rejected" />
                    </div>
                    <h1 class="mt-3 text-2xl text-red-600 font-bold ">Rejected</h1>
                </div>
            </div>
            <div v-else class="col-span-2 mb-6">
                <div class="relative">
                    <!-- Timeline Track Background -->
                    <div class="absolute top-7 left-0 right-0 h-2 bg-gray-200 z-0"></div>

                    <!-- Timeline Progress -->
                    <div class="absolute top-7 left-0 h-2 bg-green-500 z-0 transition-all duration-500 ease-in-out"
                        :style="{
                            width: `${(statusOrder.indexOf(props.liquidate.status) /
                                (statusOrder.length - 1)) *
                                100
                                }%`,
                        }"></div>

                    <!-- Timeline Steps -->
                    <div class="relative flex justify-between">
                        <!-- Pending -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.liquidate.status) >=
                                    statusOrder.indexOf('pending')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/pending.png" class="w-7 h-7" alt="Pending" :class="statusOrder.indexOf(props.liquidate.status) >=
                                    statusOrder.indexOf('pending')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.liquidate.status) >=
                                statusOrder.indexOf('pending')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Pending</span>
                        </div>

                        <!-- Reviewed -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.liquidate.status) >=
                                    statusOrder.indexOf('reviewed')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/review.png" class="w-7 h-7" alt="Reviewed" :class="statusOrder.indexOf(props.liquidate.status) >=
                                    statusOrder.indexOf('reviewed')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.liquidate.status) >=
                                statusOrder.indexOf('reviewed')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Reviewed</span>
                        </div>

                        <!-- Approved -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.liquidate.status) >=
                                    statusOrder.indexOf('approved')
                                    ? 'bg-white border-green-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/approved.png" class="w-7 h-7" alt="Approved" :class="statusOrder.indexOf(props.liquidate.status) >=
                                    statusOrder.indexOf('approved')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.liquidate.status) >=
                                statusOrder.indexOf('approved')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Approved</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liquidation Items Table -->
            <h2 class="text-xs text-gray-900 mb-4 px-6">Liquidation Items</h2>
            <div class="overflow-auto">
                <table class="w-full table-sm">
                    <thead style="background-color: #F4F7FB;">
                        <tr>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b rounded-tl-lg"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Product
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Category
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Quantity
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                UoM
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Unit Cost
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Total Cost
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Type
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Location
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Note
                            </th>
                            <th
                                class="px-2 py-2 text-left text-xs font-bold uppercase border-b rounded-tr-lg"
                                style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;"
                            >
                                Attachments
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-if="props.liquidate.items?.length === 0">
                            <td
                                colspan="10"
                                class="px-2 py-2 text-center text-sm text-gray-600 border-b"
                                style="border-bottom: 1px solid #B7C6E6;"
                            >
                                No items found
                            </td>
                        </tr>
                        <tr
                            v-for="item in props.liquidate.items"
                            :key="item.id"
                            class="border-b"
                            :class="{
                                'hover:bg-gray-50': true
                            }"
                            style="border-bottom: 1px solid #B7C6E6;"
                        >
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                <div>
                                    <div class="font-medium">{{ item.product?.name || 'N/A' }}</div>
                                    <div class="text-gray-500">{{ item.product?.productID || 'N/A' }}</div>
                                </div>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                {{ item.product?.category?.name || 'N/A' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                {{ item.quantity }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                {{ item.uom }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                ${{ formatNumber(item.unit_cost) }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                ${{ formatNumber(item.total_cost) }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                <span class="capitalize">{{ item.type || 'N/A' }}</span>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                {{ item.location || 'N/A' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                {{ item.note || 'No note' }}
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                                <div v-if="item.attachments && item.attachments.length > 0">
                                    <!-- Single attachment -->
                                    <div v-if="item.attachments.length === 1">
                                        <a 
                                            :href="`/${item.attachments[0].path}`"
                                            target="_blank"
                                            class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-md hover:bg-blue-200 transition-colors duration-200"
                                        >
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                            View
                                        </a>
                                    </div>
                                    
                                    <!-- Multiple attachments dropdown -->
                                    <div v-else class="relative">
                                        <button 
                                            @click="toggleAttachmentDropdown(item.id)"
                                            data-dropdown-button
                                            class="inline-flex items-center px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-md hover:bg-blue-200 transition-colors duration-200"
                                        >
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                            View ({{ item.attachments.length }})
                                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        
                                        <!-- Dropdown menu -->
                                        <div 
                                            v-if="openAttachmentDropdowns[item.id]"
                                            data-dropdown-menu
                                            class="absolute z-10 mt-1 w-48 bg-white rounded-md shadow-lg border border-gray-200 py-1"
                                        >
                                            <a 
                                                v-for="attachment in item.attachments" 
                                                :key="attachment.path"
                                                :href="`/${attachment.path}`"
                                                target="_blank"
                                                class="block px-3 py-2 text-xs text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                            >
                                                <div class="flex items-center">
                                                    <svg class="w-3 h-3 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                    </svg>
                                                    {{ attachment.name }}
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <span v-else class="text-gray-400">No attachments</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Liquidation Status Actions -->
            <div class="mt-8 mb-6 bg-white rounded-lg shadow-sm mx-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">
                    Liquidation Status Actions
                </h3>
                <div class="flex items-start mb-6">
                    <!-- Status Action Buttons -->
                    <div class="flex flex-wrap items-center justify-center gap-4 px-1 py-2">
                        <!-- Review button -->
                        <div class="relative">
                            <div class="flex flex-col">
                                <button @click="changeStatus(props.liquidate.id, 'reviewed', 'is_reviewing')" 
                                    :disabled="isType['is_reviewing'] || props.liquidate.status !== 'pending' || !$page.props.auth.can.liquidation_review"
                                    :class="[
                                        props.liquidate.status === 'pending'
                                            ? 'bg-yellow-500 hover:bg-yellow-600'
                                            : ['reviewed', 'approved'].includes(props.liquidate.status)
                                            ? 'bg-green-500'
                                            : 'bg-gray-300 cursor-not-allowed',
                                    ]" 
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] disabled:opacity-60 disabled:cursor-not-allowed">
                                    <img src="/assets/images/review.png" class="w-5 h-5 mr-2" alt="Review" />
                                    <span class="text-sm font-bold text-white">{{
                                        isType["is_reviewing"]
                                            ? "Please Wait..."
                                            : ['reviewed', 'approved'].includes(props.liquidate.status)
                                            ? "Reviewed"
                                            : "Review"
                                    }}</span>
                                </button>
                                <span v-show="props.liquidate?.reviewed_at" class="text-sm text-gray-600">
                                    On {{ moment(props.liquidate?.reviewed_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.liquidate?.reviewed_by" class="text-sm text-gray-600">
                                    By {{ props.liquidate?.reviewed_by?.name }}
                                </span>
                            </div>
                            <div v-if="props.liquidate.status === 'pending'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Approved button -->
                        <div class="relative" v-if="props.liquidate.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button @click="changeStatus(props.liquidate.id, 'approved', 'is_approve')" 
                                    :disabled="isType['is_approve'] || props.liquidate.status !== 'reviewed' || !$page.props.auth.can.liquidation_approve"
                                    :class="[
                                        props.liquidate.status === 'reviewed'
                                        ? 'bg-yellow-500 hover:bg-yellow-600'
                                        : props.liquidate.status === 'approved'
                                        ? 'bg-green-500'
                                        : 'bg-gray-300 cursor-not-allowed',
                                ]" 
                                class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <svg v-if="isType['is_approve']" 
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
                                            isType["is_approve"] 
                                                ? "Please Wait..." 
                                                : props.liquidate.status === 'approved'
                                                ? "Approved"
                                                : "Approve"
                                        }}</span>
                                    </template>
                                </button>
                                <span v-show="props.liquidate?.approved_by" class="text-sm text-gray-600">
                                    On {{ moment(props.liquidate?.approved_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.liquidate?.approved_by" class="text-sm text-gray-600">
                                    By {{ props.liquidate?.approved_by?.name }}
                                </span>
                            </div>
                            <div v-if="props.liquidate.status === 'reviewed'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Rejected button -->
                        <div class="relative" v-if="props.liquidate.status !== 'rejected' && props.liquidate.status !== 'approved'">
                            <div class="flex flex-col">
                                <button @click="rejectLiquidation()" 
                                    :disabled="isType['is_reject'] || props.liquidate.status !== 'reviewed' || !$page.props.auth.can.liquidation_reject"
                                    :class="[
                                        props.liquidate.status === 'reviewed'
                                            ? 'bg-red-500 hover:bg-red-600'
                                            : 'bg-gray-300 cursor-not-allowed',
                                    ]" 
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]">
                                    <img src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Reject" />
                                    <span class="text-sm font-bold text-white">{{
                                        isType['is_reject'] ? "Please Wait..." : "Reject"
                                    }}</span>
                                </button>
                                <span v-show="props.liquidate?.rejected_at" class="text-sm text-gray-600">
                                    On {{ moment(props.liquidate?.rejected_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.liquidate?.rejected_by" class="text-sm text-gray-600">
                                    By {{ props.liquidate?.rejectedBy?.name }}
                                </span>
                            </div>
                        </div>

                        <!-- Restore button -->
                        <div class="relative" v-if="props.liquidate.status === 'rejected'">
                            <div class="flex flex-col">
                                <button @click="restoreLiquidation()" 
                                    :disabled="isType['is_restore'] || !$page.props.auth.can.liquidation_edit"
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px] bg-blue-500 hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    <span class="text-sm font-bold text-white">{{
                                        isType['is_restore'] ? "Please Wait..." : "Restore"
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import moment from 'moment';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    liquidate: Object,
    error: String,
});

// Status configuration
const statusOrder = ['pending', 'reviewed', 'approved'];
const statusClasses = {
    pending: 'bg-yellow-100 text-yellow-800',
    reviewed: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    default: 'bg-gray-100 text-gray-800'
};

// Loading states
const isLoading = ref(false);
const isType = ref({
    is_reviewing: false,
    is_approve: false,
    is_reject: false,
    is_restore: false
});

// Attachment dropdown states
const openAttachmentDropdowns = ref({});

const toggleAttachmentDropdown = (itemId) => {
    // Close all other dropdowns first
    Object.keys(openAttachmentDropdowns.value).forEach(key => {
        if (key !== itemId.toString()) {
            openAttachmentDropdowns.value[key] = false;
        }
    });
    openAttachmentDropdowns.value[itemId] = !openAttachmentDropdowns.value[itemId];
};

// Close dropdowns when clicking outside
const closeAllDropdowns = () => {
    openAttachmentDropdowns.value = {};
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return moment(date).format('DD/MM/YYYY HH:mm');
};

const formatNumber = (number) => {
    return Number(number).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

const calculateTotalCost = () => {
    if (!props.liquidate.items) return 0;
    return props.liquidate.items.reduce((total, item) => total + Number(item.total_cost || 0), 0);
};

const changeStatus = (liquidateId, newStatus, type) => {
    console.log(liquidateId, newStatus, type);
    
    Swal.fire({
        title: "Are you sure?",
        text: `Do you want to change the liquidation status to ${newStatus}?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, change it!",
    }).then(async (result) => {
        if (result.isConfirmed) {
            // Set loading state
            isType.value[type] = true;

            // Map the status to the correct route name
            const routeMap = {
                'reviewed': 'review',
                'approved': 'approve',
                'rejected': 'reject'
            };
            
            const routeName = routeMap[newStatus] || newStatus;
            const routeUrl = route(`liquidate-disposal.liquidates.${routeName}`, liquidateId);

            await axios
                .post(routeUrl, {}, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    withCredentials: true
                })
                .then((response) => {
                    // Reset loading state
                    isType.value[type] = false;

                    Swal.fire({
                        title: "Updated!",
                        text: "Liquidation status has been updated.",
                        icon: "success",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    }).then(() => {
                        // Reload the page to show the updated status
                        router.reload();
                    });
                })
                .catch((error) => {
                    // Reset loading state
                    isType.value[type] = false;

                    Swal.fire({
                        title: "Error!",
                        text:
                            error.response?.data?.message ||
                            "Failed to update liquidation status",
                        icon: "error",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    });
                });
        }
    });
};

const rejectLiquidation = () => {
    Swal.fire({
        title: 'Reject Liquidation',
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
            // Set loading state
            isType.value.is_reject = true;

            await axios
                .post(route('liquidate-disposal.liquidates.reject', props.liquidate.id), {
                    reason: result.value
                }, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    withCredentials: true
                })
                .then((response) => {
                    // Reset loading state
                    isType.value.is_reject = false;

                    Swal.fire({
                        title: "Updated!",
                        text: "Liquidation has been rejected.",
                        icon: "success",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    }).then(() => {
                        // Reload the page to show the updated status
                        router.reload();
                    });
                })
                .catch((error) => {
                    // Reset loading state
                    isType.value.is_reject = false;

                    Swal.fire({
                        title: "Error!",
                        text:
                            error.response?.data?.message ||
                            "Failed to reject liquidation",
                        icon: "error",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    });
                });
        }
    });
};

const restoreLiquidation = () => {
    Swal.fire({
        title: 'Restore Liquidation',
        text: 'Are you sure you want to restore this liquidation to pending status?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Yes, restore it!',
        cancelButtonText: 'Cancel'
    }).then(async (result) => {
        if (result.isConfirmed) {
            // Set loading state
            isType.value.is_restore = true;

            await axios
                .post(route('liquidate-disposal.liquidates.rollback', props.liquidate.id), {}, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    withCredentials: true
                })
                .then((response) => {
                    // Reset loading state
                    isType.value.is_restore = false;

                    Swal.fire({
                        title: "Updated!",
                        text: "Liquidation has been restored.",
                        icon: "success",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    }).then(() => {
                        // Reload the page to show the updated status
                        router.reload();
                    });
                })
                .catch((error) => {
                    // Reset loading state
                    isType.value.is_restore = false;

                    Swal.fire({
                        title: "Error!",
                        text:
                            error.response?.data?.message ||
                            "Failed to restore liquidation",
                        icon: "error",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    });
                });
        }
    });
};

// Lifecycle hooks for dropdown management
onMounted(() => {
    document.addEventListener('click', (event) => {
        // Check if click is outside of dropdown buttons
        const isDropdownButton = event.target.closest('[data-dropdown-button]');
        const isDropdownMenu = event.target.closest('[data-dropdown-menu]');
        
        if (!isDropdownButton && !isDropdownMenu) {
            closeAllDropdowns();
        }
    });
});

onUnmounted(() => {
    document.removeEventListener('click', closeAllDropdowns);
});
</script> 