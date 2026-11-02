<template>
    <PageHeader :title="isEdit ? 'Edit Product' : 'Add Product'" :subtitle="isEdit ? 'Update product details' : 'Create a new product in your catalog'">
        <RouterLink to="/products" class="btn btn-secondary">Back to Products</RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Basic information -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Basic Information</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label">Name *</label>
                    <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Wireless Keyboard" />
                    <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Category *</label>
                    <select v-model="form.category_id" class="form-input">
                        <option value="">Select category</option>
                        <option v-for="c in activeCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="errors.category_id" class="form-error">{{ errors.category_id[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Unit *</label>
                    <select v-model="form.unit_id" class="form-input">
                        <option value="">Select unit</option>
                        <option v-for="u in activeUnits" :key="u.id" :value="u.id">{{ u.name }} ({{ u.short_name }})</option>
                    </select>
                    <p v-if="errors.unit_id" class="form-error">{{ errors.unit_id[0] }}</p>
                </div>

                <div>
                    <label class="form-label">SKU</label>
                    <input v-model="form.sku" type="text" class="form-input" placeholder="Leave empty to auto-generate" />
                    <p v-if="errors.sku" class="form-error">{{ errors.sku[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Barcode</label>
                    <input v-model="form.barcode" type="text" class="form-input" placeholder="Optional" />
                    <p v-if="errors.barcode" class="form-error">{{ errors.barcode[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea v-model="form.description" rows="3" class="form-input" placeholder="Optional"></textarea>
                    <p v-if="errors.description" class="form-error">{{ errors.description[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Pricing and stock -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Pricing &amp; Stock</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="form-label">Cost Price *</label>
                    <input v-model="form.cost_price" type="number" min="0" step="0.01" class="form-input" />
                    <p v-if="errors.cost_price" class="form-error">{{ errors.cost_price[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Sale Price *</label>
                    <input v-model="form.sale_price" type="number" min="0" step="0.01" class="form-input" />
                    <p v-if="errors.sale_price" class="form-error">{{ errors.sale_price[0] }}</p>
                </div>

                <div v-if="!isEdit">
                    <label class="form-label">Opening Stock</label>
                    <input v-model="form.stock_quantity" type="number" min="0" step="0.01" class="form-input" />
                    <p v-if="errors.stock_quantity" class="form-error">{{ errors.stock_quantity[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Low Stock Alert Level</label>
                    <input v-model="form.alert_quantity" type="number" min="0" step="0.01" class="form-input" />
                    <p v-if="errors.alert_quantity" class="form-error">{{ errors.alert_quantity[0] }}</p>
                </div>

                <p v-if="isEdit" class="text-xs text-slate-500 sm:col-span-2 lg:col-span-4">
                    Stock quantity cannot be edited here. It changes through purchases, sales, returns and stock adjustments.
                </p>
            </div>
        </div>

        <!-- Status and actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-between gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Active (available for sale)
                </label>

                <div class="flex items-center gap-2">
                    <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                    <RouterLink to="/products" class="btn btn-secondary">Cancel</RouterLink>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        {{ saving ? 'Saving...' : isEdit ? 'Update Product' : 'Save Product' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const productId = route.params.id ?? null;
const isEdit = computed(() => !!productId);

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const formError = ref('');

const categories = ref([]);
const units = ref([]);
// Only active items are offered. When editing, a product's current
// category or unit stays visible even if it was deactivated later.
const activeCategories = computed(() => categories.value.filter((c) => c.is_active || c.id === form.category_id));
const activeUnits = computed(() => units.value.filter((u) => u.is_active || u.id === form.unit_id));

const form = reactive({
    name: '',
    category_id: '',
    unit_id: '',
    sku: '',
    barcode: '',
    description: '',
    cost_price: 0,
    sale_price: 0,
    stock_quantity: 0,
    alert_quantity: 0,
    is_active: true,
});

async function loadLookups() {
    const [c, u] = await Promise.all([
        api.get('/api/categories', { params: { per_page: 100 } }),
        api.get('/api/units', { params: { per_page: 100 } }),
    ]);
    categories.value = c.data.data.data;
    units.value = u.data.data.data;
}

async function loadProduct() {
    const { data } = await api.get(`/api/products/${productId}`);
    const p = data.data;
    Object.assign(form, {
        name: p.name,
        category_id: p.category_id,
        unit_id: p.unit_id,
        sku: p.sku ?? '',
        barcode: p.barcode ?? '',
        description: p.description ?? '',
        cost_price: p.cost_price,
        sale_price: p.sale_price,
        alert_quantity: p.alert_quantity,
        is_active: p.is_active,
    });
}

async function save() {
    saving.value = true;
    errors.value = {};
    formError.value = '';

    // Send empty optional text fields as null so unique checks ignore them
    const payload = { ...form, sku: form.sku || null, barcode: form.barcode || null };
    if (isEdit.value) delete payload.stock_quantity;

    try {
        if (isEdit.value) {
            await api.put(`/api/products/${productId}`, payload);
        } else {
            await api.post('/api/products', payload);
        }
        router.push({ path: '/products', query: { saved: isEdit.value ? 'updated' : 'created' } });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            formError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    try {
        await loadLookups();
        if (isEdit.value) await loadProduct();
    } catch {
        // If the product cannot be loaded (e.g. it was deleted), go back to the list
        router.replace('/products');
        return;
    }
    loading.value = false;
});
</script>