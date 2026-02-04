<template>
    <AuthenticatedLayout title="Track Your Orders" description="Keeping Essentials Ready, Every Time"
        img="/assets/images/orders.png"> <!-- Order Header -->
        <div v-if="props.error">
            {{ props.error }}
        </div>
        <div v-else>
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <Link :href="route('orders.index')">Back to orders</Link>
                        <h1 class="text-xs font-semibold text-gray-900">
                            Order ID. {{ props.order.order_number }}
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span :class="[
                            statusClasses[props.order.status] ||
                            statusClasses.default,
                        ]" class="flex items-center text-xs font-bold px-4 py-2">
                            <!-- Status Icon -->
                            <span class="mr-3">
                                <!-- Pending Icon -->
                                <img v-if="props.order.status === 'pending'" src="/assets/images/pending.png"
                                    class="w-4 h-4" alt="Pending" />

                                <!-- reviewed Icon -->
                                <img v-else-if="props.order.status === 'reviewed'" src="/assets/images/reviewed.png"
                                    class="w-4 h-4" alt="Reviewed" />

                                <!-- Approved Icon -->
                                <img v-else-if="props.order.status === 'approved'" src="/assets/images/approved.png"
                                    class="w-4 h-4" alt="Approved" />

                                <!-- In Process Icon -->
                                <img v-else-if="props.order.status === 'in_process'" src="/assets/images/inprocess.png"
                                    class="w-4 h-4" alt="In Process" />

                                <!-- Dispatched Icon -->
                                <img v-else-if="props.order.status === 'dispatched'" src="/assets/images/dispatch.png"
                                    class="w-4 h-4" alt="Dispatched" />

                                <!-- Received Icon -->
                                <img v-else-if="props.order.status === 'received'" src="/assets/images/received.png"
                                    class="w-4 h-4" alt="Received" />

                                <!-- Rejected Icon -->
                                <img v-else-if="props.order.status === 'rejected'" src="/assets/images/rejected.png"
                                class="w-4 h-4" alt="Rejected" />
                            </span>
                            {{ props.order.status.toUpperCase() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                <!-- Facility Information -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <h2 class="text-lg font-medium text-gray-900">
                        Facility Details
                    </h2>
                    <div class="flex items-center">
                        <BuildingOfficeIcon class="h-4 w-4 text-gray-400 mr-2" />
                        <span class="text-sm text-gray-600">{{
                            props.order.facility.name
                        }}</span>
                    </div>
                    <div class="flex items-center">
                        <EnvelopeIcon class="h-4 w-4 text-gray-400 mr-2" />
                        <span class="text-sm text-gray-600">{{
                            props.order.facility.email
                        }}</span>
                    </div>
                    <div class="flex items-center">
                        <PhoneIcon class="h-4 w-4 text-gray-400 mr-2" />
                        <span class="text-sm text-gray-600">{{
                            props.order.facility.phone
                        }}</span>
                    </div>
                    <div class="flex items-center">
                        <MapPinIcon class="h-4 w-4 text-gray-400 mr-2" />
                        <span class="text-sm text-gray-600">{{ props.order.facility.address }},
                            {{ props.order.facility.city }}</span>
                    </div>
                </div>
                <div>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        <h2 class="text-xs font-medium text-gray-900">
                            Order Details
                        </h2>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs font-medium text-gray-500">
                                    Order Type
                                </p>
                                <p class="text-xs text-gray-900">
                                    {{ props.order.order_type }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500">
                                    Order Date
                                </p>
                                <p class="text-xs text-gray-900">
                                    {{ formatDate(props.order.order_date) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500">
                                    Expected Date
                                </p>
                                <p class="text-xs text-gray-900">
                                    {{ formatDate(props.order.expected_date) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Stage Timeline -->
             <div v-if="props.order.status == 'rejected'">
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
                            width: `${(statusOrder.indexOf(props.order.status) /
                                (statusOrder.length - 1)) *
                                100
                                }%`,
                        }"></div>

                    <!-- Timeline Steps -->
                    <div class="relative flex justify-between">
                        <!-- Pending -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('pending')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/pending.png" class="w-7 h-7" alt="Pending" :class="statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('pending')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.order.status) >=
                                statusOrder.indexOf('pending')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Pending</span>
                        </div>

                        <!-- Reviewed -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('reviewed')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/review.png" class="w-7 h-7" alt="Reviewed" :class="statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('reviewed')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.order.status) >=
                                statusOrder.indexOf('reviewed')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Reviewed</span>
                        </div>

                        <!-- Approved -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('approved')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/approved.png" class="w-7 h-7" alt="Approved" :class="statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('approved')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.order.status) >=
                                statusOrder.indexOf('approved')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Approved</span>
                        </div>

                        <!-- In Process -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('in_process')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/inprocess.png" class="w-7 h-7" alt="In Process" :class="statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('in_process')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.order.status) >=
                                statusOrder.indexOf('in_process')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">In Process</span>
                        </div>

                        <!-- Dispatch -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('dispatched')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/dispatch.png" class="w-7 h-7" alt="Dispatch" :class="statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('dispatched')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.order.status) >=
                                statusOrder.indexOf('dispatched')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Dispatched</span>
                        </div>

                        <!-- Delivered -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('delivered')
                                    ? 'bg-white border-orange-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/delivery.png" class="w-7 h-7" alt="Dispatch" :class="statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('delivered')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.order.status) >=
                                statusOrder.indexOf('delivered')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Delivered</span>
                        </div>

                        <!-- Received -->
                        <div class="flex flex-col items-center">
                            <div class="w-14 h-14 rounded-full border-4 flex items-center justify-center z-10" :class="[
                                statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('received')
                                    ? 'bg-white border-green-500'
                                    : 'bg-white border-gray-200',
                            ]">
                                <img src="/assets/images/received.png" class="w-7 h-7" alt="Received" :class="statusOrder.indexOf(props.order.status) >=
                                    statusOrder.indexOf('received')
                                    ? ''
                                    : 'opacity-40'
                                    " />
                            </div>
                            <span class="mt-3 text-xs font-bold" :class="statusOrder.indexOf(props.order.status) >=
                                statusOrder.indexOf('received')
                                ? 'text-green-600'
                                : 'text-gray-500'
                                ">Received</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items Table -->
            <h2 class="text-xs text-gray-900 mb-4">Order Items</h2>
            <table class="w-full overflow-hidden text-sm text-left table-sm rounded-t-lg">
                <thead>
                    <tr style="background-color: #F4F7FB;">
                        <th class="px-3 py-2 text-xs font-bold rounded-tl-lg" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">Item</th>
                        <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">Category</th>
                        <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">UoM</th>
                        <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">Facility Inventory Data</th>
                        <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">No. of Days</th>
                        <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">Ordered Quantity</th>
                        <th class="px-3 py-2 text-xs font-bold" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" rowspan="2">Quantity to release</th>
                        <th class="px-3 py-2 text-xs font-bold text-center" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;" colspan="4">Item Detail</th>
                    </tr>
                    <tr style="background-color: #F4F7FB;">
                        <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center" style="color: #4F6FCB;">QTY</th>
                        <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center" style="color: #4F6FCB;">Batch Number</th>
                        <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center" style="color: #4F6FCB;">Expiry Date</th>
                        <th class="px-2 py-1 text-xs font-bold border border-[#B7C6E6] text-center" style="color: #4F6FCB;">Location</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="form.length === 0">
                        <td colspan="11" class="px-3 py-3 text-center text-gray-500 border-b" style="border-bottom: 1px solid #B7C6E6;">
                            No items found. Form length: {{ form.length }}, Order items: {{ props.order.items?.length || 0 }}
                        </td>
                    </tr>
                    <template v-for="(item, index) in form" :key="item.id">
                        <template v-if="item.inventory_allocations?.length > 0">
                            <tr v-for="(inv, invIndex) in item.inventory_allocations" :key="`${item.id}-${inv.id || invIndex}`"
                                class="hover:bg-gray-50 transition-colors duration-150 border-b"
                                :style="'border-bottom: 1px solid #F4F7FB;'">
                                <td v-if="invIndex === 0" :rowspan="item.inventory_allocations.length"
                                    class="px-3 py-3 text-xs text-gray-900 align-top">
                                    {{ item.product?.name }}
                                </td>
                                <td v-if="invIndex === 0" :rowspan="item.inventory_allocations.length"
                                    class="px-3 py-3 text-xs text-gray-900 align-top">
                                    {{ item.product?.category?.name }}
                                </td>
                                <td v-if="invIndex === 0" :rowspan="item.inventory_allocations.length"
                                    class="px-3 py-3 text-xs text-gray-900 align-top">
                                    {{ item.inventory_allocations?.[0]?.uom || item.uom || 'N/A' }}
                                </td>
                                <td v-if="invIndex === 0" :rowspan="item.inventory_allocations.length"
                                    class="px-3 py-3 text-xs text-gray-900 align-top">
                                    <div class="flex flex-col space-y-1 text-xs">
                                        <div class="flex">
                                            <span class="font-medium text-xs w-12">SOH:</span>
                                            <span>{{ item.soh }}</span>
                                        </div>
                                        <div class="flex">
                                            <span class="font-medium text-xs w-12">AMC:</span>
                                            <span>{{ item.amc || 0 }}</span>
                                        </div>
                                        <div class="flex">
                                            <span class="font-medium text-xs w-12">QOO:</span>
                                            <span>{{ item.quantity_on_order }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td v-if="invIndex === 0" :rowspan="item.inventory_allocations.length"
                                    class="px-3 py-3 text-xs text-gray-900 align-top">
                                    {{ item.no_of_days }}
                                </td>
                                <td v-if="invIndex === 0" :rowspan="item.inventory_allocations.length"
                                    class="px-3 py-3 text-xs text-center text-black align-top">
                                    {{ item.quantity }}
                                </td>
                                <td v-if="invIndex === 0" :rowspan="item.inventory_allocations.length"
                                    class="px-3 py-3 text-xs text-gray-900 align-top" style="width: 200px;">
                                    <input type="number" placeholder="0" v-model="item.quantity_to_release" @input="
                                        handleQuantityInput(item, 'quantity_to_release', index)" :readonly="props.order.status != 'pending'"
                                        class="w-full rounded-md border border-gray-300 focus:border-orange-500 focus:ring-orange-500 sm:text-sm mb-1" />
                                    <label for="days">No. of Days</label>
                                    <input type="number" min="1" :value="item.no_of_days ?? 1"
                                        @input="(e) => { item.no_of_days = Math.max(1, parseInt(e.target.value) || 1); handleQuantityInput(item, 'days', index); }"
                                        :readonly="props.order.status != 'pending'"
                                        class="w-full rounded-md border border-gray-300 focus:border-orange-500 focus:ring-orange-500 sm:text-sm mb-1" />
                                    <span v-if="isUpading[index]" class="text-green-500 text-md">Updating...</span>
                                    <button @click="showBackOrderModal(item)" v-if="
                                        item.inventory_allocations &&
                                        item.inventory_allocations.some(
                                            (a) => a.back_order?.length > 0
                                        )
                                    " class="text-xs text-orange-600 underline hover:text-orange-800 cursor-pointer mt-1">
                                        Show Back Order
                                    </button>
                                </td>
                                <!-- Item Details Columns -->
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ inv.allocated_quantity || '' }}
                                </td>
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ inv.batch_number || '' }}
                                </td>
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                    {{ inv.expiry_date ? moment(inv.expiry_date).format('DD/MM/YYYY') : '' }}
                                </td>
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;">
                                    <div class="flex flex-col text-xs">{{ inv.location }}</div>
                                </td>
                            </tr>
                        </template>
                        <template v-else>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 border-b" style="border-bottom: 1px solid #F4F7FB;">
                                <td class="px-3 py-3 text-xs text-gray-900 align-top">{{ item.product?.name }}</td>
                                <td class="px-3 py-3 text-xs text-gray-900 align-top">{{ item.product?.category?.name }}</td>
                                <td class="px-3 py-3 text-xs text-gray-900 align-top">{{ item.uom || 'N/A' }}</td>
                                <td class="px-3 py-3 text-xs text-gray-900 align-top">
                                    <div class="flex flex-col space-y-1 text-xs">
                                        <div class="flex">
                                            <span class="font-medium text-xs w-12">SOH:</span>
                                            <span>{{ item.soh }}</span>
                                        </div>
                                        <div class="flex">
                                            <span class="font-medium text-xs w-12">AMC:</span>
                                            <span>{{ item.amc || 0 }}</span>
                                        </div>
                                        <div class="flex">
                                            <span class="font-medium text-xs w-12">QOO:</span>
                                            <span>{{ item.quantity_on_order }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-900 align-top">{{ item.no_of_days }}</td>
                                <td class="px-3 py-3 text-xs text-center text-black align-top">{{ item.quantity }}</td>
                                <td class="px-3 py-3 text-xs text-gray-900 align-top" style="width: 200px;">
                                    <input type="number" placeholder="0" v-model="item.quantity_to_release" @input="
                                        handleQuantityInput(item, 'quantity_to_release', index)" :readonly="props.order.status != 'pending'"
                                        class="w-full rounded-md border border-gray-300 focus:border-orange-500 focus:ring-orange-500 sm:text-sm mb-1" />
                                    <label for="days">No. of Days</label>
                                    <input type="number" min="1" :value="item.no_of_days ?? 1"
                                        @input="(e) => { item.no_of_days = Math.max(1, parseInt(e.target.value) || 1); handleQuantityInput(item, 'days', index); }"
                                        :readonly="props.order.status != 'pending'"
                                        class="w-full rounded-md border border-gray-300 focus:border-orange-500 focus:ring-orange-500 sm:text-sm mb-1" />
                                    <span v-if="isUpading[index]" class="text-green-500 text-md">Updating...</span>
                                    <button @click="showBackOrderModal(item)" v-if="
                                        item.back_order && item.back_order.length > 0
                                    " class="text-xs text-orange-600 underline hover:text-orange-800 cursor-pointer mt-1">
                                        Show Back Order
                                    </button>
                                </td>
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;">{{ item.allocated_quantity || '' }}</td>
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;">{{ item.batch_number || '' }}</td>
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;">{{ item.expiry_date ? moment(item.expiry_date).format('DD/MM/YYYY') : '' }}</td>
                                <td class="px-2 py-1 text-xs border-b text-center" style="border-bottom: 1px solid #B7C6E6;"><div class="flex flex-col text-xs">{{ item.location }}</div></td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>

            <!-- dispatch information -->
            <div v-if="props.order.dispatch?.length > 0" class="mt-8 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">
                        Dispatch Note (Driver Handover Log)
                    </h2>
                </div>

                <div class="bg-white rounded-lg shadow-lg divide-y divide-gray-200">
                    <div v-for="dispatch in props.order.dispatch" :key="dispatch.id" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Driver & Company Info -->
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">Driver Information</h3>
                                    <div class="mt-2 space-y-2">
                                        <div class="flex items-center">
                                            <UserIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-900">{{ dispatch.driver.name }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <IdentificationIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">ID: {{ dispatch.driver.driver_id }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <PhoneIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">{{ dispatch.driver_number }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">Logistics Company</h3>
                                    <div class="mt-2 space-y-2">
                                        <div class="flex items-center">
                                            <BuildingOfficeIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-900">{{ dispatch.logistic_company.name }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <EnvelopeIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">{{ dispatch.logistic_company.email }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <UserIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">Contact: {{ dispatch.logistic_company.incharge_person }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <PhoneIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">{{ dispatch.logistic_company.incharge_phone }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dispatch Details -->
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">Dispatch Details</h3>
                                    <div class="mt-2 space-y-2">
                                        <div class="flex items-center">
                                            <CalendarIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-900">{{ moment(dispatch.dispatch_date).format('MMMM D, YYYY') }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <TruckIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">Vehicle Plate: {{ dispatch.plate_number }}</span>
                                        </div>
                                        <div class="flex items-center">
                                            <ArchiveBoxIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">{{dispatch.received_cartons}}/{{ dispatch.no_of_cartoons }} Cartons</span>
                                        </div>
                                        <div class="flex items-center">
                                            <ClockIcon class="w-4 h-4 text-gray-400 mr-2" />
                                            <span class="text-sm text-gray-600">Dispatched on {{ moment(dispatch.created_at).format('MMMM D, YYYY h:mm A') }}</span>
                                        </div>
                                        <!-- View Images Button -->
                                        <div v-if="dispatch.image" class="mt-3">
                                            <button
                                                @click="viewDispatchImages(dispatch)"
                                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                            >
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                View Images
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dispatch Images Modal -->
            <Modal :show="showDispatchImagesModal" @close="closeDispatchImagesModal">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium text-gray-900">Dispatch Images</h2>
                        <button @click="closeDispatchImagesModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div v-if="dispatchImages.length > 0" class="space-y-4">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div
                                v-for="(image, index) in dispatchImages"
                                :key="index"
                                class="relative group cursor-pointer"
                                @click="openImageLightbox(index)"
                            >
                                <img
                                    :src="getImageUrl(image)"
                                    :alt="`Dispatch image ${index + 1}`"
                                    class="w-full h-32 object-cover rounded-lg shadow-sm hover:shadow-md transition-shadow"
                                    @error="handleImageError"
                                />
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-opacity rounded-lg flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-gray-500">No images available for this dispatch</p>
                    </div>
                </div>
            </Modal>

            <!-- Image Lightbox Modal -->
            <Modal :show="showImageLightbox" @close="closeImageLightbox">
                <div class="relative bg-black">
                    <div class="flex items-center justify-between p-4 bg-black bg-opacity-75">
                        <h3 class="text-white font-medium">
                            Image {{ currentImageIndex + 1 }} of {{ dispatchImages.length }}
                        </h3>
                        <button @click="closeImageLightbox" class="text-white hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="relative">
                        <img
                            v-if="dispatchImages[currentImageIndex]"
                            :src="getImageUrl(dispatchImages[currentImageIndex])"
                            :alt="`Dispatch image ${currentImageIndex + 1}`"
                            class="w-full max-h-screen object-contain"
                            @error="handleImageError"
                        />
                        
                        <!-- Navigation arrows -->
                        <button
                            v-if="dispatchImages.length > 1 && currentImageIndex > 0"
                            @click="previousImage"
                            class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-75 transition-all"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        
                        <button
                            v-if="dispatchImages.length > 1 && currentImageIndex < dispatchImages.length - 1"
                            @click="nextImage"
                            class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-75 transition-all"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Image counter dots -->
                    <div v-if="dispatchImages.length > 1" class="flex justify-center space-x-2 p-4 bg-black bg-opacity-75">
                        <button
                            v-for="(image, index) in dispatchImages"
                            :key="index"
                            @click="currentImageIndex = index"
                            :class="[
                                'w-2 h-2 rounded-full transition-all',
                                index === currentImageIndex ? 'bg-white' : 'bg-gray-500 hover:bg-gray-400'
                            ]"
                        />
                    </div>
                </div>
            </Modal>

            <!-- Order Status Actions -->
            <div class="mt-8 mb-6 bg-white rounded-lg shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">
                    Order Status Actions
                </h3>
                <div class="flex items-start mb-6">
                    <!-- Status Action Buttons -->
                    <div class="flex flex-wrap items-center justify-center gap-4 px-1 py-2">
                        <!-- Pending status indicator removed by the Dr Mutax -->
                        <!-- Review button -->
                        <div class="relative">
                            <div class="flex flex-col">
                                <button @click="
                                    changeStatus(
                                        props.order.id,
                                        'reviewed',
                                        'is_reviewing'
                                    )
                                    " :disabled="isType['is_reviewing'] ||
                                        props.order.status !== 'pending' ||
                                        (!$page.props.auth.can.order_review && !$page.props.auth.can.order_manage)
                                        " :class="[
                                    (!$page.props.auth.can.order_review && !$page.props.auth.can.order_manage)
                                        ? 'bg-red-200 text-red-800 cursor-not-allowed opacity-75'
                                        : props.order.status === 'pending'
                                        ? 'bg-yellow-500 hover:bg-yellow-600'
                                        : statusOrder.indexOf(props.order.status) >
                                            statusOrder.indexOf('pending')
                                            ? 'bg-green-500'
                                            : 'bg-gray-300 cursor-not-allowed',
                                ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 min-w-[160px]">
                                    <img src="/assets/images/review.png" class="w-5 h-5 mr-2" alt="Review" />
                                    <span class="text-sm font-bold text-white">{{
                                        statusOrder.indexOf(props.order.status) >
                                            statusOrder.indexOf("pending")
                                            ? "Reviewed"
                                            : isType["is_reviewing"]
                                                ? "Please Wait..."
                                                : props.order.status == 'rejected' ? "Reviewed" : "Review"
                                    }}</span>
                                </button>
                                <span v-show="props.order?.reviewed_at" class="text-sm text-gray-600">
                                    On {{ moment(props.order?.reviewed_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.order?.reviewed_by" class="text-sm text-gray-600">
                                    By {{ props.order?.reviewed_by?.name }}
                                </span>
                            </div>
                            <div v-if="props.order.status === 'pending'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Approved button -->
                        <div class="relative" v-if="props.order.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button @click="
                                    changeStatus(
                                        props.order.id,
                                        'approved',
                                        'is_approve'
                                    )
                                    " :disabled="isType['is_approve'] ||
                                        props.order.status !== 'reviewed' ||
                                        (!$page.props.auth.can.order_approve && !$page.props.auth.can.order_manage)
                                        " :class="[
                                        (!$page.props.auth.can.order_approve && !$page.props.auth.can.order_manage)
                                        ? 'bg-red-200 text-red-800 cursor-not-allowed opacity-75'
                                        : props.order.status == 'reviewed'
                                        ? 'bg-yellow-500 hover:bg-yellow-600'
                                        : statusOrder.indexOf(props.order.status) >
                                            statusOrder.indexOf('reviewed')
                                            ? 'bg-green-500'
                                            : 'bg-gray-300 cursor-not-allowed',
                                ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 min-w-[160px]">
                                    <svg v-if="
                                        isLoading &&
                                        props.order.status === 'reviewed'
                                    " class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
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
                                            statusOrder.indexOf(props.order.status) >
                                                statusOrder.indexOf("reviewed")
                                                ? "Approved"
                                                : isType["is_approve"] ? "Please Wait..." : "Approve"
                                        }}</span>
                                    </template>
                                </button>
                                <span v-show="props.order?.approved_by" class="text-sm text-gray-600">
                                    On {{ moment(props.order?.approved_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.order?.approved_by" class="text-sm text-gray-600">
                                    By {{ props.order?.approved_by?.name }}
                                </span>
                            </div>
                            <div v-if="props.order.status === 'reviewed'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Process button -->
                        <div class="relative" v-if="props.order.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button @click="
                                    changeStatus(
                                        props.order.id,
                                        'in_process',
                                        'is_process'
                                    )
                                    " :disabled="isType['is_process'] ||
                                        props.order.status !== 'approved' ||
                                        (!$page.props.auth.can.order_processing && !$page.props.auth.can.order_manage)
                                        " :class="[
                                    (!$page.props.auth.can.order_processing && !$page.props.auth.can.order_manage)
                                        ? 'bg-red-200 text-red-800 cursor-not-allowed opacity-75'
                                        : props.order.status === 'approved'
                                        ? 'bg-yellow-500 hover:bg-yellow-600'
                                        : statusOrder.indexOf(props.order.status) >
                                            statusOrder.indexOf('approved')
                                            ? 'bg-green-500'
                                            : 'bg-gray-300 cursor-not-allowed',
                                ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 min-w-[160px]">
                                    <svg v-if="
                                        isType['is_process'] &&
                                        props.order.status == 'approved'
                                    " class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4">
                                        </circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    <template v-else>
                                        <img src="/assets/images/inprocess.png" class="w-5 h-5 mr-2" alt="Process" />
                                        <span class="text-sm font-bold text-white">{{
                                            statusOrder.indexOf(props.order.status) >
                                                statusOrder.indexOf("approved")
                                                ? "Processed"
                                                : isType['is_process'] ? "Please Wait..." : "Process"
                                        }}</span>
                                    </template>
                                </button>
                                <span v-show="props.order?.processed_by" class="text-sm text-gray-600">
                                    On {{ moment(props.order?.processed_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.order?.processed_by" class="text-sm text-gray-600">
                                    By {{ props.order?.processed_by?.name }}
                                </span>
                            </div>
                            <div v-if="props.order.status === 'approved'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Dispatch button -->
                        <div class="relative" v-if="props.order.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button @click="showDispatchForm = true                                    " :disabled="isType['is_dispatch'] ||
                                    props.order.status !== 'in_process' ||
                                    (!$page.props.auth.can.order_dispatch && !$page.props.auth.can.order_manage)
                                    " :class="[
                                        (!$page.props.auth.can.order_dispatch && !$page.props.auth.can.order_manage)
                                            ? 'bg-red-200 text-red-800 cursor-not-allowed opacity-75'
                                            : props.order.status === 'in_process'
                                            ? 'bg-yellow-500 hover:bg-yellow-600'
                                            : statusOrder.indexOf(props.order.status) >
                                                statusOrder.indexOf('in_process')
                                                ? 'bg-green-500'
                                                : 'bg-gray-300 cursor-not-allowed',
                                    ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 min-w-[160px]">
                                    <svg v-if="
                                        isType['is_dispatch'] &&
                                        props.order.status === 'in_process'
                                    " class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4">
                                        </circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    <template v-else>
                                        <img src="/assets/images/dispatch.png" class="w-5 h-5 mr-2" alt="Dispatch" />
                                        <span class="text-sm font-bold text-white">{{
                                            statusOrder.indexOf(props.order.status) >
                                                statusOrder.indexOf("in_process")
                                                ? "Dispatched"
                                                : isType['is_dispatch'] ? "Please Wait..." : "Dispatch"
                                        }}</span>
                                    </template>
                                </button>
                                <span v-show="props.order?.dispatched_at" class="text-sm text-gray-600">
                                    On {{ moment(props.order?.dispatched_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.order?.dispatched_by" class="text-sm text-gray-600">
                                    By {{ props.order?.dispatched_by?.name }}
                                </span>
                            </div>
                            <div v-if="props.order.status === 'in_process'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Order Delivery Indicators -->
                        <!-- Delivered Status -->
                        <div class="relative" v-if="props.order.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button :class="[
                                    props.order.status === 'dispatched'
                                        ? 'bg-yellow-500 hover:bg-yellow-600'
                                        : statusOrder.indexOf(
                                            props.order.status
                                        ) > statusOrder.indexOf('dispatched')
                                            ? 'bg-green-500'
                                            : 'bg-gray-300 cursor-not-allowed',
                                ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]"
                                    disabled>
                                    <img src="/assets/images/delivery.png" class="w-5 h-5 mr-2" alt="dispatched" />
                                    <span class="text-sm font-bold text-white">
                                        {{
                                            statusOrder.indexOf(
                                                props.order.status
                                            ) > statusOrder.indexOf("dispatched")
                                                ? "Delivered"
                                                : "Waiting to be Delivered"
                                        }}
                                    </span>
                                </button>
                                <span v-show="props.order?.delivered_at" class="text-sm text-gray-600">
                                    On {{ moment(props.order?.delivered_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.order?.delivered_by" class="text-sm text-gray-600">
                                    By {{ props.order?.delivered_by?.name }}
                                </span>
                            </div>

                            <!-- Pulse Indicator if currently at this status -->
                            <div v-if="props.order.status === 'dispatched'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Received Status -->
                        <div class="relative" v-if="props.order.status !== 'rejected'">
                            <div class="flex flex-col">
                                <button :class="[
                                    props.order.status === 'delivered'
                                        ? 'bg-yellow-400'
                                        : statusOrder.indexOf(
                                            props.order.status
                                        ) > statusOrder.indexOf('delivered')
                                            ? 'bg-green-500'
                                            : 'bg-gray-300 cursor-not-allowed',
                                ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 text-white min-w-[160px]"
                                    disabled>
                                    <img src="/assets/images/received.png" class="w-5 h-5 mr-2" alt="Received" />
                                    <span class="text-sm font-bold text-white">
                                        {{
                                            statusOrder.indexOf(
                                                props.order.status
                                            ) > statusOrder.indexOf("delivered")
                                            ? "Received"
                                            : "Waiting to be Received"
                                        }}
                                    </span>
                                </button>
                                <span v-show="props.order?.received_at" class="text-sm text-gray-600">
                                    On {{ moment(props.order?.received_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.order?.received_by" class="text-sm text-gray-600">
                                    By {{ props.order?.received_by?.name }}
                                </span>
                            </div>

                            <!-- Pulse Indicator if currently at this status -->
                            <div v-if="props.order.status === 'delivered'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                        <!-- Rejected button -->
                        <div class="relative" v-if="props.order.status == 'reviewed' || props.order.status == 'rejected'">
<div class="flex flex-col">
                                <button @click="
                                    changeStatus(
                                        props.order.id,
                                        'rejected',
                                        'is_reject'
                                    )
                                    " :disabled="isType['is_reject'] ||
                                        props.order.status !== 'reviewed' ||
                                        !$page.props.auth.can.order_reject
                                        " :class="[
                                    !$page.props.auth.can.order_reject
                                        ? 'bg-red-200 text-red-800 cursor-not-allowed opacity-75'
                                        : props.order.status == 'reviewed'
                                        ? 'bg-red-500 hover:bg-red-600'
                                        : statusOrder.indexOf(props.order.status) >
                                            statusOrder.indexOf('reviewed')
                                            ? 'bg-red-500'
                                            : 'bg-gray-300 cursor-not-allowed',
                                ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 min-w-[160px]">
                                    <svg v-if="
                                        isLoading &&
                                        props.order.status === 'reviewed'
                                    " class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4">
                                        </circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    <template v-else>
                                        <img src="/assets/images/rejected.png" class="w-5 h-5 mr-2" alt="Rejected" />
                                        <span class="text-sm font-bold text-white">{{
                                            props.order.status == 'rejected'
                                                ? "Rejected"
                                                : isType["is_reject"] ? "Please Wait..." : "Reject"
                                        }}</span>
                                    </template>
                                </button>
                                <span v-show="props.order?.rejected_at" class="text-sm text-gray-600">
                                    On {{ moment(props.order?.rejected_at).format('DD/MM/YYYY HH:mm') }}
                                </span>
                                <span v-show="props.order?.rejected_by" class="text-sm text-gray-600">
                                    By {{ props.order?.rejected_by?.name }}
                                </span>
                            </div>
                            <div v-if="props.order.status === 'reviewed'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>

                         <!-- Restore button -->
                         <div class="relative" v-if="props.order.status === 'rejected'">
                            <div class="flex flex-col">
                                <button @click="
                                    restoreOrder(
                                        props.order.id,
                                        'reviewed',
                                        'is_restore'
                                    )
                                    " :disabled="isRestoring ||
                                        props.order.status !== 'rejected' ||
                                        !$page.props.auth.can.order_manage
                                        " :class="[
                                        !$page.props.auth.can.order_manage
                                            ? 'bg-red-200 text-red-800 cursor-not-allowed opacity-75'
                                            : 'bg-green-500 hover:bg-green-600'
                                    ]" class="inline-flex items-center justify-center px-4 py-2 rounded-lg shadow-sm transition-colors duration-150 min-w-[160px]">
                                    <svg v-if="
                                        isLoading &&
                                        props.order.status === 'rejected'
                                    " class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4">
                                        </circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    <template v-else>
                                        <img src="/assets/images/restore.jpg" class="w-5 h-5 mr-2" alt="Restore" />
                                        <span class="text-sm font-bold text-white">{{ isRestoring ? "Restoring..." : "Restore Order"  }}</span>
                                    </template>
                                </button>
                            </div>
                            <div v-if="props.order.status === 'rejected'"
                                class="absolute -top-2 -right-2 w-4 h-4 bg-yellow-400 rounded-full animate-pulse"></div>
                        </div>
                                                  
                    </div>
                </div>
            </div>

            <!-- Back Order Modal -->
            <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl w-full h-full overflow-hidden">
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            Back Order Details
                        </h3>
                        <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-4 overflow-y-auto h-full">
                        <div class="mb-4">
                            <span class="text-sm font-medium text-gray-700">Order #{{ props.order.id }}</span>
                            <h2 class="text-xl font-bold text-gray-900">
                                Back Ordered Items
                            </h2>
                        </div>
                        <div class="overflow-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                                <thead class="bg-gray-50">
                                    <tr class="border-b border-gray-200">
                                        <th
                                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                            Requested Quantity
                                        </th>
                                        <th
                                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Back Order
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in selectedBackOrder" :key="item.id" class="hover:bg-gray-50">
                                        <td class="px-3 py-3 text-sm text-gray-900 border-r border-gray-200">
                                            {{ item.allocated_quantity }}
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-900">
                                            <table class="min-w-full">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th
                                                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Quantity
                                                        </th>
                                                        <th
                                                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Type
                                                        </th>
                                                        <th
                                                            class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Notes
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white">
                                                    <tr v-for="backOrder in item.back_order" :key="backOrder.id">
                                                        <td class="px-3 py-3 text-sm text-gray-900">
                                                            {{ backOrder.quantity }}
                                                        </td>
                                                        <td class="px-3 py-3 text-sm text-gray-900">
                                                            {{ backOrder.type }}
                                                        </td>
                                                        <td class="px-3 py-3 text-sm text-gray-900">
                                                            {{ backOrder.notes }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-sm text-gray-500">
                            <p>
                                These items have been partially received. The
                                remaining quantities are on back order.
                            </p>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-200 flex justify-end">
                        <button @click="showModal = false"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition-colors duration-150">
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <Modal :show="showDispatchForm" @close="showDispatchForm = false">
                <div class="p-6 bg-white rounded-md shadow-md">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">
                        Dispatch Note (Driver Handover Log)
                    </h2>

                    <form @submit.prevent="createDispatch" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Driver</label>
                            <Multiselect
                                v-model="dispatchForm.driver"
                                :options="driverOptions"
                                :searchable="true"
                                :close-on-select="true"
                                :show-labels="false"
                                :allow-empty="true"
                                placeholder="Select Driver"
                                track-by="id"
                                label="name"
                                @select="handleDriverSelect"
                                :class="{ 'border-red-500': dispatchErrors.driver_id }"
                            >
                                <template v-slot:option="{ option }">
                                    <div
                                        :class="{
                                            'add-new-option': option.isAddNew,
                                        }"
                                    >
                                        <span
                                            v-if="option.isAddNew"
                                            class="text-indigo-600 font-medium"
                                        >+ Add New Driver</span>
                                        <span v-else>
                                            {{ option.name }}
                                            <span v-if="option.company" class="text-gray-500 text-sm">
                                                ({{ option.company.name }})
                                            </span>
                                        </span>
                                    </div>
                                </template>
                            </Multiselect>
                            <p v-if="dispatchErrors.driver_id" class="mt-1 text-sm text-red-600">{{ dispatchErrors.driver_id[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Dispatch Date</label>
                            <input 
                                type="date" 
                                required
                                v-model="dispatchForm.dispatch_date"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                :class="{ 'border-red-500': dispatchErrors.dispatch_date }"
                            >
                            <p v-if="dispatchErrors.dispatch_date" class="mt-1 text-sm text-red-600">{{ dispatchErrors.dispatch_date[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Number of Cartons</label>
                            <input 
                                type="number" 
                                v-model="dispatchForm.no_of_cartoons"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                :class="{ 'border-red-500': dispatchErrors.no_of_cartoons }"
                            >
                            <p v-if="dispatchErrors.no_of_cartoons" class="mt-1 text-sm text-red-600">{{ dispatchErrors.no_of_cartoons[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Driver Phone</label>
                            <input 
                                type="text" 
                                required
                                v-model="dispatchForm.driver_number"    
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                :class="{ 'border-red-500': dispatchErrors.driver_number }"
                            >
                            <p v-if="dispatchErrors.driver_number" class="mt-1 text-sm text-red-600">{{ dispatchErrors.driver_number[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Plate Number</label>
                            <input 
                                type="text" 
                                required
                                v-model="dispatchForm.plate_number"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                :class="{ 'border-red-500': dispatchErrors.plate_number }"
                            >
                            <p v-if="dispatchErrors.plate_number" class="mt-1 text-sm text-red-600">{{ dispatchErrors.plate_number[0] }}</p>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button 
                                type="button" 
                                @click="showDispatchForm = false" 
                                :disabled="isSaving"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors duration-150"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSaving"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors duration-150 flex items-center"
                            >
                                <span v-if="isSaving" class="mr-2">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </span>
                                {{ isSaving ? 'Creating...' : 'Save and Dispatch' }}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>

            <Modal :show="showDriverModal" @close="closeDriverModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Add New Driver</h2>

                    <div class="mt-6">
                        <form @submit.prevent="submitDriver" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Driver ID</label>
                                <input 
                                    type="text" 
                                    v-model="driverForm.driver_id" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': driverErrors.driver_id }"
                                >
                                <p v-if="driverErrors.driver_id" class="mt-1 text-sm text-red-600">{{ driverErrors.driver_id[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input 
                                    type="text" 
                                    v-model="driverForm.name" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': driverErrors.name }"
                                >
                                <p v-if="driverErrors.name" class="mt-1 text-sm text-red-600">{{ driverErrors.name[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <input 
                                    type="text" 
                                    v-model="driverForm.phone" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': driverErrors.phone }"
                                >
                                <p v-if="driverErrors.phone" class="mt-1 text-sm text-red-600">{{ driverErrors.phone[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Company</label>
                                <Multiselect
                                    v-model="driverForm.company"
                                    :options="companyOptionsWithAdd"
                                    :searchable="true"
                                    :close-on-select="true"
                                    :show-labels="false"
                                    :allow-empty="true"
                                    placeholder="Select Company"
                                    track-by="id"
                                    label="name"
                                    @select="handleCompanySelect"
                                    :class="{ 'border-red-500': driverErrors.logistic_company_id }"
                                >
                                    <template v-slot:option="{ option }">
                                        <div
                                            :class="{
                                                'add-new-option': option.isAddNew,
                                            }"
                                        >
                                            <span
                                                v-if="option.isAddNew"
                                                class="text-indigo-600 font-medium"
                                            >+ Add New Company</span>
                                            <span v-else>{{ option.name }}</span>
                                        </div>
                                    </template>
                                </Multiselect>
                                <p v-if="driverErrors.logistic_company_id" class="mt-1 text-sm text-red-600">{{ driverErrors.logistic_company_id[0] }}</p>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button 
                                    type="button" 
                                    @click="closeDriverModal" 
                                    :disabled="isSubmittingDriver"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors duration-150"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="isSubmittingDriver"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors duration-150 flex items-center"
                                >
                                    <span v-if="isSubmittingDriver" class="mr-2">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </span>
                                    {{ isSubmittingDriver ? 'Creating...' : 'Create' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Modal>

            <Modal :show="showCompanyModal" @close="closeCompanyModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Add New Company</h2>

                    <div class="mt-6">
                        <form @submit.prevent="submitCompany" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input 
                                    type="text" 
                                    v-model="companyForm.name" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': companyErrors.name }"
                                >
                                <p v-if="companyErrors.name" class="mt-1 text-sm text-red-600">{{ companyErrors.name[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input 
                                    type="email" 
                                    v-model="companyForm.email" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': companyErrors.email }"
                                >
                                <p v-if="companyErrors.email" class="mt-1 text-sm text-red-600">{{ companyErrors.email[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Incharge Person</label>
                                <input 
                                    type="text" 
                                    v-model="companyForm.incharge_person" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': companyErrors.incharge_person }"
                                >
                                <p v-if="companyErrors.incharge_person" class="mt-1 text-sm text-red-600">{{ companyErrors.incharge_person[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Incharge Phone</label>
                                <input 
                                    type="text" 
                                    v-model="companyForm.incharge_phone" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': companyErrors.incharge_phone }"
                                >
                                <p v-if="companyErrors.incharge_phone" class="mt-1 text-sm text-red-600">{{ companyErrors.incharge_phone[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea 
                                    v-model="companyForm.address" 
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    :class="{ 'border-red-500': companyErrors.address }"
                                ></textarea>
                                <p v-if="companyErrors.address" class="mt-1 text-sm text-red-600">{{ companyErrors.address[0] }}</p>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button 
                                    type="button" 
                                    @click="closeCompanyModal" 
                                    :disabled="isSubmittingCompany"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors duration-150"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit" 
                                    :disabled="isSubmittingCompany"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors duration-150 flex items-center"
                                >
                                    <span v-if="isSubmittingCompany" class="mr-2">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </span>
                                    {{ isSubmittingCompany ? 'Creating...' : 'Create' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch, h } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { router, Link } from "@inertiajs/vue3";
import {
    BuildingOfficeIcon,
    EnvelopeIcon,
    PhoneIcon,
    MapPinIcon,
    UserIcon,
    IdentificationIcon,
    CalendarIcon,
    TruckIcon,
    ArchiveBoxIcon,
    ClockIcon,
    PrinterIcon
} from "@heroicons/vue/24/outline";
import Swal from "sweetalert2";
import axios from "axios";
import moment from "moment";
import Modal from "@/Components/Modal.vue";
import { useToast } from "vue-toastification";
import Multiselect from 'vue-multiselect';
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";

const toast = useToast();

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    error: String,
    products: Array,
    drivers: Array,
    companyOptions: Array,
});

const showModal = ref(false);
const selectedBackOrder = ref(null);
const showDispatchForm = ref(false);
const showDriverModal = ref(false);
const showCompanyModal = ref(false);
const showDispatchImagesModal = ref(false);
const showImageLightbox = ref(false);
const isSubmittingDriver = ref(false);
const isSubmittingCompany = ref(false);
const dispatchImages = ref([]);
const currentImageIndex = ref(0);
const driverForm = ref({
    driver_id: '',
    name: '',
    phone: '',
    logistic_company_id: '',
    company: null,
    is_active: true
});

const dispatchForm = ref({
    driver: null,
    dispatch_date: new Date().toISOString().split('T')[0],
    no_of_cartoons: '',
    driver_number: '',
    plate_number: '',
    logistic_company_id: ''
});

const companyForm = ref({
    name: '',
    email: '',
    incharge_person: '',
    incharge_phone: '',
    address: '',
    is_active: true
});

const driverErrors = ref({});
const companyErrors = ref({});

const statusClasses = {
    pending: "bg-yellow-100 text-yellow-800 rounded-full font-bold",
    reviewed: "bg-green-100 text-green-800 rounded-full font-bold",
    approved: "bg-green-100 text-green-800 rounded-full font-bold",
    "in process": "bg-blue-100 text-blue-800 rounded-full font-bold",
    dispatched: "bg-purple-100 text-purple-800 rounded-full font-bold",
    delivered: "bg-purple-100 text-purple-800 rounded-full font-bold",
    received:
        "bg-green-100 text-green-800 rounded-full font-bold flex items-center",
};

const isLoading = ref(false);
const form = ref([]);

function syncFormFromOrder() {
    const items = props.order?.items || [];
    form.value = items.map((item) => ({
        ...item,
        no_of_days: Math.max(1, Number(item.no_of_days) || 1),
    }));
}

onMounted(() => {
    console.log('Order data:', props.order);
    console.log('Order items:', props.order.items);
    syncFormFromOrder();
});

// When order items are refreshed (e.g. after update), keep form in sync so column and input show same no_of_days
watch(() => props.order?.items, (items) => {
    if (items?.length) syncFormFromOrder();
}, { deep: true });

const formatDate = (date) => {
    return moment(date).format("DD/MM/YYYY");
};

const statusOrder = [
    "pending",
    "reviewed",
    "approved",
    "in_process",
    "dispatched",
    "delivered",
    "received",
];

const isRestoring = ref(false);
const restoreOrder = async () => {
    Swal.fire({
        title: "Are you sure?",
        text: "You want to restore the order?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
    })
    .then(async (result) => {
        if (result.isConfirmed) {
            isRestoring.value = true;
            await axios.post(route("orders.restore-order"), {
                order_id: props.order.id,
            })
                .then((response) => {
                    isRestoring.value = false;
                    Swal.fire({
                        title: "Success!",
                        text: response.data,
                        icon: "success",
                        confirmButtonText: "OK",
                    }).then(() => {
                        router.get(route("orders.show", props.order.id));
                    });
                })
                .catch((error) => {
                    isRestoring.value = false;
                    console.log(error);
                    toast.error(error.response?.data || "Failed to restore order");
                });
        }
    });
};

// update quantity with debouncing
const isUpading = ref([]);
const updateQuantityTimeouts = ref({});

// Cleanup on unmount
onBeforeUnmount(() => {
    Object.values(updateQuantityTimeouts.value).forEach(timeout => {
        if (timeout) clearTimeout(timeout);
    });
});

// Debounced input handler
const handleQuantityInput = (item, type, index) => {
    const timeoutKey = `${item.id}-${type}-${index}`;
    
    if (updateQuantityTimeouts.value[timeoutKey]) {
        clearTimeout(updateQuantityTimeouts.value[timeoutKey]);
    }
    
    updateQuantityTimeouts.value[timeoutKey] = setTimeout(() => {
        updateQuantity(item, type, index);
    }, 500);
};

async function updateQuantity(item, type, index) {
    const days = item.no_of_days ?? item.days;
    if (type === 'days' && (!days || Number(days) < 1)) {
        toast.error('No. of days cannot be 0. Please enter at least 1.');
        return;
    }
    isUpading.value[index] = true;
    await axios
        .post(route("orders.update-quantity"), {
            item_id: item.id,
            quantity: item.quantity_to_release,
            days: type === 'days' ? Math.max(1, Number(days)) : (days || 1),
            type,
        })
        .then(() => {
            isUpading.value[index] = false;
            router.get(route("orders.show", props.order.id), {}, {
                preserveScroll: true,
            });
        })
        .catch((error) => {
            isUpading.value[index] = false;
            console.log(error);
            toast.error(error.response?.data || "Failed to update quantity");
        });
}

const isSaving = ref(false);
const dispatchErrors = ref({});

const createDispatch = async () => {
        isSaving.value = true;
        dispatchErrors.value = {};
        
        console.log('Dispatch form data:', dispatchForm.value); // Debug form data

        // Validate form data before submission
        if (!dispatchForm.value.driver) {
            console.error('Driver not selected');
            dispatchErrors.value.driver_id = ['Please select a driver'];
            isSaving.value = false;
            return;
        }

        const formData = {
            order_id: props.order.id,
            driver_id: dispatchForm.value.driver?.id,
            logistic_company_id: dispatchForm.value.logistic_company_id,
            dispatch_date: dispatchForm.value.dispatch_date,
            driver_number: dispatchForm.value.driver_number,
            plate_number: dispatchForm.value.plate_number,
            no_of_cartoons: dispatchForm.value.no_of_cartoons,
            status: 'dispatched'
        };

         await axios.post(route('orders.dispatch-info'), formData)
            .then((response) => {
                console.log(response.data);
                isSaving.value = false;
                showDispatchForm.value = false;
                dispatchForm.value = {
                    driver: null,
                    dispatch_date: '',
                    no_of_cartoons: '',
                    driver_number: '',
                    plate_number: '',
                    logistic_company_id: ''
                };
        
                Swal.fire({
                    title: 'Success!',
                    text: response.data,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6',
                }).then(() => {
                    router.get(route("orders.show", props.order.id));
                });
            })
            .catch((error) => {
                isSaving.value = false;
                console.log(error);
                if (error.response?.status === 422) {
                    console.log('Validation errors:', error.response.data.errors); // Debug validation errors
                    dispatchErrors.value = error.response.data.errors;
                    toast.error('Please check the form for errors');
                } else {
                    console.error('Error details:', {
                        status: error.response?.status,
                        data: error.response?.data,
                        message: error.message
                    }); // Debug detailed error info
                    
                    toast.error(error.response?.data || error.response?.data?.message || 'Something went wrong');
                }
            });
};

const isType = ref([]);
// Function to change order status
const changeStatus = (orderId, newStatus, type) => {
    console.log(orderId, newStatus, type);
    
    // Special handling for approve action - check if quantity_to_release is 0
    if (newStatus === 'approved') {
        const totalQuantityToRelease = form.value.reduce((total, item) => {
            return total + (parseFloat(item.quantity_to_release) || 0);
        }, 0);
        
        if (totalQuantityToRelease === 0) {
            Swal.fire({
                title: "No Quantity to Release",
                text: "There is no quantity release for the current order. Do you want to proceed? Proceeding will lead to rejection of the order.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Proceed (Reject Order)",
                cancelButtonText: "Cancel"
            }).then(async (result) => {
                if (result.isConfirmed) {
                    // Set loading state
                    isType.value[type] = true;

                    await axios
                        .post(route("orders.change-status"), {
                            order_id: orderId,
                            status: 'rejected',
                        })
                        .then((response) => {
                            // Reset loading state
                            isType.value[type] = false;

                            Swal.fire({
                                title: "Updated!",
                                text: "Order status has been updated to rejected.",
                                icon: "success",
                                toast: true,
                                position: "top-end",
                                showConfirmButton: false,
                                timer: 3000,
                            }).then(() => {
                                // Reload the page to show the updated status
                                router.get(route("orders.show", props.order.id));
                            });
                        })
                        .catch((error) => {
                            // Reset loading state
                            isType.value[type] = false;

                            Swal.fire({
                                title: "Error!",
                                text:
                                    error.response?.data ||
                                    "Failed to update order status",
                                icon: "error",
                                toast: true,
                                position: "top-end",
                                showConfirmButton: false,
                                timer: 3000,
                            });
                        });
                }
            });
            return; // Exit early to prevent normal approval flow
        }
    }
    
    Swal.fire({
        title: "Are you sure?",
        text: `Do you want to change the order status to ${newStatus}?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, change it!",
    }).then(async (result) => {
        if (result.isConfirmed) {
            // Set loading state
            isType.value[type] = true;

            await axios
                .post(route("orders.change-status"), {
                    order_id: orderId,
                    status: newStatus,
                })
                .then((response) => {
                    // Reset loading state
                    isType.value[type] = false;

                    Swal.fire({
                        title: "Updated!",
                        text: "Order status has been updated.",
                        icon: "success",
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                    }).then(() => {
                        // Reload the page to show the updated status
                        router.get(route("orders.show", props.order.id));
                    });
                })
                .catch((error) => {
                    // Reset loading state
                    isType.value[type] = false;

                    Swal.fire({
                        title: "Error!",
                        text:
                            error.response?.data ||
                            "Failed to update order status",
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

const getStatusProgress = (currentStatus) => {
    const currentIndex = statusOrder.indexOf(currentStatus);
    return statusOrder.map((status, index) => ({
        status,
        isActive: index <= currentIndex,
        isPast: index < currentIndex,
    }));
};

const statusProgress = computed(() => getStatusProgress(props.order.status));

const driverOptions = computed(() => {
    const options = props.drivers.map(driver => ({
        id: driver.id,
        name: driver.name,
        phone: driver.phone,
        company: driver.company,
        isAddNew: false
    }));
    
    // Add the "Add New" option at the end
    options.push({
        id: 'new',
        name: 'Add New Driver',
        isAddNew: true
    });
    
    return options;
});

const companyOptionsWithAdd = computed(() => {
    const options = [...props.companyOptions];
    
    // Add the "Add New" option at the end
    options.push({
        id: 'new',
        name: 'Add New Company',
        isAddNew: true
    });
    
    return options;
});

const handleDriverSelect = (selected) => {
    if (selected && selected.isAddNew) {
        // Reset the selection
        dispatchForm.value.driver = null;
        dispatchForm.value.driver_number = '';
        dispatchForm.value.logistic_company_id = '';
        // Open the driver modal
        openDriverModal();
    } else if (selected) {
        // Set the driver info
        dispatchForm.value.driver = selected;
        dispatchForm.value.driver_number = selected.phone;
        dispatchForm.value.logistic_company_id = selected.company?.id;
    } else {
        // Clear the driver info if deselected
        dispatchForm.value.driver = null;
        dispatchForm.value.driver_number = '';
        dispatchForm.value.logistic_company_id = '';
    }
};

const openDriverModal = () => {
    driverForm.value = {
        driver_id: '',
        name: '',
        phone: '',
        logistic_company_id: '',
        company: null,
        is_active: true
    };
    showDriverModal.value = true;
};

const closeDriverModal = () => {
    showDriverModal.value = false;
    driverForm.value = {
        driver_id: '',
        name: '',
        phone: '',
        logistic_company_id: '',
        company: null,
        is_active: true
    };
    driverErrors.value = {};
};

const submitDriver = async () => {
    try {
        isSubmittingDriver.value = true;
        driverErrors.value = {};
        
        // Prepare the data to send, ensuring logistic_company_id is set
        const dataToSend = {
            ...driverForm.value,
            logistic_company_id: driverForm.value.company?.id || driverForm.value.logistic_company_id
        };
        
        // Remove the company object from the data to send
        delete dataToSend.company;
        
        const response = await axios.post(route('settings.drivers.store'), dataToSend);
        
        // Create a new driver option
        const newDriver = {
            id: response.data.id,
            name: driverForm.value.name,
            phone: driverForm.value.phone,
            company: driverForm.value.company,
            isAddNew: false
        };
        
        // Add the new driver to the options
        props.drivers.push(newDriver);
        
        // Select the new driver
        dispatchForm.value.driver = newDriver;
        dispatchForm.value.driver_number = newDriver.phone;
        dispatchForm.value.logistic_company_id = newDriver.company?.id;
        
        closeDriverModal();
        Swal.fire({
            title: 'Success!',
            text: response.data.message,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
        });
    } catch (error) {
        if (error.response?.status === 422) {
            driverErrors.value = error.response.data.errors;
        } else {
            Swal.fire({
                title: 'Error!',
                text: error.response?.data || 'Something went wrong',
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6',
            });
        }
    } finally {
        isSubmittingDriver.value = false;
    }
};

const handleCompanySelect = (selected) => {
    if (selected && selected.isAddNew) {
        // Reset the selection
        driverForm.value.company = null;
        driverForm.value.logistic_company_id = '';
        // Open the company modal
        openCompanyModal();
    } else if (selected) {
        // Set the company info
        driverForm.value.company = selected;
        driverForm.value.logistic_company_id = selected.id;
    } else {
        // Clear the company info if deselected
        driverForm.value.company = null;
        driverForm.value.logistic_company_id = '';
    }
};

const openCompanyModal = () => {
    // Reset the company form
    companyForm.value = {
        name: '',
        email: '',
        incharge_person: '',
        incharge_phone: '',
        address: '',
        is_active: true
    };
    showCompanyModal.value = true;
};

const closeCompanyModal = () => {
    showCompanyModal.value = false;
    companyForm.value = {
        name: '',
        email: '',
        incharge_person: '',
        incharge_phone: '',
        address: '',
        is_active: true
    };
    companyErrors.value = {};
};

const submitCompany = async () => {
    try {
        isSubmittingCompany.value = true;
        companyErrors.value = {};
        
        const response = await axios.post(route('settings.logistics.companies.store'), companyForm.value);
        
        // Create a new company option
        const newCompany = {
            id: response.data.id,
            name: companyForm.value.name,
            isAddNew: false
        };
        
        // Add the new company to the options
        props.companyOptions.push(newCompany);
        
        // Select the new company
        driverForm.value.company = newCompany;
        driverForm.value.logistic_company_id = newCompany.id;
        
        closeCompanyModal();
        Swal.fire({
            title: 'Success!',
            text: response.data.message,
            icon: 'success',
            confirmButtonText: 'OK',
            confirmButtonColor: '#3085d6',
        });
    } catch (error) {
        if (error.response?.status === 422) {
            companyErrors.value = error.response.data.errors;
        } else {
            Swal.fire({
                title: 'Error!',
                text: error.response?.data || 'Something went wrong',
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6',
            });
        }
    } finally {
        isSubmittingCompany.value = false;
    }
};

const printDispatchNote = (dispatch) => {
    // Implement print functionality
    console.log('Printing dispatch note:', dispatch);
};

const trackDispatch = (dispatch) => {
    // Implement tracking functionality
    console.log('Tracking dispatch:', dispatch);
};

// Dispatch images modal methods
const viewDispatchImages = (dispatch) => {
    dispatchImages.value = [];

    const img = dispatch?.image;
    if (!img) {
        showDispatchImagesModal.value = true;
        return;
    }

    if (Array.isArray(img)) {
        dispatchImages.value = img.filter(Boolean);
    } else if (typeof img === 'string') {
        let parsed = null;
        try {
            parsed = JSON.parse(img);
        } catch (e) {
            parsed = null;
        }
        if (Array.isArray(parsed)) {
            dispatchImages.value = parsed.filter(Boolean);
        } else if (img) {
            dispatchImages.value = [img];
        }
    }

    showDispatchImagesModal.value = true;
};

const closeDispatchImagesModal = () => {
    showDispatchImagesModal.value = false;
    dispatchImages.value = [];
    currentImageIndex.value = 0;
};

const getImageUrl = (imagePath) => {
    if (!imagePath) return '';
    // If already an absolute URL, return as-is
    if (/^https?:\/\//i.test(imagePath)) return imagePath;
    // Normalize common storage prefixes and leading slashes
    const normalized = imagePath.replace(/^\/?public\//, '/');
    return normalized.startsWith('/') ? normalized : '/' + normalized;
};

const handleImageError = (event) => {
    console.error('Failed to load image:', event.target.src);
    event.target.style.display = 'none';
};

const openImageLightbox = (index) => {
    if (dispatchImages.value[index]) {
        currentImageIndex.value = index;
        showImageLightbox.value = true;
    }
};

const closeImageLightbox = () => {
    showImageLightbox.value = false;
    currentImageIndex.value = 0;
};

const previousImage = () => {
    if (currentImageIndex.value > 0) {
        currentImageIndex.value--;
    }
};

const nextImage = () => {
    if (currentImageIndex.value < dispatchImages.value.length - 1) {
        currentImageIndex.value++;
    }
};
</script>