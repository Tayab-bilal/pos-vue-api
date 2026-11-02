<template>
    <PageHeader title="Stock" subtitle="Current stock levels, value and history">
        <RouterLink to="/stock/adjustments/create" class="btn btn-primary">
            <AppIcon name="plus" size="h-4 w-4" />
            New Adjustment
        </RouterLink>
    </PageHeader>

    <StockTabs />

    <!-- Summary cards -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">Total Products</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ Number(summary.total_products ?? 0) }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">Stock Value (at cost)</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(summary.stock_value) }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">Retail Value</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(summary.retail_value) }}</p>
            </div>
        </div>
        <button type="button" class="card text-left transition hover:border-amber-300" @click="status = 'low'">
            <div class="card-body">
                <p class="text-sm text-slate-500">Low Stock</p>
                <p class="mt-2 text-2xl font-bold text-amber-600">{{ Number(summary.low_stock ?? 0) }}</p>
            </div>
        </button>
        <button type="button" class="card text-left transition hover:border-red-300" @click="status = 'out'">
            <div class="card-body">
                <p class="text-sm text-slate-500">Out of Stock</p>
                <p class="mt-2 text-2xl font-bold text-red-600">{{ Number(summary.out_of_stock ?? 0) }}</p>
            </div>
        </button>
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs" placeholder="Search name, SKU or barcode..." />
            <select v-model="categoryFilter" class="form-input max-w-48">
                <option value="">All categories</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="status" class="form-input max-w-44">
                <option value="">All stock levels</option>
                <option value="in">In stock</option>
                <option value="low">Low stock</option>
                <option value="out">Out of stock</option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-right">Stock</th>
                        <th class="text-right">Alert Level</th>
                        <th class="text-right">Cost</th>
                        <th class="text-right">Stock Value</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="py-10 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="9" class="py-10 text-center text-slate-500">No products found.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="(item, index) in items" :key="item.id">
                            <td class="text-slate-500">{{ (meta.from ?? 1) + index }}</td>
                            <td>
                                <RouterLink :to="`/products/${item.id}`" class="font-medium text-slate-800 hover:text-brand-700">
                                    {{ item.name }}
                                </RouterLink>
                                <p class="text-xs text-slate-400">{{ item.sku }}</p>
                            </td>
                            <td class="text-slate-500">{{ item.category?.name }}</td>
                            <td class="text-right font-medium text-slate-800">
                                {{ Number(item.stock_quantity) }} {{ item.unit?.short_name }}
                            </td>
                            <td class="text-right text-slate-500">{{ Number(item.alert_quantity) }}</td>
                            <td class="text-right text-slate-500">{{ money(item.cost_price) }}</td>
                            <td class="text-right text-slate-800">{{ money(Number(item.stock_quantity) * Number(item.cost_price)) }}</td>
                            <td>
                                <span class="badge" :class="level(item).class">{{ level(item).label }}</span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="`/stock/movements?product_id=${item.id}`" class="icon-btn" title="History" aria-label="History">
                                        <AppIcon name="ledgers" size="h-4 w-4" />
                                    </RouterLink>
                                    <RouterLink
                                        :to="`/stock/adjustments/create?product_id=${item.id}`"
                                        class="icon-btn"
                                        title="Adjust stock"
                                        aria-label="Adjust stock"
                                    >
                                        <AppIcon name="pencil" size="h-4 w-4" />
                                    </RouterLink>
                                </div>
                            </td>
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
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import StockTabs from '../components/ui/stocktabs.vue';
import api from '../services/api';

const items = ref([]);
const meta = ref({});
const summary = ref({});
const categories = ref([]);
const loading = ref(false);

const search = ref('');
const categoryFilter = ref('');
const status = ref('');
const page = ref(1);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function level(item) {
    const stock = Number(item.stock_quantity);
    if (stock <= 0) return { label: 'Out of stock', class: 'badge-danger' };
    if (stock <= Number(item.alert_quantity)) return { label: 'Low stock', class: 'badge-warning' };
    return { label: 'In stock', class: 'badge-success' };
}

async function loadCategories() {
    try {
        const { data } = await api.get('/api/categories', { params: { per_page: 100 } });
        categories.value = data.data.data;
    } catch {
        categories.value = [];
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/stock', {
            params: {
                search: search.value,
                category_id: categoryFilter.value,
                status: status.value,
                page: page.value,
            },
        });
        summary.value = data.summary ?? {};
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
watch([categoryFilter, status], () => {
    page.value === 1 ? load() : (page.value = 1);
});
watch(page, load);

onMounted(() => {
    loadCategories();
    load();
});
</script>