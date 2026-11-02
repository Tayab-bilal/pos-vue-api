<template>
    <ReportShell
        v-model:from="from"
        v-model:to="to"
        :title="kind.title"
        :subtitle="kind.subtitle"
        :ready="!!report"
        :loading="loading"
        :error="error"
    >
        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div v-for="card in cards" :key="card.label" class="card print:break-inside-avoid">
                <div class="card-body">
                    <p class="text-sm text-slate-500">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ card.value }}</p>
                    <p v-if="card.hint" class="mt-1 text-xs text-slate-400">{{ card.hint }}</p>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card print:break-inside-avoid">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">{{ kind.chartTitle }}</h3>
                <p class="text-xs text-slate-500">Documents minus returns, day by day</p>
            </div>
            <div class="card-body">
                <linechart :items="chartItems" :format="money" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Payment status -->
            <div class="card print:break-inside-avoid">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">By Payment Status</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-right">{{ kind.docLabel }}</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!report.by_status.length">
                                <td colspan="3" class="py-6 text-center text-slate-500">No data for this period.</td>
                            </tr>
                            <tr v-for="row in report.by_status" :key="row.status">
                                <td class="text-slate-800">{{ paymentStatusLabels[row.status] ?? row.status }}</td>
                                <td class="text-right text-slate-500">{{ row.count }}</td>
                                <td class="text-right font-medium text-slate-800">{{ money(row.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top customers or suppliers -->
            <div class="card print:break-inside-avoid">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">{{ kind.partyTitle }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>{{ kind.partyColumn }}</th>
                                <th class="text-right">{{ kind.docLabel }}</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!parties.length">
                                <td colspan="3" class="py-6 text-center text-slate-500">No data for this period.</td>
                            </tr>
                            <tr v-for="row in parties" :key="`${row.id}-${row.name}`">
                                <td>
                                    <RouterLink v-if="row.id" :to="kind.partyPath(row.id)" class="font-medium text-slate-800 hover:text-brand-700">
                                        {{ row.name }}
                                    </RouterLink>
                                    <span v-else class="text-slate-600">{{ row.name }}</span>
                                </td>
                                <td class="text-right text-slate-500">{{ row.count }}</td>
                                <td class="text-right font-medium text-slate-800">{{ money(row.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top products -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">{{ kind.productTitle }}</h3>
                <p class="text-xs text-slate-500">After the share of the {{ kind.docLabel.toLowerCase() }} discount</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">{{ kind.amountHeader }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!report.top_products.length">
                            <td colspan="4" class="py-6 text-center text-slate-500">No data for this period.</td>
                        </tr>
                        <tr v-for="(row, index) in report.top_products" :key="row.id" class="print:break-inside-avoid">
                            <td class="text-slate-500">{{ index + 1 }}</td>
                            <td>
                                <RouterLink :to="`/products/${row.id}`" class="font-medium text-slate-800 hover:text-brand-700">{{ row.name }}</RouterLink>
                                <p class="text-xs text-slate-400">{{ row.sku }}</p>
                            </td>
                            <td class="text-right text-slate-500">{{ Number(row.quantity) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(row.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Day by day -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Day by Day</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-right">{{ kind.docLabel }}</th>
                            <th class="text-right">{{ kind.amountHeader }}</th>
                            <th class="text-right">Returns</th>
                            <th class="text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!report.daily.length">
                            <td colspan="5" class="py-6 text-center text-slate-500">No data for this period.</td>
                        </tr>
                        <tr v-for="day in report.daily" :key="day.date" class="print:break-inside-avoid">
                            <td class="text-slate-800">{{ formatDate(day.date) }}</td>
                            <td class="text-right text-slate-500">{{ day.count }}</td>
                            <td class="text-right text-slate-800">{{ money(day.total) }}</td>
                            <td class="text-right text-slate-500">{{ money(day.returns) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(day.net) }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.daily.length">
                        <tr class="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-800">
                            <td>Total</td>
                            <td class="text-right">{{ dayTotals.count }}</td>
                            <td class="text-right">{{ money(dayTotals.total) }}</td>
                            <td class="text-right">{{ money(dayTotals.returns) }}</td>
                            <td class="text-right">{{ money(dayTotals.net) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </ReportShell>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ReportShell from '../components/ui/reportshell.vue';
import linachart from '../components/ui/linechart.vue';
import { formatDate, formatDay, money, paymentStatusLabels } from '../config/reports';
import api from '../services/api';
import Linechart from '../components/ui/linechart.vue';

const route = useRoute();
const router = useRouter();

// This page serves both reports. The router tells us which one.
const kinds = {
    sale: {
        title: 'Sales Report',
        subtitle: 'Invoices, returns and best sellers',
        api: '/api/reports/sales',
        docLabel: 'Invoices',
        totalLabel: 'Total Sales',
        netLabel: 'Net Sales',
        netKey: 'net_sales',
        amountHeader: 'Sales',
        chartTitle: 'Net Sales per Day',
        productTitle: 'Top Products by Sales',
        partyKey: 'top_customers',
        partyTitle: 'Top Customers',
        partyColumn: 'Customer',
        partyPath: (id) => `/customers/${id}`,
    },
    purchase: {
        title: 'Purchases Report',
        subtitle: 'Purchases, returns and main suppliers',
        api: '/api/reports/purchases',
        docLabel: 'Purchases',
        totalLabel: 'Total Purchases',
        netLabel: 'Net Purchases',
        netKey: 'net_purchases',
        amountHeader: 'Cost',
        chartTitle: 'Net Purchases per Day',
        productTitle: 'Top Products by Cost',
        partyKey: 'top_suppliers',
        partyTitle: 'Top Suppliers',
        partyColumn: 'Supplier',
        partyPath: (id) => `/suppliers/${id}`,
    },
};

const kind = computed(() => kinds[route.meta.kind]);

const report = ref(null);
const loading = ref(false);
const error = ref('');

// The date range lives in the address, so a refresh or a shared link keeps it
const from = ref(route.query.from ?? '');
const to = ref(route.query.to ?? '');

const parties = computed(() => report.value?.[kind.value.partyKey] ?? []);

const cards = computed(() => {
    const s = report.value.summary;
    const k = kind.value;

    return [
        { label: k.docLabel, value: Number(s.count).toLocaleString('en-US'), hint: `Average ${money(s.average)}` },
        { label: k.totalLabel, value: money(s.total), hint: `Before discount ${money(s.gross)}` },
        { label: 'Discounts', value: money(s.discount) },
        { label: 'Returns', value: money(s.returns_total), hint: `${s.returns_count} return${s.returns_count === 1 ? '' : 's'}` },
        { label: k.netLabel, value: money(s[k.netKey]) },
    ];
});

const chartItems = computed(() => report.value.daily.map((d) => ({ label: formatDay(d.date), value: Number(d.net) })));

const dayTotals = computed(() =>
    report.value.daily.reduce(
        (sum, d) => ({
            count: sum.count + Number(d.count),
            total: sum.total + Number(d.total),
            returns: sum.returns + Number(d.returns),
            net: sum.net + Number(d.net),
        }),
        { count: 0, total: 0, returns: 0, net: 0 },
    ),
);

async function load() {
    // Wait until the dates make sense, so the server is not asked a question it would reject
    if (from.value && to.value && to.value < from.value) return;

    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get(kind.value.api, {
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