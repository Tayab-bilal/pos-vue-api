<template>
    <PageHeader
        title="New Purchase Return"
        :subtitle="data ? `Return goods from purchase ${data.purchase.reference_no}` : 'Return goods to a supplier'"
    >
        <RouterLink :to="`/purchases/${purchaseId}`" class="btn btn-secondary">Back to Purchase</RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="blockedMessage" class="card">
        <div class="card-body text-sm text-slate-600">{{ blockedMessage }}</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Purchase summary -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Original Purchase</h3>
            </div>
            <dl class="grid gap-4 px-5 py-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-slate-500">Reference</dt>
                    <dd class="font-medium text-slate-800">{{ data.purchase.reference_no }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Supplier</dt>
                    <dd class="font-medium text-slate-800">
                        {{ data.purchase.supplier_name }}{{ data.purchase.supplier_id ? '' : ' (one-time)' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Purchase Total</dt>
                    <dd class="font-medium text-slate-800">{{ money(data.purchase.total) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Paid</dt>
                    <dd class="font-medium text-slate-800">{{ money(data.purchase.paid_amount) }}</dd>
                </div>
            </dl>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Items to Return</h3>
                <button type="button" class="btn btn-secondary px-3! py-1!" @click="returnAll">Return all possible</button>
            </div>

            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="min-w-48">Product</th>
                            <th class="text-right">Purchased</th>
                            <th class="text-right">Returned</th>
                            <th class="text-right">In stock</th>
                            <th class="text-right">Can return</th>
                            <th class="w-32 text-right">Return qty</th>
                            <th class="text-right">Unit Cost</th>
                            <th class="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.product_id" class="align-top" :class="{ 'opacity-50': maxQty(row) <= 0 }">
                            <td>
                                <p class="font-medium text-slate-800">{{ row.name }}</p>
                                <p class="text-xs text-slate-400">{{ row.sku }}</p>
                            </td>
                            <td class="text-right text-slate-500">{{ row.purchased }} {{ row.unit }}</td>
                            <td class="text-right text-slate-500">{{ row.returned }}</td>
                            <td class="text-right text-slate-500">{{ row.in_stock }}</td>
                            <td class="text-right font-medium text-slate-800">{{ maxQty(row) }}</td>
                            <td>
                                <input
                                    v-model="row.quantity"
                                    type="number"
                                    min="0"
                                    :max="maxQty(row)"
                                    step="0.01"
                                    class="form-input text-right"
                                    :disabled="maxQty(row) <= 0"
                                />
                                <p v-if="errorFor(row, 'quantity')" class="form-error">{{ errorFor(row, 'quantity') }}</p>
                                <p v-if="errorFor(row, 'product_id')" class="form-error">{{ errorFor(row, 'product_id') }}</p>
                            </td>
                            <td class="text-right text-slate-500">{{ money(row.unit_cost) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(net(row)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="px-5 pb-2 text-xs text-slate-400">
                "Can return" is the smaller of the quantity still returnable and what is in stock now.
            </p>
            <p v-if="errors.items" class="form-error px-5 pb-3">{{ errors.items[0] }}</p>
        </div>

        <!-- Settlement -->
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Return Details</h3>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <label class="form-label">Return Date *</label>
                        <input v-model="form.return_date" type="date" class="form-input" />
                        <p v-if="errors.return_date" class="form-error">{{ errors.return_date[0] }}</p>
                    </div>

                    <div>
                        <p class="form-label">Settlement *</p>
                        <div class="space-y-2 text-sm text-slate-700">
                            <label class="flex items-start gap-2">
                                <input v-model="form.refund_method" type="radio" value="cash" class="mt-1 h-4 w-4" />
                                <span>Cash received <span class="block text-xs text-slate-500">The supplier pays the money back to you.</span></span>
                            </label>
                            <label class="flex items-start gap-2" :class="{ 'opacity-50': !data.purchase.supplier_id }">
                                <input
                                    v-model="form.refund_method"
                                    type="radio"
                                    value="adjust_due"
                                    class="mt-1 h-4 w-4"
                                    :disabled="!data.purchase.supplier_id"
                                />
                                <span>
                                    Adjust against due
                                    <span class="block text-xs text-slate-500">
                                        Reduce what you still owe the supplier on this purchase
                                        ({{ money(data.max_due_adjustment) }} outstanding). Any extra is received in cash.
                                        {{ data.purchase.supplier_id ? '' : 'Not available for a one-time supplier.' }}
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p v-if="errors.refund_method" class="form-error">{{ errors.refund_method[0] }}</p>
                    </div>

                    <div>
                        <label class="form-label">Reason</label>
                        <input v-model="form.reason" type="text" class="form-input" placeholder="e.g. Damaged on arrival, wrong item, expired" />
                        <p v-if="errors.reason" class="form-error">{{ errors.reason[0] }}</p>
                    </div>

                    <div>
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="3" class="form-input" placeholder="Optional"></textarea>
                        <p v-if="errors.notes" class="form-error">{{ errors.notes[0] }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Return Summary</h3>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Items value</span>
                        <span class="font-medium text-slate-800">{{ money(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Purchase discount share</span>
                        <span class="text-slate-800">- {{ money(discountShare) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-3 text-base font-semibold">
                        <span class="text-slate-800">Total return value</span>
                        <span class="text-slate-800">{{ money(total) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Adjusted against due</span>
                        <span class="text-slate-800">{{ money(dueAdjustment) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Cash received</span>
                        <span class="font-medium text-slate-800">{{ money(cashReceived) }}</span>
                    </div>

                    <p v-if="cashTooHigh" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        The cash to receive is more than you have paid for this purchase ({{ money(data.max_cash_received) }}).
                        Choose "Adjust against due" instead.
                    </p>
                    <p class="text-xs text-slate-400">These figures are a preview. The server calculates the final amounts.</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-end gap-2">
                <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                <RouterLink :to="`/purchases/${purchaseId}`" class="btn btn-secondary">Cancel</RouterLink>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save Return' }}
                </button>
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

const purchaseId = route.params.id;

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const formError = ref('');

const data = ref(null);
const rows = ref([]);

const today = () => new Date().toLocaleDateString('en-CA');

const form = reactive({
    return_date: today(),
    refund_method: 'cash',
    reason: '',
    notes: '',
});

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const blockedMessage = computed(() => {
    if (!data.value) return '';
    if (data.value.purchase.status === 'cancelled') return 'This purchase is cancelled, so it cannot have returns.';
    if (!data.value.can_return) return 'All items on this purchase have already been returned.';
    return '';
});

// The most that can go back: what is still returnable, limited by what is in stock now
const maxQty = (row) => Math.max(Math.min(row.remaining, row.in_stock), 0);

// Preview figures. The server recalculates everything.
const ratio = computed(() => data.value?.discount_ratio ?? 1);
const gross = (row) => round2((Number(row.quantity) || 0) * row.unit_cost);
const net = (row) => round2(gross(row) * ratio.value);

const subtotal = computed(() => round2(rows.value.reduce((sum, r) => sum + gross(r), 0)));
const total = computed(() => round2(rows.value.reduce((sum, r) => sum + net(r), 0)));
const discountShare = computed(() => round2(subtotal.value - total.value));

const dueAdjustment = computed(() =>
    form.refund_method === 'adjust_due' ? Math.min(total.value, data.value?.max_due_adjustment ?? 0) : 0,
);
const cashReceived = computed(() => round2(total.value - dueAdjustment.value));
const cashTooHigh = computed(() => cashReceived.value > (data.value?.max_cash_received ?? 0) + 0.004);

// Only rows with a quantity are sent, so server error indexes follow this list
const selectedRows = computed(() => rows.value.filter((r) => Number(r.quantity) > 0));

function errorFor(row, field) {
    const index = selectedRows.value.indexOf(row);
    return index === -1 ? '' : errors.value[`items.${index}.${field}`]?.[0] ?? '';
}

function returnAll() {
    rows.value.forEach((r) => {
        if (maxQty(r) > 0) r.quantity = maxQty(r);
    });
}

async function save() {
    errors.value = {};
    formError.value = '';

    if (!selectedRows.value.length) {
        formError.value = 'Enter a quantity for at least one product.';
        return;
    }

    saving.value = true;

    const payload = {
        purchase_id: Number(purchaseId),
        return_date: form.return_date,
        refund_method: form.refund_method,
        reason: form.reason || null,
        notes: form.notes || null,
        items: selectedRows.value.map((r) => ({
            product_id: r.product_id,
            quantity: Number(r.quantity),
        })),
    };

    try {
        await api.post('/api/purchase-returns', payload);
        router.push({ path: '/returns/purchases', query: { saved: 'created' } });
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
        const res = await api.get(`/api/purchases/${purchaseId}/returnable`);
        data.value = res.data.data;
        rows.value = data.value.items.map((i) => ({ ...i, quantity: 0 }));

        // Default to adjusting the due when you still owe the supplier money on this purchase
        if (data.value.purchase.supplier_id && data.value.max_due_adjustment > 0) {
            form.refund_method = 'adjust_due';
        }
    } catch {
        router.replace('/purchases');
        return;
    }
    loading.value = false;
});
</script>