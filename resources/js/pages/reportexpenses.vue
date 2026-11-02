<template>
    <ReportShell
        v-model:from="from"
        v-model:to="to"
        title="Expenses Report"
        subtitle="Where the money went"
        :ready="!!report"
        :loading="loading"
        :error="error"
    >
        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card print:break-inside-avoid">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Total Expenses</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(report.summary.total) }}</p>
                </div>
            </div>
            <div class="card print:break-inside-avoid">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Number of Expenses</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ Number(report.summary.count) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Cancelled expenses are not counted</p>
                </div>
            </div>
            <div class="card print:break-inside-avoid">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Average per Expense</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(average) }}</p>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card print:break-inside-avoid">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Expenses per Day</h3>
            </div>
            <div class="card-body">
                <BarChart :items="chartItems" :format="money" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- By category -->
            <div class="card print:break-inside-avoid">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">By Category</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-right">Expenses</th>
                                <th class="text-right">Total</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!report.by_category.length">
                                <td colspan="4" class="py-6 text-center text-slate-500">No data for this period.</td>
                            </tr>
                            <tr v-for="row in report.by_category" :key="row.id">
                                <td class="font-medium text-slate-800">{{ row.name }}</td>
                                <td class="text-right text-slate-500">{{ row.count }}</td>
                                <td class="text-right font-medium text-slate-800">{{ money(row.total) }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-20 rounded bg-slate-100">
                                            <div class="h-2 rounded bg-brand-500" :style="{ width: `${row.percent}%` }"></div>
                                        </div>
                                        <span class="text-xs text-slate-500">{{ percent(row.percent) }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- By method -->
            <div class="card print:break-inside-avoid">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">By Payment Method</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th class="text-right">Expenses</th>
                                <th class="text-right">Total</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!report.by_method.length">
                                <td colspan="4" class="py-6 text-center text-slate-500">No data for this period.</td>
                            </tr>
                            <tr v-for="row in report.by_method" :key="row.method">
                                <td class="font-medium text-slate-800">{{ paymentMethods[row.method] ?? row.method }}</td>
                                <td class="text-right text-slate-500">{{ row.count }}</td>
                                <td class="text-right font-medium text-slate-800">{{ money(row.total) }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-20 rounded bg-slate-100">
                                            <div class="h-2 rounded bg-brand-500" :style="{ width: `${row.percent}%` }"></div>
                                        </div>
                                        <span class="text-xs text-slate-500">{{ percent(row.percent) }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <p class="text-sm text-slate-500 print:hidden">
            To see or change the individual expenses, open
            <RouterLink to="/expenses" class="text-brand-700 underline">Expenses</RouterLink>.
        </p>
    </ReportShell>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ReportShell from '../components/ui/reportshell.vue';
import BarChart from '../components/ui/barchart.vue';
import { formatDay, money, percent } from '../config/reports';
import { paymentMethods } from '../config/payments';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const report = ref(null);
const loading = ref(false);
const error = ref('');

// The date range lives in the address, so a refresh or a shared link keeps it
const from = ref(route.query.from ?? '');
const to = ref(route.query.to ?? '');

const average = computed(() => {
    const count = Number(report.value.summary.count);
    return count > 0 ? Number(report.value.summary.total) / count : 0;
});

const chartItems = computed(() => report.value.daily.map((d) => ({ label: formatDay(d.date), value: Number(d.total) })));

async function load() {
    // Wait until the dates make sense, so the server is not asked a question it would reject
    if (from.value && to.value && to.value < from.value) return;

    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/api/reports/expenses', {
            params: { from: from.value || undefined, to: to.value || undefined },
        });
        report.value = data.data;
    } catch {
        error.value = 'Could not load the report. Please try again.';
    } finally {
        loading.value = false;
    }
}

watch([from, to], () => {
    router.replace({ path: route.path, query: { from: from.value || undefined, to: to.value || undefined } });
    load();
});

onMounted(load);
</script>