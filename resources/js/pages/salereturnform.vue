<template>
    <PageHeader
        title="New Sales Return"
        :subtitle="data ? `Return items from invoice ${data.sale.reference_no}` : 'Return items from an invoice'"
    >
        <RouterLink :to="`/sales/${saleId}`" class="btn btn-secondary">Back to Sale</RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="blockedMessage" class="card">
        <div class="card-body text-sm text-slate-600">{{ blockedMessage }}</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Invoice summary -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Original Invoice</h3>
            </div>
            <dl class="grid gap-4 px-5 py-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-slate-500">Invoice</dt>
                    <dd class="font-medium text-slate-800">{{ data.sale.reference_no }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Customer</dt>
                    <dd class="font-medium text-slate-800">
                        {{ data.sale.customer_name }}{{ data.sale.customer_id ? '' : ' (walk-in)' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Invoice Total</dt>
                    <dd class="font-medium text-slate-800">{{ money(data.sale.total) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Paid</dt>
                    <dd class="font-medium text-slate-800">{{ money(data.sale.paid_amount) }}</dd>
                </div>
            </dl>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Items to Return</h3>
                <button type="button" class="btn btn-secondary px-3! py-1!" @click="returnAll">Return all</button>
            </div>

            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="min-w-48">Product</th>
                            <th class="text-right">Sold</th>
                            <th class="text-right">Returned</th>
                            <th class="text-right">Can return</th>
                            <th class="w-32 text-right">Return qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Refund value</th>
                            <th class="w-36">Condition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.product_id" class="align-top" :class="{ 'opacity-50': row.remaining <= 0 }">
                            <td>
                                <p class="font-medium text-slate-800">{{ row.name }}</p>
                                <p class="text-xs text-slate-400">{{ row.sku }}</p>
                            </td>
                            <td class="text-right text-slate-500">{{ row.sold }} {{ row.unit }}</td>
                            <td class="text-right text-slate-500">{{ row.returned }}</td>
                            <td class="text-right font-medium text-slate-800">{{ row.remaining }}</td>
                            <td>
                                <input
                                    v-model="row.quantity"
                                    type="number"
                                    min="0"
                                    :max="row.remaining"
                                    step="0.01"
                                    class="form-input text-right"
                                    :disabled="row.remaining <= 0"
                                />
                                <p v-if="errorFor(row, 'quantity')" class="form-error">{{ errorFor(row, 'quantity') }}</p>
                                <p v-if="errorFor(row, 'product_id')" class="form-error">{{ errorFor(row, 'product_id') }}</p>
                            </td>
                            <td class="text-right text-slate-500">{{ money(row.unit_price) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(net(row)) }}</td>
                            <td>
                                <label class="flex items-center gap-2 text-xs text-slate-600">
                                    <input v-model="row.damaged" type="checkbox" class="h-4 w-4 rounded border-slate-300" :disabled="row.remaining <= 0" />
                                    Damaged (do not restock)
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="errors.items" class="form-error px-5 pb-3">{{ errors.items[0] }}</p>
        </div>

        <!-- Refund -->
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
                        <p class="form-label">Refund Method *</p>
                        <div class="space-y-2 text-sm text-slate-700">
                            <label class="flex items-start gap-2">
                                <input v-model="form.refund_method" type="radio" value="cash" class="mt-1 h-4 w-4" />
                                <span>Cash refund <span class="block text-xs text-slate-500">Pay the money back to the customer.</span></span>
                            </label>
                            <label class="flex items-start gap-2" :class="{ 'opacity-50': !data.sale.customer_id }">
                                <input
                                    v-model="form.refund_method"
                                    type="radio"
                                    value="adjust_due"
                                    class="mt-1 h-4 w-4"
                                    :disabled="!data.sale.customer_id"
                                />
                                <span>
                                    Adjust against due
                                    <span class="block text-xs text-slate-500">
                                        Reduce what the customer still owes on this invoice
                                        ({{ money(data.max_due_adjustment) }} outstanding). Any extra is refunded in cash.
                                        {{ data.sale.customer_id ? '' : 'Not available for walk-in sales.' }}
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p v-if="errors.refund_method" class="form-error">{{ errors.refund_method[0] }}</p>
                    </div>

                    <div>
                        <label class="form-label">Reason</label>
                        <input v-model="form.reason" type="text" class="form-input" placeholder="e.g. Wrong item, defective, customer changed mind" />
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
                    <h3 class="font-semibold text-slate-800">Refund Summary</h3>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Items value</span>
                        <span class="font-medium text-slate-800">{{ money(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Invoice discount share</span>
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
                        <span class="text-slate-500">Cash refund</span>
                        <span class="font-medium text-slate-800">{{ money(cashRefund) }}</span>
                    </div>

                    <p v-if="cashTooHigh" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        The cash refund is more than the customer has paid ({{ money(data.max_cash_refund) }}).
                        Choose "Adjust against due" instead.
                    </p>
                    <p class="text-xs text-slate-400">
                        These figures are a preview. The server calculates the final amounts.
                    </p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-end gap-2">
                <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                <RouterLink :to="`/sales/${saleId}`" class="btn btn-secondary">Cancel</RouterLink>
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

const saleId = route.params.id;

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
    if (data.value.sale.status === 'cancelled') return 'This sale is cancelled, so it cannot have returns.';
    if (!data.value.can_return) return 'All items on this invoice have already been returned.';
    return '';
});

// Preview figures. The server recalculates everything.
const ratio = computed(() => data.value?.discount_ratio ?? 1);
const gross = (row) => round2((Number(row.quantity) || 0) * row.unit_price);
const net = (row) => round2(gross(row) * ratio.value);

const subtotal = computed(() => round2(rows.value.reduce((sum, r) => sum + gross(r), 0)));
const total = computed(() => round2(rows.value.reduce((sum, r) => sum + net(r), 0)));
const discountShare = computed(() => round2(subtotal.value - total.value));

const dueAdjustment = computed(() =>
    form.refund_method === 'adjust_due' ? Math.min(total.value, data.value?.max_due_adjustment ?? 0) : 0,
);
const cashRefund = computed(() => round2(total.value - dueAdjustment.value));
const cashTooHigh = computed(() => cashRefund.value > (data.value?.max_cash_refund ?? 0) + 0.004);

// Only rows with a quantity are sent, so server error indexes follow this list
const selectedRows = computed(() => rows.value.filter((r) => Number(r.quantity) > 0));

function errorFor(row, field) {
    const index = selectedRows.value.indexOf(row);
    return index === -1 ? '' : errors.value[`items.${index}.${field}`]?.[0] ?? '';
}

function returnAll() {
    rows.value.forEach((r) => {
        if (r.remaining > 0) r.quantity = r.remaining;
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
        sale_id: Number(saleId),
        return_date: form.return_date,
        refund_method: form.refund_method,
        reason: form.reason || null,
        notes: form.notes || null,
        items: selectedRows.value.map((r) => ({
            product_id: r.product_id,
            quantity: Number(r.quantity),
            restock: !r.damaged,
        })),
    };

    try {
        await api.post('/api/sale-returns', payload);
        router.push({ path: '/returns', query: { saved: 'created' } });
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
        const res = await api.get(`/api/sales/${saleId}/returnable`);
        data.value = res.data.data;
        rows.value = data.value.items.map((i) => ({ ...i, quantity: 0, damaged: false }));

        // Default to adjusting the due when the customer still owes money on this invoice
        if (data.value.sale.customer_id && data.value.max_due_adjustment > 0) {
            form.refund_method = 'adjust_due';
        }
    } catch {
        router.replace('/sales');
        return;
    }
    loading.value = false;
});
</script>