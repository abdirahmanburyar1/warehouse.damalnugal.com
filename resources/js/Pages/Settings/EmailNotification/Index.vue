<template>
    <AuthenticatedLayout :title="'Email Notifications'" description="Configure programmable email notifications" img="/assets/images/settings.png">
        <Head title="Email Notifications" />
        <div class="p-6 max-w-3xl">
            <Link
                :href="route('settings.index')"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 mb-6 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to settings
            </Link>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Email Notifications</h1>
            <p class="mt-1 text-sm text-slate-500 mb-8">Configure when and to whom expiry and other notifications are sent.</p>

            <form @submit.prevent="submit" class="space-y-8">
                <!-- Expiry items -->
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Expiry items</h2>
                                <p class="mt-0.5 text-sm text-slate-500">Send a daily email by policy: already expired, expiring in 6 months, and/or expiring in 1 year. Choose which timeframes to include and who receives (by role).</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input
                                    v-model="form.expiry_items.enabled"
                                    type="checkbox"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-slate-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                                <span class="ms-3 text-sm font-medium text-slate-700">Enable</span>
                            </label>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Notify users with these roles</label>
                            <Multiselect
                                v-model="form.expiry_items.roles"
                                :options="roles"
                                :multiple="true"
                                :searchable="true"
                                :close-on-select="false"
                                track-by="id"
                                label="name"
                                placeholder="Select roles"
                                class="mt-1"
                            />
                            <p class="mt-1 text-xs text-slate-500">Users who have at least one of these roles (and are active) will receive the expiry report email.</p>
                        </div>
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Include in email (when to notify)</label>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.expiry_items.notify_expired"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    />
                                    <span class="text-sm text-slate-700">Already expired</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.expiry_items.notify_expiring_6_months"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    />
                                    <span class="text-sm text-slate-700">Expiring within 6 months</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.expiry_items.notify_expiring_1_year"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    />
                                    <span class="text-sm text-slate-700">Expiring within 1 year</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Choose which timeframes to include. “1 year” covers items expiring after 6 months and up to 12 months from today.</p>
                        </div>
                        <div class="space-y-4 rounded-lg bg-slate-50 border border-slate-200 p-4">
                            <p class="font-medium text-slate-700 text-sm">Programmable schedule</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="send_time" class="block text-xs font-medium text-slate-600 mb-1">Send time (daily)</label>
                                    <input
                                        id="send_time"
                                        v-model="form.expiry_items.send_time"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="e.g. 13:49"
                                        maxlength="5"
                                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 font-mono"
                                        @blur="form.expiry_items.send_time = normalizeTime(form.expiry_items.send_time)"
                                    />
                                    <p class="mt-0.5 text-xs text-slate-500">24-hour format (e.g. 07:00 or 13:49). Expired section is included every day at this time.</p>
                                </div>
                                <div>
                                    <label for="interval_6m" class="block text-xs font-medium text-slate-600 mb-1">Expiring in 6 months: every (days)</label>
                                    <input
                                        id="interval_6m"
                                        v-model.number="form.expiry_items.expiring_6_months_interval_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                                    />
                                    <p class="mt-0.5 text-xs text-slate-500">Include 6‑month section every N days (e.g. 4 = twice a week).</p>
                                </div>
                                <div>
                                    <label for="interval_1y" class="block text-xs font-medium text-slate-600 mb-1">Expiring in 1 year: every (days)</label>
                                    <input
                                        id="interval_1y"
                                        v-model.number="form.expiry_items.expiring_1_year_interval_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                                    />
                                    <p class="mt-0.5 text-xs text-slate-500">Include 1‑year section every N days (e.g. 14 = every two weeks).</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 12 12 12s12-5.373 12-12h-4a8 8 0 01-8 8z" />
                        </svg>
                        {{ saving ? 'Saving…' : 'Save settings' }}
                    </button>
                    <p v-if="success" class="text-sm text-emerald-600">Settings saved.</p>
                    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import '@/Components/multiselect.css';

const page = usePage();
const props = defineProps({
    roles: { type: Array, default: () => [] },
    expiryItems: {
        type: Object,
        default: () => ({
            enabled: false,
            role_ids: [],
            notify_expired: true,
            notify_expiring_6_months: true,
            notify_expiring_1_year: true,
            send_time: '07:00',
            expiring_6_months_interval_days: 4,
            expiring_1_year_interval_days: 14,
        }),
    },
});

const saving = ref(false);
const success = ref(false);
const error = ref('');

function normalizeTime(t) {
    if (!t || typeof t !== 'string') return '07:00';
    const trimmed = t.trim();
    // HH:mm or H:mm
    let m = trimmed.match(/^(\d{1,2}):(\d{2})$/);
    if (m) {
        const h = Math.min(23, Math.max(0, parseInt(m[1], 10)));
        const min = Math.min(59, Math.max(0, parseInt(m[2], 10)));
        return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
    }
    // HHmm (4 digits, e.g. 1349 -> 13:49)
    m = trimmed.match(/^(\d{2})(\d{2})$/);
    if (m) {
        const h = Math.min(23, Math.max(0, parseInt(m[1], 10)));
        const min = Math.min(59, Math.max(0, parseInt(m[2], 10)));
        return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
    }
    return '07:00';
}

const form = ref({
    expiry_items: {
        enabled: !!props.expiryItems.enabled,
        roles: (props.expiryItems.role_ids || []).length
            ? props.roles.filter((r) => props.expiryItems.role_ids.includes(r.id))
            : [],
        notify_expired: !!props.expiryItems.notify_expired,
        notify_expiring_6_months: !!props.expiryItems.notify_expiring_6_months,
        notify_expiring_1_year: !!props.expiryItems.notify_expiring_1_year,
        send_time: normalizeTime(props.expiryItems.send_time),
        expiring_6_months_interval_days: Math.max(1, Math.min(365, parseInt(props.expiryItems.expiring_6_months_interval_days, 10) || 4)),
        expiring_1_year_interval_days: Math.max(1, Math.min(365, parseInt(props.expiryItems.expiring_1_year_interval_days, 10) || 14)),
    },
});

watch(() => [props.expiryItems, props.roles], () => {
    form.value.expiry_items = {
        enabled: !!props.expiryItems.enabled,
        roles: (props.expiryItems.role_ids || []).length
            ? props.roles.filter((r) => props.expiryItems.role_ids.includes(r.id))
            : [],
        notify_expired: !!props.expiryItems.notify_expired,
        notify_expiring_6_months: !!props.expiryItems.notify_expiring_6_months,
        notify_expiring_1_year: !!props.expiryItems.notify_expiring_1_year,
        send_time: normalizeTime(props.expiryItems.send_time),
        expiring_6_months_interval_days: Math.max(1, Math.min(365, parseInt(props.expiryItems.expiring_6_months_interval_days, 10) || 4)),
        expiring_1_year_interval_days: Math.max(1, Math.min(365, parseInt(props.expiryItems.expiring_1_year_interval_days, 10) || 14)),
    };
}, { deep: true });

function submit() {
    saving.value = true;
    success.value = false;
    error.value = '';
    const payload = {
        expiry_items: {
            enabled: form.value.expiry_items.enabled,
            role_ids: (form.value.expiry_items.roles || []).map((r) => (r && typeof r === 'object' && 'id' in r ? r.id : r)),
            notify_expired: form.value.expiry_items.notify_expired,
            notify_expiring_6_months: form.value.expiry_items.notify_expiring_6_months,
            notify_expiring_1_year: form.value.expiry_items.notify_expiring_1_year,
            send_time: normalizeTime(form.value.expiry_items.send_time),
            expiring_6_months_interval_days: Math.max(1, Math.min(365, parseInt(form.value.expiry_items.expiring_6_months_interval_days, 10) || 4)),
            expiring_1_year_interval_days: Math.max(1, Math.min(365, parseInt(form.value.expiry_items.expiring_1_year_interval_days, 10) || 14)),
        },
    };
    router.put(route('settings.email-notifications.update'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            success.value = true;
            error.value = '';
        },
        onError: (errors) => {
            error.value = Object.values(errors).flat().join(' ') || 'Failed to save';
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>
