<template>
    <ReportShell
        v-model:from="from"
        v-model:to="to"
        title="Profit & Loss"
        subtitle="What the business earned after costs and expenses"
        :ready="!!report"
        :loading="loading"
        :error="error"
    >
        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in cards" :key="card.label" class="card print:break-inside-avoid">
                <div class="card-body">
                    <p class="text-sm text-slate-500">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="card.class ?? 'text-slate-800'">{{ card.value }}</p>
                    <p v-if="card.hint" class="mt-1 text-xs text-slate-400">{{ card.hint }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Statement -->
            <div class="card print:break-inside-avoid">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Profit and Loss Statement</h3>
                    <p class="text-xs text-slate-500">{{ periodLabel(report.from, report.to) }}</p>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div
                        v-for="row in statement"
                        :key="row.label"
                        class="flex justify-between gap-4 px-5 py-2.5"
                        :class="row.big ? 'bg-slate-50 text-base' : ''"
                    >
                        <dt :class="row.strong ? 'font-semibold text-slate-800' : 'text-slate-500'">{{ row.label }}</dt>
                        <dd class="text-right" :class="[row.strong ? 'font-semibold' : '', valueClass(row)]">{{ signedMoney(row.value) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Chart -->
            <div class="card print:break-inside-avoid">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Net Profit per Month</h3>
                    <p class="text-xs text-slate-500">A red bar below the line is a loss</p>
                </div>
                <div class="card-body">
                    <BarChart :items="chartItems" :format="signedMoney" :height="220" />
                </div>
            </div>
        </div>

        <!-- Month by month -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Month by Month</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-right">Net Sales</th>
                            <th class="text-right">Cost of Goods</th>
                            <th class="text-right">Gross Profit</th>
                            <th class="text-right">Expenses</th>
                            <th class="text-right">Stock Adjustments</th>
                            <th class="text-right">Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!report.monthly.length">
                            <td colspan="7" class="py-6 text-center text-slate-500">No data for this period.</td>
                        </tr>
                        <tr v-for="row in report.monthly" :key="row.month" class="print:break-inside-avoid">
                            <td class="font-medium text-slate-800">{{ formatMonth(row.month) }}</td>
                            <td class="text-right text-slate-800">{{ money(row.net_sales) }}</td>
                            <td class="text-right text-slate-500">{{ money(row.cogs) }}</td>
                            <td class="text-right" :class="profitClass(row.gross_profit)">{{ signedMoney(row.gross_profit) }}</td>
                            <td class="text-right text-slate-500">{{ money(row.expenses) }}</td>
                            <td class="text-right" :class="profitClass(row.stock_adjustments)">{{ signedMoney(row.stock_adjustments) }}</td>
                            <td class="text-right font-semibold" :class="profitClass(row.net_profit)">{{ signedMoney(row.net_profit) }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.monthly.length">
                        <tr class="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-800">
                            <td>Total</td>
                            <td class="text-right">{{ money(report.totals.net_sales) }}</td>
                            <td class="text-right">{{ money(report.totals.cogs) }}</td>
                            <td class="text-right" :class="profitClass(report.totals.gross_profit)">{{ signedMoney(report.totals.gross_profit) }}</td>
                            <td class="text-right">{{ money(report.totals.expenses) }}</td>
                            <td class="text-right" :class="profitClass(report.totals.stock_adjustments)">{{ signedMoney(report.totals.stock_adjustments) }}</td>
                            <td class="text-right" :class="profitClass(report.totals.net_profit)">{{ signedMoney(report.totals.net_profit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Profit per product -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Profit by Product</h3>
                <p class="text-xs text-slate-500">
                    Sold minus returned. Products with a loss are in red.
                    <span v-if="report.products_total > report.products.length">
                        Showing the best {{ report.products.length }} of {{ report.products_total }} products.
                    </span>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Revenue</th>
                            <th class="text-right">Cost</th>
                            <th class="text-right">Profit</th>
                            <th class="text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!report.products.length">
                            <td colspan="7" class="py-6 text-center text-slate-500">No data for this period.</td>
                        </tr>
                        <tr v-for="(row, index) in report.products" :key="row.id" class="print:break-inside-avoid">
                            <td class="text-slate-500">{{ index + 1 }}</td>
                            <td>
                                <RouterLink :to="`/products/${row.id}`" class="font-medium text-slate-800 hover:text-brand-700">{{ row.name }}</RouterLink>
                                <p class="text-xs text-slate-400">{{ row.sku }}</p>
                            </td>
                            <td class="text-right text-slate-500">{{ Number(row.quantity) }}</td>
                            <td class="text-right text-slate-800">{{ money(row.revenue) }}</td>
                            <td class="text-right text-slate-500">{{ money(row.cost) }}</td>
                            <td class="text-right font-medium" :class="profitClass(row.profit)">{{ signedMoney(row.profit) }}</td>
                            <td class="text-right text-slate-500">{{ percent(row.margin) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- How it is worked out -->
        <div class="card print:break-inside-avoid">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">How These Figures Are Worked Out</h3>
            </div>
            <ul class="list-disc space-y-1 px-9 py-4 text-sm text-slate-600">
                <li>Net sales are completed sales after discounts, minus sales returns. Cancelled documents are left out.</li>
                <li>Cost of goods is the cost recorded on each sale at the time it was made. Goods returned into stock give their cost back. Damaged returns keep their cost as a loss.</li>
                <li>Gross profit is net sales minus cost of goods.</li>
                <li>Net profit is gross profit minus expenses, plus stock adjustments (damage, loss and expiry reduce it, a count correction can add to it).</li>
            </ul>
        </div>
    </ReportShell>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ReportShell from '../components/ui/reportshell.vue';
import BarChart from '../components/ui/barchart.vue';
import { formatMonth, money, percent, periodLabel, profitClass, signedMoney } from '../config/reports';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const report = ref(null);
const loading = ref(false);
const error = ref('');

// The date range lives in the address, so a refresh or a shared link keeps it
const from = ref(route.query.from ?? '');
const to = ref(route.query.to ?? '');

const cards = computed(() => {
    const t = report.value.totals;

    return [
        { label: 'Net Sales', value: money(t.net_sales), hint: `Gross sales ${money(t.gross_sales)}` },
        { label: 'Gross Profit', value: signedMoney(t.gross_profit), hint: `Margin ${percent(t.gross_margin)}`, class: profitClass(t.gross_profit) },
        { label: 'Expenses', value: money(t.expenses) },
        {
            label: 'Net Profit',
            value: signedMoney(t.net_profit),
            hint: `Margin ${percent(t.net_margin)}`,
            class: Number(t.net_profit) < 0 ? 'text-red-600' : 'text-emerald-600',
        },
    ];
});

const statement = computed(() => {
    const t = report.value.totals;

    return [
        { label: 'Gross sales', value: t.gross_sales },
        { label: 'Less: discounts', value: -t.discounts },
        { label: 'Sales after discounts', value: t.sales, strong: true },
        { label: 'Less: sales returns', value: -t.returns },
        { label: 'Net sales', value: t.net_sales, strong: true },
        { label: 'Less: cost of goods sold', value: -t.cogs },
        { label: `Gross profit (margin ${percent(t.gross_margin)})`, value: t.gross_profit, strong: true, profit: true },
        { label: 'Less: expenses', value: -t.expenses },
        { label: 'Stock adjustments (damage, loss, corrections)', value: t.stock_adjustments, profit: true },
        { label: `Net profit (margin ${percent(t.net_margin)})`, value: t.net_profit, strong: true, big: true, profit: true },
    ];
});

// Money going out is shown in black, only profit lines turn red or green
function valueClass(row) {
    if (row.big) return Number(row.value) < 0 ? 'text-red-600' : 'text-emerald-600';
    return row.profit ? profitClass(row.value) : 'text-slate-800';
}

const chartItems = computed(() => report.value.monthly.map((m) => ({ label: formatMonth(m.month), value: Number(m.net_profit) })));

async function load() {
    // Wait until the dates make sense, so the server is not asked a question it would reject
    if (from.value && to.value && to.value < from.value) return;

    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/api/reports/profit-loss', {
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