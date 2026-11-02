<template>
    <PageHeader
        :title="`New ${kind.title}`"
        :subtitle="isReceipt ? 'Record money received from a customer' : 'Record money paid to a supplier'"
    >
        <RouterLink :to="kind.listPath" class="btn btn-secondary">Back to {{ kind.plural }}</RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Party and details -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">{{ kind.title }} Details</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label">{{ kind.party }} *</label>
                    <select v-model="partyId" class="form-input" @change="loadOutstanding">
                        <option value="">Select {{ kind.party.toLowerCase() }}</option>
                        <option v-for="p in parties" :key="p.id" :value="p.id">
                            {{ p.name }}{{ p.phone ? ` (${p.phone})` : '' }}
                        </option>
                    </select>
                    <p v-if="errors[partyField]" class="form-error">{{ errors[partyField][0] }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Only saved {{ kind.party.toLowerCase() }}s are listed. Walk-in and one-time parties pay in full at the time, so they do not need this.
                    </p>
                </div>

                <div>
                    <label class="form-label">Date *</label>
                    <input v-model="form.payment_date" type="date" class="form-input" />
                    <p v-if="errors.payment_date" class="form-error">{{ errors.payment_date[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Method *</label>
                    <select v-model="form.method" class="form-input">
                        <option v-for="(label, key) in paymentMethods" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="errors.method" class="form-error">{{ errors.method[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Reference</label>
                    <input v-model="form.transaction_ref" type="text" class="form-input" placeholder="Cheque number, transfer ID or wallet reference (optional)" />
                    <p v-if="errors.transaction_ref" class="form-error">{{ errors.transaction_ref[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea v-model="form.notes" rows="2" class="form-input" placeholder="Optional"></textarea>
                    <p v-if="errors.notes" class="form-error">{{ errors.notes[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Nothing selected yet -->
        <div v-if="!partyId" class="card">
            <div class="card-body text-sm text-slate-500">Select a {{ kind.party.toLowerCase() }} to see what is outstanding.</div>
        </div>

        <!-- Outstanding -->
        <div v-else-if="outstandingLoading" class="card">
            <div class="card-body text-sm text-slate-500">Loading outstanding balance...</div>
        </div>

        <div v-else-if="outstanding" class="card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                <div>
                    <h3 class="font-semibold text-slate-800">What to settle</h3>
                    <p class="text-xs text-slate-500">
                        Total outstanding for {{ outstanding.party.name }}:
                        <strong class="text-slate-800">{{ money(outstanding.total_outstanding) }}</strong>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secondary px-3! py-1!" @click="autoFill">Auto-fill oldest first</button>
                    <button type="button" class="btn btn-secondary px-3! py-1!" @click="clearAll">Clear</button>
                </div>
            </div>

            <div v-if="!hasAnything" class="card-body text-sm text-slate-500">
                Nothing is outstanding for this {{ kind.party.toLowerCase() }}.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Date</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Returns</th>
                            <th class="text-right">Due</th>
                            <th class="w-40 text-right">Pay now</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening balance -->
                        <tr v-if="outstanding.opening_outstanding > 0" class="align-top">
                            <td class="font-medium text-slate-800">Opening balance</td>
                            <td class="text-slate-500">-</td>
                            <td class="text-right text-slate-500">{{ money(outstanding.summary.opening_balance) }}</td>
                            <td class="text-right text-slate-500">{{ money(outstanding.summary.opening_balance - outstanding.opening_outstanding) }}</td>
                            <td class="text-right text-slate-500">-</td>
                            <td class="text-right font-medium text-slate-800">{{ money(outstanding.opening_outstanding) }}</td>
                            <td>
                                <input v-model="openingAmount" type="number" min="0" step="0.01" class="form-input text-right" />
                                <p v-if="errors.opening_amount" class="form-error">{{ errors.opening_amount[0] }}</p>
                            </td>
                        </tr>

                        <!-- Invoices -->
                        <tr v-for="row in rows" :key="row.id" class="align-top">
                            <td>
                                <RouterLink
                                    :to="`${isReceipt ? '/sales' : '/purchases'}/${row.id}`"
                                    class="font-medium text-slate-800 hover:text-brand-700"
                                >
                                    {{ row.reference_no }}
                                </RouterLink>
                            </td>
                            <td class="text-slate-500">{{ formatDate(row.date) }}</td>
                            <td class="text-right text-slate-500">{{ money(row.total) }}</td>
                            <td class="text-right text-slate-500">{{ money(row.paid) }}</td>
                            <td class="text-right text-slate-500">{{ row.returns_adjusted > 0 ? money(row.returns_adjusted) : '-' }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(row.due) }}</td>
                            <td>
                                <input v-model="row.pay" type="number" min="0" step="0.01" class="form-input text-right" />
                                <p v-if="errorFor(row)" class="form-error">{{ errorFor(row) }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="errors.allocations" class="form-error px-5 pb-3">{{ errors.allocations[0] }}</p>

            <div class="flex justify-end border-t border-slate-100 px-5 py-4 text-sm">
                <div class="flex w-full max-w-xs justify-between text-base font-semibold">
                    <span class="text-slate-800">Total {{ isReceipt ? 'received' : 'paid' }}</span>
                    <span class="text-slate-800">{{ money(total) }}</span>
                </div>
            </div>
        </div>

        <p class="text-xs text-slate-400">
            A payment cannot be more than what is due. The server checks the live balance when you save.
        </p>

        <!-- Actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-end gap-2">
                <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                <RouterLink :to="kind.listPath" class="btn btn-secondary">Cancel</RouterLink>
                <button type="submit" class="btn btn-primary" :disabled="saving || !partyId">
                    {{ saving ? 'Saving...' : `Save ${kind.title}` }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import { paymentKinds, paymentMethods } from '../config/payments';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

// This page serves both forms. The router tells us which one.
const kind = computed(() => paymentKinds[route.meta.kind]);
const isReceipt = computed(() => route.meta.kind === 'receipt');
const partyField = computed(() => (isReceipt.value ? 'customer_id' : 'supplier_id'));

const loading = ref(true);
const saving = ref(false);
const outstandingLoading = ref(false);
const errors = ref({});
const formError = ref('');

const parties = ref([]);
const partyId = ref('');
const outstanding = ref(null);
const rows = ref([]); // unpaid invoices, each with a "pay" amount
const openingAmount = ref('');

const today = () => new Date().toLocaleDateString('en-CA');

const form = reactive({
    payment_date: today(),
    method: 'cash',
    transaction_ref: '',
    notes: '',
});

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const hasAnything = computed(() => (outstanding.value?.opening_outstanding ?? 0) > 0 || rows.value.length > 0);

const total = computed(() =>
    round2((Number(openingAmount.value) || 0) + rows.value.reduce((sum, r) => sum + (Number(r.pay) || 0), 0)),
);

// Only rows with an amount are sent, so server error indexes follow this list
const selectedRows = computed(() => rows.value.filter((r) => Number(r.pay) > 0));

function errorFor(row) {
    const index = selectedRows.value.indexOf(row);
    if (index === -1) return '';
    return errors.value[`allocations.${index}.amount`]?.[0] ?? errors.value[`allocations.${index}.document_id`]?.[0] ?? '';
}

function clearAll() {
    openingAmount.value = '';
    rows.value.forEach((r) => (r.pay = ''));
}

// Pay the oldest items first. Opening balance counts as the oldest.
function autoFill() {
    const target = Number(prompt('How much is being paid? Leave empty to pay everything that is due.') || '');
    let left = target > 0 ? target : Infinity;

    clearAll();

    const opening = outstanding.value.opening_outstanding;
    if (opening > 0 && left > 0) {
        const take = Math.min(opening, left);
        openingAmount.value = round2(take);
        left = round2(left - take);
    }

    for (const row of rows.value) {
        if (left <= 0) break;
        const take = Math.min(row.due, left);
        row.pay = round2(take);
        left = round2(left - take);
    }
}

async function loadParties() {
    const { data } = await api.get(isReceipt.value ? '/api/customers' : '/api/suppliers', {
        params: { per_page: 100, is_active: 1 },
    });
    parties.value = data.data.data;
}

async function loadOutstanding() {
    errors.value = {};
    outstanding.value = null;
    rows.value = [];
    openingAmount.value = '';

    if (!partyId.value) return;

    outstandingLoading.value = true;
    try {
        const url = isReceipt.value
            ? `/api/customers/${partyId.value}/outstanding`
            : `/api/suppliers/${partyId.value}/outstanding`;
        const { data } = await api.get(url);
        outstanding.value = data.data;
        rows.value = data.data.invoices.map((i) => ({ ...i, pay: '' }));
    } catch {
        formError.value = 'Could not load the outstanding balance. Please try again.';
    } finally {
        outstandingLoading.value = false;
    }
}

async function save() {
    errors.value = {};
    formError.value = '';

    if (total.value <= 0) {
        formError.value = 'Enter an amount for the opening balance or for at least one invoice.';
        return;
    }

    saving.value = true;

    const payload = {
        [partyField.value]: partyId.value,
        payment_date: form.payment_date,
        method: form.method,
        transaction_ref: form.transaction_ref || null,
        notes: form.notes || null,
        opening_amount: Number(openingAmount.value) || 0,
        allocations: selectedRows.value.map((r) => ({ document_id: r.id, amount: Number(r.pay) })),
    };

    try {
        await api.post(kind.value.listApi, payload);
        router.push({ path: kind.value.listPath, query: { saved: 'created' } });
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
        await loadParties();
    } catch {
        formError.value = 'Could not load the list. Please refresh the page.';
    }

    // Opened from a customer, supplier, sale or purchase page, with the party already chosen
    const preset = Number(route.query[isReceipt.value ? 'customer_id' : 'supplier_id']);
    if (preset && parties.value.some((p) => p.id === preset)) {
        partyId.value = preset;
        await loadOutstanding();
    }

    loading.value = false;
});
</script>