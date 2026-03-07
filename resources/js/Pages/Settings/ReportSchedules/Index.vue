<template>
    <AuthenticatedLayout title="Report Schedules" description="Configure when scheduled reports run" img="/assets/images/settings.png">
        <Head title="Report Schedules" />
        <div class="p-6 max-w-[1400px] mx-auto">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Report Schedules</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Set when automated reports run. Use a single cron entry: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono text-slate-800">* * * * * cd /var/www/warehouse.damalnugal.com && php artisan schedule:run >> /dev/null 2>&1</code>
                    </p>
                </div>
                <Link
                    :href="route('settings.index')"
                    class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors bg-indigo-50 px-4 py-2 rounded-lg"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Settings
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[1000px]">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-700">
                                    <th class="p-4 pl-6 font-semibold w-1/3">Report / Action</th>
                                    <th class="p-4 font-semibold w-40">Schedule Day/Quarter</th>
                                    <th class="p-4 font-semibold w-32">Time</th>
                                    <th class="p-4 font-semibold w-24 text-center">Status</th>
                                    <th class="p-4 pr-6 font-semibold w-64">Manual Run</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(scheduleDef, slug) in scheduleDefs" :key="slug" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-4 pl-6 align-top">
                                        <div class="font-medium text-slate-900">{{ scheduleDef.title }}</div>
                                        <div class="text-xs text-slate-500 mt-1 pr-4 leading-relaxed">{{ scheduleDef.description }}</div>
                                        <Link
                                            v-if="slug === 'facility_monthly_report'"
                                            :href="route('reports.facility-lmis-report')"
                                            class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2 py-1 rounded"
                                        >
                                            Create or edit LMIS reports manually →
                                        </Link>
                                    </td>
                                    <td class="p-4 align-top">
                                        <div v-if="!scheduleDef.quarterly">
                                            <div class="relative">
                                                <input
                                                    :id="`day_${slug}`"
                                                    v-model.number="form[slug].day_of_month"
                                                    type="number"
                                                    min="1"
                                                    max="28"
                                                    class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-shadow pr-12"
                                                    title="Day of month (1-28)"
                                                />
                                                <span class="absolute right-3 top-2.5 text-xs text-slate-400 font-medium">Day</span>
                                            </div>
                                            <div class="text-[10px] text-slate-400 mt-1.5 font-medium uppercase tracking-wider">Day of Month</div>
                                        </div>
                                        <div v-if="scheduleDef.quarterly">
                                            <select
                                                :id="`quarter_start_${slug}`"
                                                v-model.number="form[slug].quarter_start_month"
                                                class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-shadow"
                                                title="Quarter of year (start month)"
                                            >
                                                <option v-for="opt in quarterStartOptions" :key="opt.value" :value="opt.value">
                                                    {{ opt.label }}
                                                </option>
                                            </select>
                                            <div class="text-[10px] text-slate-400 mt-1.5 font-medium uppercase tracking-wider" :title="quarterStartDatesLabel(form[slug].quarter_start_month)">Quarter Start Month</div>
                                        </div>
                                    </td>
                                    <td class="p-4 align-top">
                                        <input
                                            :id="`time_${slug}`"
                                            v-model="form[slug].time"
                                            type="text"
                                            placeholder="01:00"
                                            maxlength="5"
                                            class="block w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 font-mono transition-shadow text-center"
                                            @blur="form[slug].time = normalizeTime(form[slug].time)"
                                            title="Time (24-hour)"
                                        />
                                        <div class="text-[10px] text-slate-400 mt-1.5 font-medium uppercase tracking-wider text-center">24-Hour Format</div>
                                    </td>
                                    <td class="p-4 align-top text-center pt-6">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input
                                                v-model="form[slug].enabled"
                                                type="checkbox"
                                                class="sr-only peer"
                                            />
                                            <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                        </label>
                                    </td>
                                    <td class="p-4 pr-6 align-top">
                                        <div v-if="slug !== 'orders_quarterly'" class="flex flex-col gap-2">
                                            <div v-if="scheduleDef.monthlyReport" class="flex items-center gap-2">
                                                <input
                                                    :id="`run_month_${slug}`"
                                                    v-model="runMonthBySlug[slug]"
                                                    type="month"
                                                    class="block w-full text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 px-2 py-1.5 transition-shadow text-slate-600 h-[34px]"
                                                    :disabled="runningSlug === slug"
                                                    title="Target Month (Leave empty for previous month)"
                                                />
                                                <button
                                                    type="button"
                                                    :disabled="runningSlug === slug"
                                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all h-[34px] whitespace-nowrap"
                                                    @click="runScheduleNow(slug)"
                                                >
                                                    <svg v-if="runningSlug === slug" class="animate-spin w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 12 12 12s12-5.373 12-12h-4a8 8 0 01-8 8z" />
                                                    </svg>
                                                    <svg v-else class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ runningSlug === slug ? 'Running...' : 'Run Now' }}
                                                </button>
                                            </div>
                                            <div v-else>
                                                 <button
                                                    type="button"
                                                    :disabled="runningSlug === slug"
                                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all h-[34px] w-full"
                                                    @click="runScheduleNow(slug)"
                                                >
                                                    <svg v-if="runningSlug === slug" class="animate-spin w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 12 12 12s12-5.373 12-12h-4a8 8 0 01-8 8z" />
                                                    </svg>
                                                    <svg v-else class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ runningSlug === slug ? 'Processing...' : 'Run Now' }}
                                                </button>
                                            </div>
                                            <div v-if="scheduleDef.monthlyReport" class="text-[10px] text-slate-400 leading-tight">
                                                Optional: Select month, leave blank for previous month.
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-slate-400 italic py-2">
                                            Manual run disabled for this task.
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            :disabled="saving"
                            class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all"
                        >
                            <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 12 12 12s12-5.373 12-12h-4a8 8 0 01-8 8z" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ saving ? 'Saving Changes...' : 'Save Settings' }}
                        </button>
                        
                        <transition
                            enter-active-class="transition ease-out duration-200"
                            enter-from-class="opacity-0 translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition ease-in duration-150"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 translate-y-1"
                        >
                            <p v-if="success" class="text-sm font-medium text-emerald-600 flex items-center gap-1.5 bg-emerald-50 px-3 py-1.5 rounded-md border border-emerald-100">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Settings successfully saved.
                            </p>
                        </transition>
                        <p v-if="error" class="text-sm font-medium text-red-600 flex items-center gap-1.5 bg-red-50 px-3 py-1.5 rounded-md border border-red-100">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            {{ error }}
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const quarterStartOptions = Array.from({ length: 12 }, (_, i) => {
    const m = i + 1;
    const dates = [m, m + 3, m + 6, m + 9].map((x) => (x > 12 ? x - 12 : x));
    return {
        value: m,
        label: `${MONTH_NAMES[i]} (${dates.map((d) => `${MONTH_NAMES[d - 1]} 1`).join(', ')})`,
    };
});

function quarterStartDatesLabel(month) {
    if (!month || month < 1 || month > 12) return 'Jan 1, Apr 1, Jul 1, Oct 1';
    const dates = [month, month + 3, month + 6, month + 9].map((x) => (x > 12 ? x - 12 : x));
    return dates.map((d) => `${MONTH_NAMES[d - 1]} 1`).join(', ');
}

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
        description: 'Generates facility monthly (LMIS) reports from facility_inventory_movements for all facilities. Runs on the configured day and time for the previous month.',
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
            quarter_start_month: def.quarterly ? Math.max(1, Math.min(12, parseInt(s.quarter_start_month, 10) || 1)) : undefined,
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
        } else {
            payload[slug].quarter_start_month = Math.max(1, Math.min(12, parseInt(form.value[slug].quarter_start_month, 10) || 1));
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