<template>
    <AuthenticatedLayout title="Report Schedules" description="Configure when scheduled reports run" img="/assets/images/settings.png">
        <Head title="Report Schedules" />
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
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Report Schedules</h1>
            <p class="mt-1 text-sm text-slate-500 mb-8">
                Set when automated reports run. Ensure the server cron runs <code class="bg-slate-100 px-1 rounded">* * * * * php artisan schedule:run</code> so these settings take effect.
            </p>

            <form @submit.prevent="submit" class="space-y-8">
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Monthly received quantities report</h2>
                                <p class="mt-0.5 text-sm text-slate-500">Generates the monthly report of received quantities (from <code class="bg-slate-100 px-0.5">received_quantities</code>) for the previous month. Runs only on the configured day and time.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input
                                    v-model="form.monthly_received_report.enabled"
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
                            <div>
                                <label for="day_of_month" class="block text-sm font-medium text-slate-700 mb-1">Day of month (1–28)</label>
                                <input
                                    id="day_of_month"
                                    v-model.number="form.monthly_received_report.day_of_month"
                                    type="number"
                                    min="1"
                                    max="28"
                                    class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                                />
                                <p class="mt-0.5 text-xs text-slate-500">Report runs on this day each month (e.g. 1 = first day).</p>
                            </div>
                            <div>
                                <label for="time" class="block text-sm font-medium text-slate-700 mb-1">Time (24-hour)</label>
                                <input
                                    id="time"
                                    v-model="form.monthly_received_report.time"
                                    type="text"
                                    placeholder="01:00"
                                    maxlength="5"
                                    class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 font-mono"
                                    @blur="form.monthly_received_report.time = normalizeTime(form.monthly_received_report.time)"
                                />
                                <p class="mt-0.5 text-xs text-slate-500">e.g. 01:00. Use 24-hour format.</p>
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

const page = usePage();
const props = defineProps({
    monthlyReceivedReport: {
        type: Object,
        default: () => ({
            enabled: false,
            day_of_month: 1,
            time: '01:00',
        }),
    },
});

const saving = ref(false);
const success = ref(false);
const error = ref('');

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

const form = ref({
    monthly_received_report: {
        enabled: !!props.monthlyReceivedReport.enabled,
        day_of_month: Math.max(1, Math.min(28, parseInt(props.monthlyReceivedReport.day_of_month, 10) || 1)),
        time: normalizeTime(props.monthlyReceivedReport.time || '01:00'),
    },
});

watch(() => props.monthlyReceivedReport, () => {
    form.value.monthly_received_report = {
        enabled: !!props.monthlyReceivedReport.enabled,
        day_of_month: Math.max(1, Math.min(28, parseInt(props.monthlyReceivedReport.day_of_month, 10) || 1)),
        time: normalizeTime(props.monthlyReceivedReport.time || '01:00'),
    };
}, { deep: true });

function submit() {
    saving.value = true;
    success.value = false;
    error.value = '';
    const payload = {
        monthly_received_report: {
            enabled: form.value.monthly_received_report.enabled,
            day_of_month: Math.max(1, Math.min(28, parseInt(form.value.monthly_received_report.day_of_month, 10) || 1)),
            time: normalizeTime(form.value.monthly_received_report.time),
        },
    };
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
</script>
