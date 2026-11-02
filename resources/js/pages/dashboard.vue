<template>
    <PageHeader title="Dashboard" :subtitle="`${greeting}. Here is how the business is doing, ${todayText}.`">
        <button type="button" class="btn btn-secondary" :disabled="loading" @click="load">
            {{ loading ? 'Refreshing...' : 'Refresh' }}
        </button>
        <RouterLink to="/purchases/create" class="btn btn-secondary">New Purchase</RouterLink>
        <RouterLink to="/sales/create" class="btn btn-primary">
            <AppIcon name="plus" size="h-4 w-4" />
            New Sale
        </RouterLink>
    </PageHeader>

    <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ error }}
    </div>

    <div v-if="!data && loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <!-- While the figures refresh, the old ones stay visible but faded -->
    <div v-else-if="data" class="space-y-6" :class="{ 'opacity-60': loading }">
        <!-- Credit limit warning -->
        <RouterLink
            v-if="data.receivable.over_limit > 0"
            to="/ledgers"
            class="block rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:bg-amber-100"
        >
            <strong>{{ data.receivable.over_limit }}</strong>
            customer{{ data.receivable.over_limit === 1 ? ' is' : 's are' }} over the credit limit. Open the ledgers to see who.
        </RouterLink>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <RouterLink v-for="card in cards" :key="card.label" :to="card.to" class="card block transition hover:border-brand-300">
                <div class="card-body">
                    <p class="text-sm text-slate-500">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="card.class ?? 'text-slate-800'">{{ card.value }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ card.hint }}</p>
                </div>
            </RouterLink>
        </div>

        <!-- Chart and best sellers -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Net Sales, Last 7 Days</h3>
                    <p class="text-xs text-slate-500">Sales minus returns, day by day</p>
                </div>
                <div class="card-body">
                    <linechart :items="chartItems" :format="money" :height="200" />
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <div>
                        <h3 class="font-semibold text-slate-800">Best Sellers</h3>
                        <p class="text-xs text-slate-500">{{ monthLabel }}</p>
                    </div>
                    <RouterLink to="/reports" class="text-xs text-brand-700 hover:underline">Report</RouterLink>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-if="!data.top_products.length" class="px-5 py-6 text-center text-slate-500">No sales this month yet.</li>
                    <li v-for="(item, index) in data.top_products" :key="item.id" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <RouterLink :to="`/products/${item.id}`" class="block truncate font-medium text-slate-800 hover:text-brand-700">
                                {{ index + 1 }}. {{ item.name }}
                            </RouterLink>
                            <p class="text-xs text-slate-400">{{ Number(item.quantity) }} sold</p>
                        </div>
                        <span class="font-medium text-slate-800">{{ money(item.amount) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Recent sales and stock alerts -->
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Recent Sales</h3>
                    <RouterLink to="/sales" class="text-xs text-brand-700 hover:underline">All sales</RouterLink>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th class="text-right">Total</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!data.recent_sales.length">
                                <td colspan="5" class="py-6 text-center text-slate-500">No sales yet.</td>
                            </tr>
                            <tr v-for="sale in data.recent_sales" :key="sale.id">
                                <td>
                                    <RouterLink :to="`/sales/${sale.id}`" class="font-medium text-slate-800 hover:text-brand-700">{{ sale.reference_no }}</RouterLink>
                                </td>
                                <td class="text-slate-600">
                                    {{ sale.customer_name }}
                                    <span v-if="sale.walk_in" class="text-xs text-slate-400">(walk-in)</span>
                                </td>
                                <td class="text-slate-500">{{ formatDate(sale.date) }}</td>
                                <td class="text-right font-medium text-slate-800">{{ money(sale.total) }}</td>
                                <td>
                                    <span class="badge" :class="statusClass[sale.payment_status]">{{ paymentStatusLabels[sale.payment_status] ?? sale.payment_status }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <div>
                        <h3 class="font-semibold text-slate-800">Stock Alerts</h3>
                        <p class="text-xs text-slate-500">{{ data.stock.out_of_stock }} out of stock, {{ data.stock.low_stock }} low</p>
                    </div>
                    <RouterLink to="/stock" class="text-xs text-brand-700 hover:underline">Open stock</RouterLink>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-if="!data.stock.alerts.length" class="px-5 py-6 text-center text-slate-500">All products have enough stock.</li>
                    <li v-for="item in data.stock.alerts" :key="item.id" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <RouterLink :to="`/products/${item.id}`" class="block truncate font-medium text-slate-800 hover:text-brand-700">{{ item.name }}</RouterLink>
                            <p class="text-xs text-slate-400">{{ item.sku }}, alert level {{ Number(item.alert) }}</p>
                        </div>
                        <span class="badge" :class="item.level === 'out' ? 'badge-danger' : 'badge-warning'">
                            {{ item.level === 'out' ? 'Out of stock' : `${Number(item.stock)} ${item.unit} left` }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Receivable and payable -->
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <div>
                        <h3 class="font-semibold text-slate-800">Customers Who Owe You</h3>
                        <p class="text-xs text-slate-500">{{ data.receivable.parties }} of {{ data.counts.customers }} customers have a balance</p>
                    </div>
                    <RouterLink to="/ledgers" class="text-xs text-brand-700 hover:underline">All ledgers</RouterLink>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-if="!data.receivable.top.length" class="px-5 py-6 text-center text-slate-500">Nobody owes you anything.</li>
                    <li v-for="row in data.receivable.top" :key="row.id" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <RouterLink :to="`/ledgers/customers/${row.id}`" class="block truncate font-medium text-slate-800 hover:text-brand-700">{{ row.name }}</RouterLink>
                            <p v-if="row.phone" class="text-xs text-slate-400">{{ row.phone }}</p>
                        </div>
                        <span class="font-medium text-red-600">{{ money(row.outstanding) }}</span>
                    </li>
                </ul>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <div>
                        <h3 class="font-semibold text-slate-800">Suppliers You Owe</h3>
                        <p class="text-xs text-slate-500">{{ data.payable.parties }} of {{ data.counts.suppliers }} suppliers have a balance</p>
                    </div>
                    <RouterLink to="/ledgers/suppliers" class="text-xs text-brand-700 hover:underline">All ledgers</RouterLink>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    <li v-if="!data.payable.top.length" class="px-5 py-6 text-center text-slate-500">You do not owe any supplier.</li>
                    <li v-for="row in data.payable.top" :key="row.id" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <RouterLink :to="`/ledgers/suppliers/${row.id}`" class="block truncate font-medium text-slate-800 hover:text-brand-700">{{ row.name }}</RouterLink>
                            <p v-if="row.phone" class="text-xs text-slate-400">{{ row.phone }}</p>
                        </div>
                        <span class="font-medium text-red-600">{{ money(row.outstanding) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Quick Actions</h3>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-3 xl:grid-cols-6">
                <RouterLink
                    v-for="action in actions"
                    :key="action.to"
                    :to="action.to"
                    class="flex flex-col items-center gap-2 rounded-lg border border-slate-200 px-3 py-4 text-center text-sm font-medium text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                >
                    <AppIcon :name="action.icon" size="h-6 w-6" />
                    {{ action.label }}
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import linechart from '../components/ui/linechart.vue';
import { formatDate, formatDay, money, paymentStatusLabels, profitClass, signedMoney } from '../config/reports';
import api from '../services/api';

const data = ref(null);
const loading = ref(false);
const error = ref('');

const statusClass = { paid: 'badge-success', partial: 'badge-warning', unpaid: 'badge-danger' };

const actions = [
    { label: 'New Sale', to: '/sales/create', icon: 'sales' },
    { label: 'New Purchase', to: '/purchases/create', icon: 'purchases' },
    { label: 'Add Product', to: '/products/create', icon: 'products' },
    { label: 'Receive Payment', to: '/payments/receipts/create', icon: 'customers' },
    { label: 'Add Expense', to: '/expenses/create', icon: 'banknotes' },
    { label: 'Adjust Stock', to: '/stock/adjustments/create', icon: 'stock' },
];

const hour = new Date().getHours();
const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
const todayText = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const monthLabel = computed(() =>
    data.value ? new Date(`${data.value.date}T00:00:00`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) : '',
);

const cards = computed(() => {
    const d = data.value;
    const plural = (n, word) => `${n} ${word}${Number(n) === 1 ? '' : 's'}`;

    return [
        {
            label: "Today's Net Sales",
            value: money(d.today.net_sales),
            hint: `${plural(d.today.sales_count, 'invoice')}, returns ${money(d.today.returns_total)}`,
            to: '/sales',
        },
        {
            label: "Today's Gross Profit",
            value: signedMoney(d.today.gross_profit),
            hint: 'Before expenses',
            class: profitClass(d.today.gross_profit),
            to: '/reports/profit-loss',
        },
        {
            label: 'Net Sales This Month',
            value: money(d.month.net_sales),
            hint: `${plural(d.month.sales_count, 'invoice')} in ${monthLabel.value}`,
            to: '/reports',
        },
        {
            label: 'Net Profit This Month',
            value: signedMoney(d.month.net_profit),
            hint: `After expenses of ${money(d.month.expenses)}`,
            class: Number(d.month.net_profit) < 0 ? 'text-red-600' : 'text-emerald-600',
            to: '/reports/profit-loss',
        },
        {
            label: 'Receivable',
            value: money(d.receivable.total),
            hint: `${plural(d.receivable.parties, 'customer')} owe you`,
            class: Number(d.receivable.total) > 0 ? 'text-red-600' : 'text-slate-800',
            to: '/ledgers',
        },
        {
            label: 'Payable',
            value: money(d.payable.total),
            hint: `You owe ${plural(d.payable.parties, 'supplier')}`,
            class: Number(d.payable.total) > 0 ? 'text-red-600' : 'text-slate-800',
            to: '/ledgers/suppliers',
        },
        {
            label: 'Stock Value',
            value: money(d.stock.cost_value),
            hint: `${plural(d.stock.products, 'product')}, retail ${money(d.stock.retail_value)}`,
            to: '/reports/stock-valuation',
        },
        {
            label: 'Stock Alerts',
            value: Number(d.stock.low_stock) + Number(d.stock.out_of_stock),
            hint: `${d.stock.low_stock} low, ${d.stock.out_of_stock} out of stock`,
            class: Number(d.stock.out_of_stock) > 0 ? 'text-red-600' : Number(d.stock.low_stock) > 0 ? 'text-amber-600' : 'text-slate-800',
            to: '/stock',
        },
    ];
});

const chartItems = computed(() => data.value.chart.map((d) => ({ label: formatDay(d.date), value: Number(d.net) })));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        // The day is decided here, in the browser, the same way sale dates are
        const today = new Date().toLocaleDateString('en-CA');
        const { data: response } = await api.get('/api/dashboard', { params: { today } });
        data.value = response.data;
    } catch {
        error.value = 'Could not load the dashboard. Please try again.';
    } finally {
        loading.value = false;
    }
}

// Keep the figures fresh if the page stays open
let timer;
onMounted(() => {
    load();
    timer = setInterval(load, 5 * 60 * 1000);
});
onUnmounted(() => clearInterval(timer));
</script>