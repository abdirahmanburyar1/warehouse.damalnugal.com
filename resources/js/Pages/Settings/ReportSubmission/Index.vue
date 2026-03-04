<template>
    <AuthenticatedLayout title="Report Submission Rate" description="Expected reports per period and ontime day for the Report Submission Rate report" img="/assets/images/settings.png">
        <Head title="Report Submission Rate Configuration" />
        <div class="p-6 max-w-2xl">
            <Link
                :href="route('settings.index')"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 mb-6 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to settings
            </Link>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Report Submission Rate</h1>
            <p class="mt-1 text-sm text-slate-500 mb-8">
                Reference configuration for the Report Submission Rate report. Data from <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono">facility_monthly_reports.submitted_at</code>, filtered by Region, District, Facility, and Period.
            </p>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-6 space-y-6">
                    <!-- Expected reports per period -->
                    <div>
                        <label for="expected_reports" class="block text-sm font-medium text-slate-700 mb-1">Expected reports (per period)</label>
                        <input
                            id="expected_reports"
                            v-model.number="form.expected_reports"
                            type="number"
                            min="1"
                            max="24"
                            class="block w-32 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                        />
                        <p class="mt-1 text-xs text-slate-500">Reports expected per period. Default: 1 (e.g. 1 per month = 12 per year for monthly).</p>
                    </div>

                    <!-- Ontime day -->
                    <div>
                        <label for="ontime_day" class="block text-sm font-medium text-slate-700 mb-1">Ontime (day of month)</label>
                        <input
                            id="ontime_day"
                            v-model.number="form.ontime_day"
                            type="number"
                            min="1"
                            max="31"
                            class="block w-32 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400"
                        />
                        <p class="mt-1 text-xs text-slate-500">Day of the following month. Submit by day 1–5 = ontime; day 6+ = late. Example: 5 means 1–5 ontime, 6+ late.</p>
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
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                    <p v-if="success" class="text-sm text-emerald-600">Settings saved.</p>
                    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, watch } from 'vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const form = reactive({
    expected_reports: props.config?.expected_reports ?? 1,
    ontime_day: props.config?.ontime_day ?? 5,
});

watch(() => props.config, (c) => {
    if (c?.expected_reports != null) form.expected_reports = c.expected_reports;
    if (c?.ontime_day != null) form.ontime_day = c.ontime_day;
}, { deep: true });

const saving = ref(false);
const success = ref(false);
const error = ref('');

function submit() {
    saving.value = true;
    success.value = false;
    error.value = '';
    router.put(route('settings.report-submission.update'), {
        expected_reports: form.expected_reports,
        ontime_day: form.ontime_day,
    }, {
        preserveScroll: true,
        onSuccess: () => { success.value = true; saving.value = false; },
        onError: (err) => { error.value = err?.message || 'Failed to save.'; saving.value = false; },
        onFinish: () => { saving.value = false; },
    });
}
</script>
