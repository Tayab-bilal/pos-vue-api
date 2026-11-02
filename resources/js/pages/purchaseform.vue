<template>
    <PageHeader
        :title="isEdit ? 'Edit Purchase' : 'New Purchase'"
        :subtitle="isEdit ? 'Update this purchase. Stock is adjusted automatically.' : 'Record products bought from a supplier'"
    >
        <RouterLink :to="isEdit ? `/purchases/${purchaseId}` : '/purchases'" class="btn btn-secondary">
            {{ isEdit ? 'Back to Purchase' : 'Back to Purchases' }}
        </RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Supplier and date -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Supplier &amp; Date</h3>
            </div>
            <div class="card-body space-y-4">
                <div class="flex flex-wrap gap-6 text-sm text-slate-700">
                    <label class="flex items-center gap-2">
                        <input v-model="supplierMode" type="radio" value="saved" class="h-4 w-4" @change="onModeChange" />
                        Saved supplier
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="supplierMode" type="radio" value="onetime" class="h-4 w-4" @change="onModeChange" />
                        One-time supplier
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <template v-if="supplierMode === 'saved'">
                        <div class="sm:col-span-2">
                            <label class="form-label">Supplier *</label>
                            <select v-model="form.supplier_id" class="form-input">
                                <option value="">Select supplier</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                    {{ s.name }}{{ s.company_name ? ` (${s.company_name})` : '' }}
                                </option>
                            </select>
                            <p v-if="errors.supplier_id" class="form-error">{{ errors.supplier_id[0] }}</p>
                            <p v-if="errors.supplier_name" class="form-error">{{ errors.supplier_name[0] }}</p>
                        </div>
                    </template>

                    <template v-else>
                        <div>
                            <label class="form-label">Supplier Name *</label>
                            <input v-model="form.supplier_name" type="text" class="form-input" placeholder="e.g. Local market vendor" />
                            <p v-if="errors.supplier_name" class="form-error">{{ errors.supplier_name[0] }}</p>
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="form.supplier_phone" type="text" class="form-input" placeholder="Optional" />
                            <p v-if="errors.supplier_phone" class="form-error">{{ errors.supplier_phone[0] }}</p>
                        </div>
                    </template>

                    <div>
                        <label class="form-label">Purchase Date *</label>
                        <input v-model="form.purchase_date" type="date" class="form-input" />
                        <p v-if="errors.purchase_date" class="form-error">{{ errors.purchase_date[0] }}</p>
                    </div>
                </div>

                <p v-if="supplierMode === 'onetime'" class="text-xs text-slate-500">
                    A one-time supplier is not saved in your Suppliers list, and must be paid in full. Use a saved supplier to buy on credit.
                </p>
            </div>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Items</h3>
                <button type="button" class="btn btn-secondary px-3! py-1!" @click="addRow">
                    <AppIcon name="plus" size="h-4 w-4" />
                    Add Product
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="min-w-56">Product</th>
                            <th class="w-32 text-right">Quantity</th>
                            <th class="w-36 text-right">Unit Cost</th>
                            <th class="w-36 text-right">Line Total</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.key" class="align-top">
                            <td>
                                <select v-model="row.product_id" class="form-input" @change="onProductChange(row)">
                                    <option value="">Select product</option>
                                    <option v-for="p in availableProducts(row)" :key="p.id" :value="p.id">
                                        {{ p.name }} ({{ p.sku }})
                                    </option>
                                </select>
                                <p v-if="errors[`items.${index}.product_id`]" class="form-error">
                                    {{ errors[`items.${index}.product_id`][0] }}
                                </p>
                            </td>
                            <td>
                                <input v-model="row.quantity" type="number" min="0.01" step="0.01" class="form-input text-right" />
                                <p v-if="errors[`items.${index}.quantity`]" class="form-error">
                                    {{ errors[`items.${index}.quantity`][0] }}
                                </p>
                            </td>
                            <td>
                                <input v-model="row.unit_cost" type="number" min="0" step="0.01" class="form-input text-right" />
                                <p v-if="errors[`items.${index}.unit_cost`]" class="form-error">
                                    {{ errors[`items.${index}.unit_cost`][0] }}
                                </p>
                            </td>
                            <td class="pt-5 text-right font-medium text-slate-800">{{ money(lineTotal(row)) }}</td>
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
        </div>

        <!-- Payment -->
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Notes</h3>
                </div>
                <div class="card-body">
                    <textarea v-model="form.notes" rows="5" class="form-input" placeholder="Optional"></textarea>
                    <p v-if="errors.notes" class="form-error">{{ errors.notes[0] }}</p>
                </div>
            </div>

            <div class="card">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Payment Summary</h3>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Subtotal</span>
                        <span class="font-medium text-slate-800">{{ money(subtotal) }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="text-slate-500" for="discount">Discount</label>
                        <div class="w-40">
                            <input id="discount" v-model="form.discount" type="number" min="0" step="0.01" class="form-input text-right" />
                        </div>
                    </div>
                    <p v-if="errors.discount" class="form-error text-right">{{ errors.discount[0] }}</p>

                    <div class="flex justify-between border-t border-slate-100 pt-3 text-base font-semibold">
                        <span class="text-slate-800">Total</span>
                        <span class="text-slate-800">{{ money(total) }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="text-slate-500" for="paid">Paid Amount</label>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-secondary px-3! py-1!" @click="payInFull">Pay in full</button>
                            <div class="w-36">
                                <input id="paid" v-model="form.paid_amount" type="number" min="0" step="0.01" class="form-input text-right" />
                            </div>
                        </div>
                    </div>
                    <p v-if="errors.paid_amount" class="form-error text-right">{{ errors.paid_amount[0] }}</p>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Due</span>
                        <span class="font-medium" :class="due > 0 ? 'text-red-600' : 'text-slate-800'">{{ money(due) }}</span>
                    </div>

                    <p v-if="supplierMode === 'onetime' && due > 0" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        A one-time supplier must be paid in full. Increase the paid amount or select a saved supplier.
                    </p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-end gap-2">
                <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                <RouterLink :to="isEdit ? `/purchases/${purchaseId}` : '/purchases'" class="btn btn-secondary">Cancel</RouterLink>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    {{ saving ? 'Saving...' : isEdit ? 'Update Purchase' : 'Save Purchase' }}
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
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const purchaseId = route.params.id ?? null;
const isEdit = computed(() => !!purchaseId);

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const formError = ref('');

const suppliers = ref([]);
const products = ref([]);

const supplierMode = ref('saved'); // 'saved' or 'onetime'

// Local date in YYYY-MM-DD format
const today = () => new Date().toLocaleDateString('en-CA');

const form = reactive({
    supplier_id: '',
    supplier_name: '',
    supplier_phone: '',
    purchase_date: today(),
    discount: 0,
    paid_amount: 0,
    notes: '',
});

let rowKey = 0;
const newRow = () => ({ key: ++rowKey, product_id: '', quantity: 1, unit_cost: 0 });
const rows = ref([newRow()]);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

// These totals are only for display. The server recalculates everything.
const lineTotal = (row) => round2((Number(row.quantity) || 0) * (Number(row.unit_cost) || 0));
const subtotal = computed(() => round2(rows.value.reduce((sum, row) => sum + lineTotal(row), 0)));
const total = computed(() => round2(Math.max(subtotal.value - (Number(form.discount) || 0), 0)));
const due = computed(() => round2(Math.max(total.value - (Number(form.paid_amount) || 0), 0)));

// A product can only be picked once, so hide products already used in other rows
function availableProducts(row) {
    const used = rows.value.filter((r) => r.key !== row.key).map((r) => r.product_id);
    return products.value.filter((p) => !used.includes(p.id));
}

function onProductChange(row) {
    const product = products.value.find((p) => p.id === row.product_id);
    if (product) row.unit_cost = product.cost_price;
}

function addRow() {
    rows.value.push(newRow());
}

function removeRow(index) {
    if (rows.value.length > 1) rows.value.splice(index, 1);
}

function payInFull() {
    form.paid_amount = total.value;
}

function onModeChange() {
    form.supplier_id = '';
    form.supplier_name = '';
    form.supplier_phone = '';
    errors.value = {};
}

async function loadLookups() {
    const [s, p] = await Promise.all([
        api.get('/api/suppliers', { params: { per_page: 100, is_active: 1 } }),
        api.get('/api/products', { params: { per_page: 100, is_active: 1 } }),
    ]);
    suppliers.value = s.data.data.data;
    products.value = p.data.data.data;
}

async function loadPurchase() {
    const { data } = await api.get(`/api/purchases/${purchaseId}`);
    const p = data.data;

    // A cancelled purchase cannot be edited
    if (p.status === 'cancelled') {
        router.replace(`/purchases/${purchaseId}`);
        return false;
    }

    supplierMode.value = p.supplier_id ? 'saved' : 'onetime';
    Object.assign(form, {
        supplier_id: p.supplier_id ?? '',
        supplier_name: p.supplier_id ? '' : p.supplier_name ?? '',
        supplier_phone: p.supplier_id ? '' : p.supplier_phone ?? '',
        purchase_date: p.purchase_date,
        discount: Number(p.discount),
        paid_amount: Number(p.paid_amount),
        notes: p.notes ?? '',
    });

    // Keep the saved supplier selectable even if it was deactivated later
    if (p.supplier_id && !suppliers.value.some((s) => s.id === p.supplier_id)) {
        suppliers.value.push({ id: p.supplier_id, name: p.supplier_name });
    }

    // Keep every product on this purchase selectable even if it was deactivated later
    rows.value = p.items.map((line) => {
        if (!products.value.some((x) => x.id === line.product_id)) {
            products.value.push({
                id: line.product_id,
                name: line.product?.name ?? 'Unknown product',
                sku: line.product?.sku ?? '',
                cost_price: line.unit_cost,
            });
        }
        return { key: ++rowKey, product_id: line.product_id, quantity: Number(line.quantity), unit_cost: Number(line.unit_cost) };
    });

    return true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    formError.value = '';

    const payload = {
        supplier_id: supplierMode.value === 'saved' ? form.supplier_id || null : null,
        supplier_name: supplierMode.value === 'onetime' ? form.supplier_name || null : null,
        supplier_phone: supplierMode.value === 'onetime' ? form.supplier_phone || null : null,
        purchase_date: form.purchase_date,
        discount: Number(form.discount) || 0,
        paid_amount: Number(form.paid_amount) || 0,
        notes: form.notes || null,
        items: rows.value.map((r) => ({
            product_id: r.product_id || null,
            quantity: r.quantity,
            unit_cost: r.unit_cost,
        })),
    };

    try {
        if (isEdit.value) {
            await api.put(`/api/purchases/${purchaseId}`, payload);
        } else {
            await api.post('/api/purchases', payload);
        }
        router.push({ path: '/purchases', query: { saved: isEdit.value ? 'updated' : 'created' } });
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
        await loadLookups();
        if (isEdit.value) {
            const ok = await loadPurchase();
            if (!ok) return;
        }
    } catch {
        if (isEdit.value) {
            router.replace('/purchases');
            return;
        }
        formError.value = 'Could not load suppliers and products. Please refresh the page.';
    }
    loading.value = false;
});
</script>