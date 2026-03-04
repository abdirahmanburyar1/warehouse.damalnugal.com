<template>
    <AuthenticatedLayout title="Report Schedules" description="Configure when scheduled reports run" img="/assets/images/settings.png">
        <Head title="Report Schedules" />
        <div class="p-6 max-w-4xl">
            <Link
                :href="route('settings.index')"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 mb-6 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to settings
            </Link>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Report Schedules</h1>
            <p class="mt-1 text-sm text-slate-500 mb-8">
                Set when automated reports run. Use a single cron entry: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono">* * * * * cd /var/www/warehouse.damalnugal.com && php artisan schedule:run >> /dev/null 2>&1</code>
            </p>

            <form @submit.prevent="submit" class="space-y-6">
                <div
                    v-for="(scheduleDef, slug) in scheduleDefs"
                    :key="slug"
                    class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden"
                >
                    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center justify-between gap-4 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-semibold text-slate-900">{{ scheduleDef.title }}</h2>
                                <p class="mt-0.5 text-sm text-slate-500">{{ scheduleDef.description }}</p>
                                <Link
                                    v-if="slug === 'facility_monthly_report'"
                                    :href="route('reports.facility-lmis-report')"
                                    class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
                                >
                                    Create or edit LMIS reports manually →
                                </Link>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input
                                    v-model="form[slug].enabled"
                                    type="checkbox"
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-slate-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-slate-900"></div>
                                <span class="ms-3 text-sm font-medium text-slate-700">Enable</span>
                            </label>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-if="!scheduleDef.quarterly">
                                <label :for="`day_${slug}`" class="block text-sm font-medium text-slate-700 mb-1">Day of month (1–28)</label>
                                <input
                                    :id="`day_${slug}`"
                                    v-model.number="form[slug].day_of_month"
                                    type="number"
                                    min="1"
                                    max="28"
                                    class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                                />
                            </div>
                            <div>
                                <label :for="`time_${slug}`" class="block text-sm font-medium text-slate-700 mb-1">Time (24-hour)</label>
                                <input
                                    :id="`time_${slug}`"
                                    v-model="form[slug].time"
                                    type="text"
                                    placeholder="01:00"
                                    maxlength="5"
                                    class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 font-mono"
                                    @blur="form[slug].time = normalizeTime(form[slug].time)"
                                />
                                <p v-if="scheduleDef.quarterly" class="mt-0.5 text-xs text-slate-500">Runs on quarter start dates only: Dec 1, Mar 1, Jun 1, Sep 1.</p>
                            </div>
                        </div>
                        <!-- Run now: all schedules -->
                        <div class="pt-2 border-t border-slate-100">
                            <p class="text-xs text-slate-500 mb-2">Run this task now{{ scheduleDef.monthlyReport ? ' (optional: choose month below; defaults to previous month)' : '' }}.</p>
                            <div v-if="scheduleDef.monthlyReport" class="mb-2">
                                <label :for="`run_month_${slug}`" class="sr-only">Month for Run now</label>
                                <input
                                    :id="`run_month_${slug}`"
                                    v-model="runMonthBySlug[slug]"
                                    type="month"
                                    class="block w-40 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 px-2 py-1.5"
                                    :disabled="runningSlug === slug"
                                />
                                <span class="ml-1 text-xs text-slate-500">Leave empty for previous month</span>
                            </div>
                            <button
                                type="button"
                                :disabled="runningSlug === slug"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-slate-700 bg-slate-100 border border-slate-200 rounded-lg hover:bg-slate-200 focus:ring-2 focus:ring-slate-400/50 disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="runScheduleNow(slug)"
                            >
                                <svg v-if="runningSlug === slug" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 12 12 12s12-5.373 12-12h-4a8 8 0 01-8 8z" />
                                </svg>
                                {{ runningSlug === slug ? 'Running…' : 'Run now' }}
                            </button>
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

const scheduleDefs = {
    monthly_received_report: {
        title: 'Monthly received quantities report',
        description: 'Generates the monthly report of received quantities for the previous month.',
        quarterly: false,
        monthlyReport: true,
    },
    issue_quantities: {
        title: 'Issue quantities report',
        description: 'Generates the monthly report of issued quantities (report:issue-quantities).',
        quarterly: false,
        monthlyReport: true,
    },
    monthly_consumption: {
        title: 'Monthly consumption data',
        description: 'Generates previous month consumption data from dispenses (consumption:generate).',
        quarterly: false,
    },
    inventory_monthly_report: {
        title: 'Inventory monthly report',
        description: 'Generates monthly inventory reports (inventory:generate-monthly-report).',
        quarterly: false,
        monthlyReport: true,
    },
    orders_quarterly: {
        title: 'Quarterly orders',
        description: 'Generates quarterly orders for facilities. Runs only on quarter start dates at the set time.',
        quarterly: true,
    },
    warehouse_amc: {
        title: 'Warehouse AMC',
        description: 'Generates AMC and reorder levels from issue quantity data (warehouse:generate-amc).',
        quarterly: false,
        monthlyReport: true,
    },
    facility_monthly_report: {
        title: 'Facility LMIS report',
        description: 'Generates facility monthly (LMIS) reports from facility_inventory_movements for all facilities. Runs on the configured day and time for the previous month. You can also create or edit reports manually at Reports → LMIS Report.',
        quarterly: false,
        monthlyReport: true,
    },
};

const props = defineProps({
    schedules: {
        type: Object,
        default: () => ({}),
    },
});

const saving = ref(false);
const success = ref(false);
const error = ref('');
const runningSlug = ref(null);
const runMonthBySlug = ref(
    Object.fromEntries(
        Object.keys(scheduleDefs)
            .filter((k) => scheduleDefs[k].monthlyReport)
            .map((k) => [k, ''])
    )
);

function normalizeTime(t) {
    if (!t || typeof t !== 'string') return '01:00';
    const trimmed = String(t).trim();
    const m = trimmed.match(/^(\d{1,2}):(\d{2})$/);
    if (m) {
        const h = Math.min(23, Math.max(0, parseInt(m[1], 10)));
        const min = Math.min(59, Math.max(0, parseInt(m[2], 10)));
        return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
    }
    return '01:00';
}

function buildFormFromSchedules() {
    const f = {};
    for (const slug of Object.keys(scheduleDefs)) {
        const def = scheduleDefs[slug];
        const s = props.schedules[slug] || {};
        f[slug] = {
            enabled: !!s.enabled,
            day_of_month: def.quarterly ? undefined : Math.max(1, Math.min(28, parseInt(s.day_of_month, 10) || 1)),
            time: normalizeTime(s.time || '01:00'),
        };
    }
    return f;
}

const form = ref(buildFormFromSchedules());

watch(() => props.schedules, () => {
    form.value = buildFormFromSchedules();
}, { deep: true });

function submit() {
    saving.value = true;
    success.value = false;
    error.value = '';
    const payload = {};
    for (const slug of Object.keys(scheduleDefs)) {
        const def = scheduleDefs[slug];
        payload[slug] = {
            enabled: form.value[slug].enabled,
            time: normalizeTime(form.value[slug].time),
        };
        if (!def.quarterly) {
            payload[slug].day_of_month = Math.max(1, Math.min(28, parseInt(form.value[slug].day_of_month, 10) || 1));
        }
    }
    router.put(route('settings.report-schedules.update'), payload, {
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

function runScheduleNow(slug) {
    runningSlug.value = slug;
    error.value = '';
    const payload = { slug };
    const month = runMonthBySlug.value[slug];
    if (month && typeof month === 'string' && /^\d{4}-\d{2}$/.test(month)) {
        payload.month = month;
    }
    router.post(route('settings.report-schedules.run-schedule'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            success.value = true;
        },
        onError: (errors) => {
            error.value = Object.values(errors).flat().join(' ') || 'Run failed';
        },
        onFinish: () => {
            runningSlug.value = null;
        },
    });
}
</script>
