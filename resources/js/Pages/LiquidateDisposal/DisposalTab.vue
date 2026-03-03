<template>
    <div>
        <div class="overflow-auto">
            <table class="w-full table-sm">
                <thead style="background-color: #F4F7FB;">
                    <tr>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b rounded-tl-lg" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Disposal ID</th>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Date</th>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Warehouse/Facility</th>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Source</th>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Disposed By</th>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Items</th>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Total Cost</th>
                        <th class="px-2 py-2 text-left text-xs font-bold uppercase border-b rounded-tr-lg" style="color: #4F6FCB; border-bottom: 2px solid #B7C6E6;">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <tr v-if="disposals.data?.length === 0">
                        <td colspan="8" class="px-2 py-2 text-center text-sm text-gray-600 border-b" style="border-bottom: 1px solid #B7C6E6;">No disposals found</td>
                    </tr>
                    <tr
                        v-for="disposal in disposals.data"
                        :key="disposal.id"
                        class="border-b"
                        :class="{ 'hover:bg-gray-50': true, 'text-red-500': disposal.status === 'rejected' }"
                        style="border-bottom: 1px solid #B7C6E6;"
                    >
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">
                            <Link :href="route('liquidate-disposal.disposals.show', disposal.id)" class="text-blue-600 hover:text-blue-800">{{ disposal.disposal_id || '—' }}</Link>
                        </td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-600 border-b" style="border-bottom: 1px solid #B7C6E6;">{{ formatDate(disposal.disposed_at) }}</td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-600 border-b" style="border-bottom: 1px solid #B7C6E6;">
                            {{ formatWarehouseFacility(disposal.facility, disposal.warehouse) }}
                        </td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-900 border-b" style="border-bottom: 1px solid #B7C6E6;">{{ disposal.source_display || (disposal.source ? String(disposal.source).replace(/_/g, ' ') : null) || '—' }}</td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-600 border-b" style="border-bottom: 1px solid #B7C6E6;">{{ disposal.disposed_by_name ?? disposal.disposedBy?.name ?? '—' }}</td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-600 border-b" style="border-bottom: 1px solid #B7C6E6;">{{ (disposal.items?.length ?? 0) }} item{{ (disposal.items?.length ?? 0) === 1 ? '' : 's' }}</td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-600 border-b" style="border-bottom: 1px solid #B7C6E6;">${{ formatNumber(calculateTotalCost(disposal)) }}</td>
                        <td class="px-2 py-2 whitespace-nowrap border-b" style="border-bottom: 1px solid #B7C6E6;">
                            <div class="flex items-center gap-1.5">
                                <img src="/assets/images/pending.png" class="w-6 h-6 shrink-0" alt="Pending" title="Pending" />
                                <img v-if="['reviewed','approved'].includes(disposal.status)" src="/assets/images/review.png" class="w-6 h-6 shrink-0" alt="Reviewed" title="Reviewed" />
                                <img v-if="disposal.status === 'approved'" src="/assets/images/approved.png" class="w-6 h-6 shrink-0" alt="Approved" title="Approved" />
                                <img v-if="disposal.status === 'rejected'" src="/assets/images/rejected.png" class="w-6 h-6 shrink-0" alt="Rejected" title="Rejected" />
                                <span class="text-xs font-medium text-gray-700 capitalize">{{ disposal.status || '—' }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex justify-end">
            <TailwindPagination :data="disposals" :limit="2" @pagination-change-page="$emit('pagination-change', $event)" />
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { TailwindPagination } from 'laravel-vue-pagination';
import moment from 'moment';

const props = defineProps({
    disposals: Object,
    filters: Object,
});

defineEmits(['pagination-change']);

const formatDate = (date) => {
    if (!date) return '—';
    return moment(date).format('DD/MM/YYYY');
};

const formatNumber = (number) => {
    const n = Number(number);
    if (Number.isNaN(n)) return '0.00';
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatWarehouseFacility = (facility, warehouse) => {
    const parts = [facility, warehouse].filter(Boolean);
    return parts.length ? parts.join(' / ') : '—';
};

const calculateTotalCost = (disposal) => {
    if (!disposal?.items?.length) return 0;
    return disposal.items.reduce((total, item) => total + Number(item.total_cost || 0), 0);
};
</script> 