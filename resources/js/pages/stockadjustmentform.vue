<template>
    <PageHeader title="New Stock Adjustment" subtitle="Correct stock after damage, loss, expiry or a physical count">
        <RouterLink to="/stock/adjustments" class="btn btn-secondary">Back to Adjustments</RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Details -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Adjustment Details</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label">Type *</label>
                    <select v-model="form.type" class="form-input">
                        <option v-for="(t, key) in adjustmentTypes" :key="key" :value="key">{{ t.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ adjustmentTypes[form.type].hint }}</p>
                    <p v-if="errors.type" class="form-error">{{ errors.type[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Date *</label>
                    <input v-model="form.adjustment_date" type="date" class="form-input" />
                    <p v-if="errors.adjustment_date" class="form-error">{{ errors.adjustment_date[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Reason</label>
                    <input v-model="form.reason" type="text" class="form-input" placeholder="e.g. Dropped during unloading, monthly stock count" />
                    <p v-if="errors.reason" class="form-error">{{ errors.reason[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea v-model="form.notes" rows="2" class="form-input" placeholder="Optional"></textarea>
                    <p v-if="errors.notes" class="form-error">{{ errors.notes[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Products</h3>
                <button type="button" class="btn btn-secondary px-3! py-1!" @click="addRow">
                    <AppIcon name="plus" size="h-4 w-4" />
                    Add Product
                </button>
            </div>

            <div class="space-y-2 border-b border-slate-100 px-5 py-4 text-sm text-slate-700">
                <p class="font-medium text-slate-800">How do you want to enter the stock?</p>
                <label class="flex items-start gap-2">
                    <input v-model="mode" type="radio" value="count" class="mt-1 h-4 w-4" />
                    <span>
                        I counted the stock
                        <span class="block text-xs text-slate-500">Enter what you actually found. The system works out the difference.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2">
                    <input v-model="mode" type="radio" value="change" class="mt-1 h-4 w-4" />
                    <span>
                        I know the change
                        <span class="block text-xs text-slate-500">
                            {{ decreaseOnly ? 'Enter how many to remove from stock.' : 'Enter how many to add (+) or remove (-).' }}
                        </span>
                    </span>
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="min-w-64">Product</th>
                            <th class="text-right">System stock</th>
                            <th class="w-40 text-right">{{ inputLabel }}</th>
                            <th class="text-right">Difference</th>
                            <th class="text-right">New stock</th>
                            <th class="text-right">Value</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.key" class="align-top">
                            <td>
                                <select v-model="row.product_id" class="form-input">
                                    <option value="">Select product</option>
                                    <option v-for="p in availableProducts(row)" :key="p.id" :value="p.id">
                                        {{ p.name }} ({{ p.sku }})
                                    </option>
                                </select>
                                <p v-if="errors[`items.${index}.product_id`]" class="form-error">
                                    {{ errors[`items.${index}.product_id`][0] }}
                                </p>
                            </td>
                            <td class="pt-5 text-right text-slate-600">
                                {{ currentOf(row) === null ? '-' : `${currentOf(row)} ${productOf(row)?.unit?.short_name ?? ''}` }}
                            </td>
                            <td>
                                <input
                                    v-model="row.value"
                                    type="number"
                                    step="0.01"
                                    :min="mode === 'count' || decreaseOnly ? 0 : undefined"
                                    class="form-input text-right"
                                />
                                <p v-if="rowError(index)" class="form-error">{{ rowError(index) }}</p>
                            </td>
                            <td class="pt-5 text-right font-medium" :class="differenceClass(row)">
                                {{ differenceOf(row) === null ? '-' : signed(differenceOf(row)) }}
                            </td>
                            <td class="pt-5 text-right" :class="afterOf(row) !== null && afterOf(row) < 0 ? 'font-medium text-red-600' : 'text-slate-800'">
                                {{ afterOf(row) === null ? '-' : afterOf(row) }}
                            </td>
                            <td class="pt-5 text-right text-slate-600">{{ differenceOf(row) === null ? '-' : money(valueOf(row)) }}</td>
                            <td class="pt-3 text-right">
                                <button
                                    type="button"
                                    class="icon-btn icon-btn-danger"
                                    title="Remove"
                                    aria-label="Remove"
                                    :disabled="rows.length === 1"
                                    @click="removeRow(index)"
                                >
                                    <AppIcon name="trash" size="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="errors.items" class="form-error px-5 pb-3">{{ errors.items[0] }}</p>

            <div class="flex justify-end border-t border-slate-100 px-5 py-4 text-sm">
                <div class="flex w-full max-w-xs justify-between text-base font-semibold">
                    <span class="text-slate-800">Net value change</span>
                    <span :class="totalValue < 0 ? 'text-red-600' : 'text-slate-800'">{{ money(totalValue) }}</span>
                </div>
            </div>
        </div>

        <p class="text-xs text-slate-400">
            These figures are a preview based on the stock shown now. The server checks the live stock when you save.
        </p>

        <!-- Actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-end gap-2">
                <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                <RouterLink to="/stock/adjustments" class="btn btn-secondary">Cancel</RouterLink>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save Adjustment' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import { adjustmentTypes } from '../config/stock';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const formError = ref('');

const products = ref([]);
const mode = ref('count'); // 'count' = counted quantity, 'change' = change in stock

const today = () => new Date().toLocaleDateString('en-CA');

const form = reactive({
    type: 'count_correction',
    adjustment_date: today(),
    reason: '',
    notes: '',
});

let rowKey = 0;
const newRow = (productId = '') => ({ key: ++rowKey, product_id: productId, value: '' });
const rows = ref([newRow()]);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
const signed = (n) => (n > 0 ? `+${n}` : `${n}`);

// Damage, loss and expiry can only remove stock
const decreaseOnly = computed(() => adjustmentTypes[form.type].decrease);

const inputLabel = computed(() => {
    if (mode.value === 'count') return 'Counted quantity';
    return decreaseOnly.value ? 'Quantity to remove' : 'Change (+ / -)';
});

const productOf = (row) => products.value.find((p) => p.id === row.product_id) ?? null;
const currentOf = (row) => (productOf(row) ? Number(productOf(row).stock_quantity) : null);

function differenceOf(row) {
    const current = currentOf(row);
    if (current === null || row.value === '' || row.value === null) return null;

    const value = Number(row.value);
    if (Number.isNaN(value)) return null;

    if (mode.value === 'count') return round2(value - current);
    return round2(decreaseOnly.value ? -Math.abs(value) : value);
}

const afterOf = (row) => (differenceOf(row) === null ? null : round2(currentOf(row) + differenceOf(row)));
const valueOf = (row) => (differenceOf(row) === null ? 0 : round2(differenceOf(row) * Number(productOf(row).cost_price)));
const totalValue = computed(() => round2(rows.value.reduce((sum, row) => sum + valueOf(row), 0)));

const differenceClass = (row) => {
    const d = differenceOf(row);
    if (d === null || d === 0) return 'text-slate-500';
    return d > 0 ? 'font-medium text-emerald-600' : 'font-medium text-red-600';
};

// The server reports a problem against either field name
const rowError = (index) =>
    errors.value[`items.${index}.counted_quantity`]?.[0] ?? errors.value[`items.${index}.quantity_change`]?.[0] ?? '';

// A product can only be picked once, so hide products already used in other rows
function availableProducts(row) {
    const used = rows.value.filter((r) => r.key !== row.key).map((r) => r.product_id);
    return products.value.filter((p) => !used.includes(p.id));
}

function addRow() {
    rows.value.push(newRow());
}

function removeRow(index) {
    if (rows.value.length > 1) rows.value.splice(index, 1);
}

async function save() {
    saving.value = true;
    errors.value = {};
    formError.value = '';

    const payload = {
        adjustment_date: form.adjustment_date,
        type: form.type,
        reason: form.reason || null,
        notes: form.notes || null,
        items: rows.value.map((row) => {
            const item = { product_id: row.product_id || null };
            if (row.value !== '' && row.value !== null) {
                if (mode.value === 'count') {
                    item.counted_quantity = Number(row.value);
                } else {
                    item.quantity_change = decreaseOnly.value ? -Math.abs(Number(row.value)) : Number(row.value);
                }
            }
            return item;
        }),
    };

    try {
        await api.post('/api/stock-adjustments', payload);
        router.push({ path: '/stock/adjustments', query: { saved: 'created' } });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            formError.value = e.response?.data?.message ?? 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    try {
        const { data } = await api.get('/api/products', { params: { per_page: 100 } });
        products.value = data.data.data;
    } catch {
        formError.value = 'Could not load products. Please refresh the page.';
    }

    // Opened from the Stock Overview "Adjust stock" button for one product
    const productId = Number(route.query.product_id);
    if (productId && products.value.some((p) => p.id === productId)) {
        rows.value = [newRow(productId)];
    }

    loading.value = false;
});
</script>