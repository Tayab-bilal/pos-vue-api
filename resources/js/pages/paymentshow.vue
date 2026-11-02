<template>
    <PageHeader :title="payment?.reference_no ?? 'Payment Details'" :subtitle="kind?.document ?? ''" class="print:hidden">
        <RouterLink :to="kind?.listPath ?? '/payments'" class="btn btn-secondary">Back to {{ kind?.plural ?? 'Payments' }}</RouterLink>
        <template v-if="payment">
            <button type="button" class="btn btn-secondary" @click="printDoc">
                <AppIcon name="printer" size="h-4 w-4" />
                Print
            </button>
            <button v-if="payment.status === 'completed'" type="button" class="btn btn-danger" @click="openCancel">
                Cancel {{ kind.title }}
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="card print:hidden">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="payment" class="space-y-6 print:hidden">
        <div v-if="notice" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ notice }}
        </div>

        <div v-if="isCancelled" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">This {{ kind.title.toLowerCase() }} was cancelled on {{ formatDateTime(payment.cancelled_at) }}.</p>
            <p class="mt-1">Reason: {{ payment.cancel_reason }}</p>
            <p class="mt-1 text-xs">The amount is due again on the invoices it settled.</p>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Amount</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(payment.amount) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">{{ kind.party }}</p>
                    <p class="mt-2 text-xl font-bold text-slate-800">{{ payment.party_name }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Method</p>
                    <p class="mt-2 text-xl font-bold text-slate-800">{{ paymentMethods[payment.method] ?? payment.method }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Status</p>
                    <span class="badge mt-3" :class="isCancelled ? 'badge-danger' : 'badge-success'">
                        {{ isCancelled ? 'Cancelled' : 'Completed' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">{{ kind.title }} Information</h3>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Number</dt>
                    <dd class="font-medium text-slate-800">{{ payment.reference_no }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Date</dt>
                    <dd class="font-medium text-slate-800">{{ formatDate(payment.payment_date) }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">{{ kind.party }}</dt>
                    <dd>
                        <RouterLink :to="`${isReceipt ? '/customers' : '/suppliers'}/${partyId}`" class="font-medium text-brand-700 hover:underline">
                            {{ payment.party_name }}
                        </RouterLink>
                    </dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Reference</dt>
                    <dd class="text-right font-medium text-slate-800">{{ payment.transaction_ref || '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Created</dt>
                    <dd class="font-medium text-slate-800">{{ formatDateTime(payment.created_at) }}</dd>
                </div>
            </dl>
        </div>

        <!-- Allocations -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Settled Against</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Date</th>
                            <th class="text-right">Invoice Total</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="Number(payment.opening_amount) > 0">
                            <td class="font-medium text-slate-800">Opening balance</td>
                            <td class="text-slate-500">-</td>
                            <td class="text-right text-slate-500">-</td>
                            <td class="text-right font-medium text-slate-800">{{ money(payment.opening_amount) }}</td>
                        </tr>
                        <tr v-for="line in payment.allocations" :key="line.id">
                            <td>
                                <RouterLink
                                    :to="`${isReceipt ? '/sales' : '/purchases'}/${isReceipt ? line.sale_id : line.purchase_id}`"
                                    class="font-medium text-slate-800 hover:text-brand-700"
                                >
                                    {{ invoiceOf(line)?.reference_no }}
                                </RouterLink>
                            </td>
                            <td class="text-slate-500">
                                {{ formatDate(isReceipt ? invoiceOf(line)?.sale_date : invoiceOf(line)?.purchase_date) }}
                            </td>
                            <td class="text-right text-slate-500">{{ money(invoiceOf(line)?.total) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(line.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notes -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Notes</h3>
            </div>
            <div class="card-body whitespace-pre-line text-sm text-slate-600">
                {{ payment.notes || 'No notes.' }}
            </div>
        </div>
    </div>

    <!-- Printable receipt or voucher (visible only when printing) -->
    <PaymentSheet v-if="payment" :doc="payment" />

    <CancelDialog
        v-model="showCancel"
        :title="`Cancel ${kind?.title ?? 'Payment'}`"
        :description="`This will cancel ${payment?.reference_no}. The amount becomes due again on the invoices it settled.`"
        :processing="cancelling"
        :error="cancelError"
        @confirm="confirmCancel"
    />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import PaymentSheet from '../components/ui/paymentsheet.vue';
import CancelDialog from '../components/ui/canceldialog.vue';
import { paymentKinds, paymentMethods } from '../config/payments';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const payment = ref(null);
const loading = ref(true);
const notice = ref('');

const showCancel = ref(false);
const cancelling = ref(false);
const cancelError = ref('');

const kind = computed(() => (payment.value ? paymentKinds[payment.value.type] : null));
const isReceipt = computed(() => payment.value?.type === 'receipt');
const isCancelled = computed(() => payment.value?.status === 'cancelled');
const partyId = computed(() => (isReceipt.value ? payment.value.customer_id : payment.value.supplier_id));

const invoiceOf = (line) => (isReceipt.value ? line.sale : line.purchase);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const formatDateTime = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

async function loadPayment() {
    const { data } = await api.get(`/api/payments/${route.params.id}`);
    payment.value = data.data;
}

function flash(message) {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 3000);
}

function printDoc() {
    // The browser uses the page title as the default PDF file name
    const original = document.title;
    document.title = payment.value.reference_no;
    window.print();
    document.title = original;
}

function openCancel() {
    cancelError.value = '';
    showCancel.value = true;
}

async function confirmCancel(reason) {
    cancelling.value = true;
    cancelError.value = '';
    try {
        await api.post(`/api/payments/${payment.value.id}/cancel`, { reason });
        showCancel.value = false;
        await loadPayment();
        flash(`${kind.value.title} cancelled successfully.`);
    } catch (e) {
        const data = e.response?.data;
        cancelError.value =
            data?.errors?.reason?.[0] ?? data?.errors?.items?.[0] ?? data?.message ?? 'Something went wrong. Please try again.';
    } finally {
        cancelling.value = false;
    }
}

onMounted(async () => {
    try {
        await loadPayment();
    } catch {
        router.replace('/payments');
        return;
    }
    loading.value = false;
});
</script>