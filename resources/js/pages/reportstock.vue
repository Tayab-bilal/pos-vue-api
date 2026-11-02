<template>
    <ReportShell
        title="Stock Valuation"
        subtitle="What the stock in hand is worth right now"
        period-text="As of today"
        :show-filter="false"
        :ready="!!report"
        :loading="loading"
        :error="error"
    >
        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="card in cards" :key="card.label" class="card print:break-inside-avoid">
                <div class="card-body">
                    <p class="text-sm text-slate-500">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="card.class ?? 'text-slate-800'">{{ card.value }}</p>
                    <p v-if="card.hint" class="mt-1 text-xs text-slate-400">{{ card.hint }}</p>
                </div>
            </div>
        </div>

        <!-- By category -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">By Category</h3>
                <p class="text-xs text-slate-500">Potential profit is the retail value minus the cost value, if all the stock sells at its listed price</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-right">Products</th>
                            <th class="text-right">Cost Value</th>
                            <th class="text-right">Retail Value</th>
                            <th class="text-right">Potential Profit</th>
                            <th>Share of cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!report.categories.length">
                            <td colspan="6" class="py-6 text-center text-slate-500">No products yet.</td>
                        </tr>
                        <tr v-for="row in report.categories" :key="`${row.id}-${row.name}`" class="print:break-inside-avoid">
                            <td class="font-medium text-slate-800">{{ row.name }}</td>
                            <td class="text-right text-slate-500">{{ row.products }}</td>
                            <td class="text-right text-slate-800">{{ money(row.cost_value) }}</td>
                            <td class="text-right text-slate-500">{{ money(row.retail_value) }}</td>
                            <td class="text-right" :class="profitClass(row.potential_profit)">{{ signedMoney(row.potential_profit) }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 rounded bg-slate-100">
                                        <div class="h-2 rounded bg-brand-500" :style="{ width: `${share(row)}%` }"></div>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ percent(share(row)) }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="report.categories.length">
                        <tr class="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-800">
                            <td>Total</td>
                            <td class="text-right">{{ report.totals.products }}</td>
                            <td class="text-right">{{ money(report.totals.cost_value) }}</td>
                            <td class="text-right">{{ money(report.totals.retail_value) }}</td>
                            <td class="text-right" :class="profitClass(report.totals.potential_profit)">{{ signedMoney(report.totals.potential_profit) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Low stock and out of stock -->
        <div class="grid gap-6 lg:grid-cols-2">
            <div v-for="list in lists" :key="list.title" class="card">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">{{ list.title }} ({{ list.count }})</h3>
                    <p v-if="list.count > list.items.length" class="text-xs text-slate-500">Showing the first {{ list.items.length }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">In stock</th>
                                <th class="text-right">Alert level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!list.items.length">
                                <td colspan="3" class="py-6 text-center text-slate-500">Nothing here. Good.</td>
                            </tr>
                            <tr v-for="item in list.items" :key="item.id" class="print:break-inside-avoid">
                                <td>
                                    <RouterLink :to="`/products/${item.id}`" class="font-medium text-slate-800 hover:text-brand-700">{{ item.name }}</RouterLink>
                                    <p class="text-xs text-slate-400">{{ item.sku }}</p>
                                </td>
                                <td class="text-right font-medium" :class="list.danger ? 'text-red-600' : 'text-amber-600'">
                                    {{ Number(item.stock) }} {{ item.unit }}
                                </td>
                                <td class="text-right text-slate-500">{{ Number(item.alert) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <p class="text-sm text-slate-500 print:hidden">
            For every stock change, open
            <RouterLink to="/stock" class="text-brand-700 underline">Stock</RouterLink>.
        </p>
    </ReportShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import ReportShell from '../components/ui/reportshell.vue';
import { money, percent, profitClass, signedMoney } from '../config/reports';
import api from '../services/api';

const report = ref(null);
const loading = ref(false);
const error = ref('');

const cards = computed(() => {
    const t = report.value.totals;

    return [
        { label: 'Stock Value (at cost)', value: money(t.cost_value), hint: `${t.products} products` },
        { label: 'Retail Value', value: money(t.retail_value), hint: 'At the listed selling prices' },
        { label: 'Potential Profit', value: signedMoney(t.potential_profit), class: profitClass(t.potential_profit) },
        { label: 'Products', value: Number(t.products).toLocaleString('en-US') },
        { label: 'Low Stock', value: Number(t.low_stock).toLocaleString('en-US'), class: 'text-amber-600', hint: 'At or below the alert level' },
        { label: 'Out of Stock', value: Number(t.out_of_stock).toLocaleString('en-US'), class: 'text-red-600' },
    ];
});

const lists = computed(() => [
    { title: 'Low Stock', count: report.value.totals.low_stock, items: report.value.low_stock, danger: false },
    { title: 'Out of Stock', count: report.value.totals.out_of_stock, items: report.value.out_of_stock, danger: true },
]);

// Each category's part of the total stock cost
const share = (row) => {
    const total = Number(report.value.totals.cost_value);
    return total > 0 ? (Number(row.cost_value) / total) * 100 : 0;
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await api.get('/api/reports/stock-valuation');
        report.value = data.data;
    } catch {
        error.value = 'Could not load the report. Please try again.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>