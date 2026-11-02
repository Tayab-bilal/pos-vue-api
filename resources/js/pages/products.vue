<template>
    <PageHeader title="Products" subtitle="Manage your product catalog and stock levels">
        <RouterLink to="/products/create" class="btn btn-primary">
            <AppIcon name="plus" size="h-4 w-4" />
            Add Product
        </RouterLink>
    </PageHeader>

    <div v-if="notice"
        class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ notice }}
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs"
                placeholder="Search name, SKU or barcode..." />
            <select v-model="categoryFilter" class="form-input max-w-48">
                <option value="">All categories</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="status" class="form-input max-w-40">
                <option value="">All status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="lowStock" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                Low stock only
            </label>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th class="text-right">Cost</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Stock</th>
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
                                <RouterLink :to="`/products/${item.id}`"
                                    class="font-medium text-slate-800 hover:text-brand-700">{{ item.name }}</RouterLink>
                                <p v-if="item.barcode" class="text-xs text-slate-400">{{ item.barcode }}</p>
                            </td>
                            <td class="text-slate-500">{{ item.sku }}</td>
                            <td class="text-slate-500">{{ item.category?.name }}</td>
                            <td class="text-right text-slate-500">{{ money(item.cost_price) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(item.sale_price) }}</td>
                            <td class="text-right">
                                <span class="badge" :class="stockClass(item)">
                                    {{ Number(item.stock_quantity) }} {{ item.unit?.short_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="item.is_active ? 'badge-success' : 'badge-danger'">
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="`/products/${item.id}`" class="icon-btn" title="View"
                                        aria-label="View">
                                        <AppIcon name="eye" size="h-4 w-4" />
                                    </RouterLink>
                                    <RouterLink :to="`/products/${item.id}/edit`" class="icon-btn" title="Edit"
                                        aria-label="Edit">
                                        <AppIcon name="pencil" size="h-4 w-4" />
                                    </RouterLink>
                                    <button class="icon-btn icon-btn-danger" title="Delete" aria-label="Delete"
                                        @click="askDelete(item)">
                                        <AppIcon name="trash" size="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
            <p>Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}</p>
            <div class="flex items-center gap-2">
                <button class="btn btn-secondary px-3! py-1!" :disabled="page <= 1" @click="page--">Previous</button>
                <span>Page {{ page }} / {{ meta.last_page ?? 1 }}</span>
                <button class="btn btn-secondary px-3! py-1!" :disabled="page >= (meta.last_page ?? 1)"
                    @click="page++">Next</button>
            </div>
        </div>
    </div>

    <!-- Delete confirmation -->
    <AppModal v-model="showDelete" title="Delete Product" width="max-w-md">
        <p class="text-sm text-slate-600">
            Are you sure you want to delete <strong>{{ toDelete?.name }}</strong>? This action cannot be undone.
        </p>
        <template #footer>
            <button class="btn btn-secondary" @click="showDelete = false">Cancel</button>
            <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">
                {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import AppModal from '../components/ui/appmodel.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const items = ref([]);
const meta = ref({});
const loading = ref(false);
const deleting = ref(false);
const notice = ref('');

const search = ref('');
const categoryFilter = ref('');
const status = ref('');
const lowStock = ref(false);
const page = ref(1);

const categories = ref([]);

const showDelete = ref(false);
const toDelete = ref(null);

const money = (value) => Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function stockClass(item) {
    const stock = Number(item.stock_quantity);
    if (stock <= 0) return 'badge-danger';
    if (stock <= Number(item.alert_quantity)) return 'badge-warning';
    return 'badge-success';
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
        const { data } = await api.get('/api/products', {
            params: {
                search: search.value,
                category_id: categoryFilter.value,
                is_active: status.value,
                low_stock: lowStock.value ? 1 : '',
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

function flash(message) {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 3000);
}

function askDelete(item) {
    toDelete.value = item;
    showDelete.value = true;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        await api.delete(`/api/products/${toDelete.value.id}`);
        showDelete.value = false;
        flash('Product deleted successfully.');
        if (items.value.length === 1 && page.value > 1) page.value--;
        await load();
    } finally {
        deleting.value = false;
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
watch([categoryFilter, status, lowStock], () => {
    page.value === 1 ? load() : (page.value = 1);
});
watch(page, load);

onMounted(() => {
    // Show a success message sent from the Add/Edit page
    const saved = route.query.saved;
    if (saved) {
        flash(saved === 'created' ? 'Product created successfully.' : 'Product updated successfully.');
        router.replace({ path: '/products' });
    }
    loadCategories();
    load();
});
</script>