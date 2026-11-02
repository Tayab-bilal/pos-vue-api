<template>
    <PageHeader
        :title="isEdit ? 'Edit Sale' : 'New Sale'"
        :subtitle="isEdit ? 'Update this invoice. Stock is adjusted automatically.' : 'Create an invoice for a customer'"
    >
        <RouterLink :to="isEdit ? `/sales/${saleId}` : '/sales'" class="btn btn-secondary">
            {{ isEdit ? 'Back to Sale' : 'Back to Sales' }}
        </RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Customer and date -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Customer &amp; Date</h3>
            </div>
            <div class="card-body space-y-4">
                <div class="flex flex-wrap gap-6 text-sm text-slate-700">
                    <label class="flex items-center gap-2">
                        <input v-model="customerMode" type="radio" value="saved" class="h-4 w-4" @change="onModeChange" />
                        Saved customer
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="customerMode" type="radio" value="walkin" class="h-4 w-4" @change="onModeChange" />
                        Walk-in customer
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <template v-if="customerMode === 'saved'">
                        <div class="sm:col-span-2">
                            <label class="form-label">Customer *</label>
                            <select v-model="form.customer_id" class="form-input">
                                <option value="">Select customer</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">
                                    {{ c.name }}{{ c.phone ? ` (${c.phone})` : '' }}
                                </option>
                            </select>
                            <p v-if="errors.customer_id" class="form-error">{{ errors.customer_id[0] }}</p>
                        </div>
                    </template>

                    <template v-else>
                        <div>
                            <label class="form-label">Customer Name</label>
                            <input v-model="form.customer_name" type="text" class="form-input" placeholder="Optional (defaults to Walk-in Customer)" />
                            <p v-if="errors.customer_name" class="form-error">{{ errors.customer_name[0] }}</p>
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="form.customer_phone" type="text" class="form-input" placeholder="Optional" />
                            <p v-if="errors.customer_phone" class="form-error">{{ errors.customer_phone[0] }}</p>
                        </div>
                        <label v-if="!isEdit" class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                            <input v-model="form.save_customer" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                            Save this customer for future sales (required for credit)
                        </label>
                    </template>

                    <div>
                        <label class="form-label">Sale Date *</label>
                        <input v-model="form.sale_date" type="date" class="form-input" />
                        <p v-if="errors.sale_date" class="form-error">{{ errors.sale_date[0] }}</p>
                    </div>
                </div>

                <p v-if="customerMode === 'walkin' && !form.save_customer" class="text-xs text-slate-500">
                    A walk-in customer is not saved and must pay in full. Select a saved customer to sell on credit.
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
                            <th class="min-w-64">Product</th>
                            <th class="w-32 text-right">Quantity</th>
                            <th class="w-36 text-right">Unit Price</th>
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
                                        {{ p.name }} ({{ p.sku }}) - Stock: {{ availableOf(p) }} {{ p.unit?.short_name }}
                                    </option>
                                </select>
                                <p v-if="errors[`items.${index}.product_id`]" class="form-error">
                                    {{ errors[`items.${index}.product_id`][0] }}
                                </p>
                            </td>
                            <td>
                                <input v-model="row.quantity" type="number" min="0.01" step="0.01" class="form-input text-right" />
                                <p v-if="stockOf(row) !== null" class="mt-1 text-right text-xs text-slate-400">
                                    Available: {{ stockOf(row) }}
                                </p>
                                <p v-if="errors[`items.${index}.quantity`]" class="form-error">
                                    {{ errors[`items.${index}.quantity`][0] }}
                                </p>
                            </td>
                            <td>
                                <input v-model="row.unit_price" type="number" min="0" step="0.01" class="form-input text-right" />
                                <p v-if="errors[`items.${index}.unit_price`]" class="form-error">
                                    {{ errors[`items.${index}.unit_price`][0] }}
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

                    <p v-if="needsFullPayment" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        A walk-in customer must pay in full. Increase the paid amount, or select a saved customer to sell on credit.
                    </p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-end gap-2">
                <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                <RouterLink :to="isEdit ? `/sales/${saleId}` : '/sales'" class="btn btn-secondary">Cancel</RouterLink>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    {{ saving ? 'Saving...' : isEdit ? 'Update Sale' : 'Save Sale' }}
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

const saleId = route.params.id ?? null;
const isEdit = computed(() => !!saleId);

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const formError = ref('');

const customers = ref([]);
const products = ref([]);

// When editing: quantity per product that this sale already holds, so it counts as available again
const oldQty = ref({});

const customerMode = ref('saved'); // 'saved' or 'walkin'

// Local date in YYYY-MM-DD format
const today = () => new Date().toLocaleDateString('en-CA');

const form = reactive({
    customer_id: '',
    customer_name: '',
    customer_phone: '',
    save_customer: false,
    sale_date: today(),
    discount: 0,
    paid_amount: 0,
    notes: '',
});

let rowKey = 0;
const newRow = () => ({ key: ++rowKey, product_id: '', quantity: 1, unit_price: 0 });
const rows = ref([newRow()]);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

// These totals are only for display. The server recalculates everything.
const lineTotal = (row) => round2((Number(row.quantity) || 0) * (Number(row.unit_price) || 0));
const subtotal = computed(() => round2(rows.value.reduce((sum, row) => sum + lineTotal(row), 0)));
const total = computed(() => round2(Math.max(subtotal.value - (Number(form.discount) || 0), 0)));
const due = computed(() => round2(Math.max(total.value - (Number(form.paid_amount) || 0), 0)));

// Credit is only possible for a saved customer
const canUseCredit = computed(() => customerMode.value === 'saved' || form.save_customer);
const needsFullPayment = computed(() => !canUseCredit.value && due.value > 0);

const availableOf = (product) => round2(Number(product.stock_quantity) + (oldQty.value[product.id] ?? 0));

function stockOf(row) {
    const product = products.value.find((p) => p.id === row.product_id);
    return product ? availableOf(product) : null;
}

// A product can only be picked once, so hide products already used in other rows
function availableProducts(row) {
    const used = rows.value.filter((r) => r.key !== row.key).map((r) => r.product_id);
    return products.value.filter((p) => !used.includes(p.id));
}

function onProductChange(row) {
    const product = products.value.find((p) => p.id === row.product_id);
    if (product) row.unit_price = product.sale_price;
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
    form.customer_id = '';
    form.customer_name = '';
    form.customer_phone = '';
    form.save_customer = false;
    errors.value = {};
}

async function loadLookups() {
    const [c, p] = await Promise.all([
        api.get('/api/customers', { params: { per_page: 100, is_active: 1 } }),
        api.get('/api/products', { params: { per_page: 100, is_active: 1 } }),
    ]);
    customers.value = c.data.data.data;
    products.value = p.data.data.data;
}

async function loadSale() {
    const { data } = await api.get(`/api/sales/${saleId}`);
    const s = data.data;

    // A cancelled sale cannot be edited
    if (s.status === 'cancelled') {
        router.replace(`/sales/${saleId}`);
        return false;
    }

    customerMode.value = s.customer_id ? 'saved' : 'walkin';
    Object.assign(form, {
        customer_id: s.customer_id ?? '',
        customer_name: s.customer_id || s.customer_name === 'Walk-in Customer' ? '' : s.customer_name ?? '',
        customer_phone: s.customer_id ? '' : s.customer_phone ?? '',
        save_customer: false,
        sale_date: s.sale_date,
        discount: Number(s.discount),
        paid_amount: Number(s.paid_amount),
        notes: s.notes ?? '',
    });

    // Keep the saved customer selectable even if it was deactivated later
    if (s.customer_id && !customers.value.some((c) => c.id === s.customer_id)) {
        customers.value.push({ id: s.customer_id, name: s.customer_name, phone: s.customer_phone });
    }

    // Keep every product on this sale selectable, and remember what it already holds
    const held = {};
    rows.value = s.items.map((line) => {
        held[line.product_id] = (held[line.product_id] ?? 0) + Number(line.quantity);
        if (!products.value.some((x) => x.id === line.product_id)) {
            products.value.push({
                id: line.product_id,
                name: line.product?.name ?? 'Unknown product',
                sku: line.product?.sku ?? '',
                stock_quantity: 0,
                sale_price: line.unit_price,
                unit: line.product?.unit,
            });
        }
        return { key: ++rowKey, product_id: line.product_id, quantity: Number(line.quantity), unit_price: Number(line.unit_price) };
    });
    oldQty.value = held;

    return true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    formError.value = '';

    const isWalkin = customerMode.value === 'walkin';

    const payload = {
        customer_id: !isWalkin ? form.customer_id || null : null,
        customer_name: isWalkin ? form.customer_name || null : null,
        customer_phone: isWalkin ? form.customer_phone || null : null,
        save_customer: isWalkin && !isEdit.value ? form.save_customer : false,
        sale_date: form.sale_date,
        discount: Number(form.discount) || 0,
        paid_amount: Number(form.paid_amount) || 0,
        notes: form.notes || null,
        items: rows.value.map((r) => ({
            product_id: r.product_id || null,
            quantity: r.quantity,
            unit_price: r.unit_price,
        })),
    };

    try {
        if (isEdit.value) {
            await api.put(`/api/sales/${saleId}`, payload);
        } else {
            await api.post('/api/sales', payload);
        }
        router.push({ path: '/sales', query: { saved: isEdit.value ? 'updated' : 'created' } });
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
            const ok = await loadSale();
            if (!ok) return;
        }
    } catch {
        if (isEdit.value) {
            router.replace('/sales');
            return;
        }
        formError.value = 'Could not load customers and products. Please refresh the page.';
    }
    loading.value = false;
});
</script>