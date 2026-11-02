<template>
    <PageHeader title="Stock Movements" subtitle="Every change to your stock, with the balance after it">
        <RouterLink to="/stock/adjustments/create" class="btn btn-primary">
            <AppIcon name="plus" size="h-4 w-4" />
            New Adjustment
        </RouterLink>
    </PageHeader>

    <StockTabs />

    <div v-if="productId" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
        <span>Showing history for <strong>{{ productName || 'one product' }}</strong></span>
        <RouterLink to="/stock/movements" class="underline">Show all products</RouterLink>
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs" placeholder="Search product or reference..." />
            <select v-model="type" class="form-input max-w-56">
                <option value="">All types</option>
                <option v-for="(label, key) in movementTypes" :key="key" :value="key">{{ label }}</option>
            </select>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <input v-model="dateFrom" type="date" class="form-input w-40" aria-label="From date" />
                <span>to</span>
                <input v-model="dateTo" type="date" class="form-input w-40" aria-label="To date" />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th class="text-right">In</th>
                        <th class="text-right">Out</th>
                        <th class="text-right">Balance</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="8" class="py-10 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="8" class="py-10 text-center text-slate-500">No stock movements found.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="item in items" :key="item.id">
                            <td class="whitespace-nowrap text-slate-500">{{ formatDateTime(item.created_at) }}</td>
                            <td>
                                <p class="font-medium text-slate-800">{{ item.product?.name }}</p>
                                <p class="text-xs text-slate-400">{{ item.product?.sku }}</p>
                            </td>
                            <td>
                                <span class="badge" :class="Number(item.quantity) >= 0 ? 'badge-success' : 'badge-danger'">
                                    {{ movementTypes[item.type] ?? item.type }}
                                </span>
                            </td>
                            <td>
                                <RouterLink v-if="movementLink(item)" :to="movementLink(item)" class="text-slate-700 hover:text-brand-700">
                                    {{ item.reference_no || '-' }}
                                </RouterLink>
                                <span v-else class="text-slate-500">{{ item.reference_no || '-' }}</span>
                            </td>
                            <td class="text-right font-medium text-emerald-600">{{ Number(item.quantity) > 0 ? `+${Number(item.quantity)}` : '' }}</td>
                            <td class="text-right font-medium text-red-600">{{ Number(item.quantity) < 0 ? Number(item.quantity) : '' }}</td>
                            <td class="text-right font-semibold text-slate-800">{{ Number(item.balance_after) }}</td>
                            <td class="max-w-48 truncate text-slate-500">{{ item.note || '-' }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
            <p>Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}</p>
            <div class="flex items-center gap-2">
                <button class="btn btn-secondary px-3! py-1!" :disabled="page <= 1" @click="page--">Previous</button>
                <span>Page {{ page }} / {{ meta.last_page ?? 1 }}</span>
                <button class="btn btn-secondary px-3! py-1!" :disabled="page >= (meta.last_page ?? 1)" @click="page++">Next</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import StockTabs from '../components/ui/stocktabs.vue';
import { movementTypes, movementLink } from '../config/stock';
import api from '../services/api';

const route = useRoute();

const items = ref([]);
const meta = ref({});
const loading = ref(false);

const search = ref('');
const type = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const page = ref(1);

// Opened from the Stock Overview "History" button for one product
const productId = ref(route.query.product_id ?? '');
const productName = ref('');

const formatDateTime = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

async function loadProductName() {
    productName.value = '';
    if (!productId.value) return;
    try {
        const { data } = await api.get(`/api/products/${productId.value}`);
        productName.value = data.data.name;
    } catch {
        productName.value = '';
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/stock-movements', {
            params: {
                product_id: productId.value,
                search: search.value,
                type: type.value,
                date_from: dateFrom.value,
                date_to: dateTo.value,
                page: page.value,
            },
        });
        items.value = data.data.data;
        meta.value = data.data;
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

// Wait briefly after typing so we don't send a request on every keystroke
let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        page.value === 1 ? load() : (page.value = 1);
    }, 400);
});
watch([type, dateFrom, dateTo], () => {
    page.value === 1 ? load() : (page.value = 1);
});
watch(page, load);

// "Show all products" removes the product from the address
watch(
    () => route.query.product_id,
    (value) => {
        productId.value = value ?? '';
        loadProductName();
        page.value === 1 ? load() : (page.value = 1);
    },
);

onMounted(() => {
    loadProductName();
    load();
});
</script>