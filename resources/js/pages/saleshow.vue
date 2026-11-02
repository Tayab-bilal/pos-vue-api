<template>
    <PageHeader :title="sale?.reference_no ?? 'Sale Details'" subtitle="Sales invoice" class="print:hidden">
        <RouterLink to="/sales" class="btn btn-secondary">Back to Sales</RouterLink>
        <template v-if="sale">
            <RouterLink v-if="sale.status === 'completed' && sale.customer_id && due > 0"
                :to="`/payments/receipts/create?customer_id=${sale.customer_id}`" class="btn btn-secondary">
                Add Payment
            </RouterLink>
            <RouterLink v-if="sale.status === 'completed'" :to="`/sales/${sale.id}/return`" class="btn btn-secondary">
                <AppIcon name="returns" size="h-4 w-4" />
                Return
            </RouterLink>
            <RouterLink v-if="canChange" :to="`/sales/${sale.id}/edit`" class="btn btn-secondary">
                <AppIcon name="pencil" size="h-4 w-4" />
                Edit
            </RouterLink>
            <button type="button" class="btn btn-secondary" @click="printInvoice">
                <AppIcon name="printer" size="h-4 w-4" />
                Print
            </button>
            <button v-if="canChange" type="button" class="btn btn-danger" @click="openCancel">
                Cancel Sale
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="card print:hidden">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="sale" class="space-y-6 print:hidden">
        <div v-if="notice"
            class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ notice }}
        </div>

        <div v-if="isCancelled" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">This sale was cancelled on {{ formatDateTime(sale.cancelled_at) }}.</p>
            <p class="mt-1">Reason: {{ sale.cancel_reason }}</p>
            <p class="mt-1 text-xs">The sold stock was returned to inventory. A cancelled sale cannot be edited.</p>
        </div>

        <div v-if="hasPayments && !hasReturns && !isCancelled"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Payments are recorded against this sale.</p>
            <p class="mt-1 text-xs">Edit and Cancel are hidden to keep balances correct. To change it, cancel its
                payments
                first.</p>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Total</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(sale.total) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Paid</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(sale.paid_amount) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Due</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(due) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Status</p>
                    <span class="badge mt-3" :class="statusClass">{{ statusLabel }}</span>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Sale Information</h3>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div v-for="row in infoRows" :key="row.label" class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">{{ row.label }}</dt>
                    <dd class="text-right font-medium text-slate-800">{{ row.value || '-' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Items</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in sale.items" :key="line.id">
                            <td class="text-slate-500">{{ index + 1 }}</td>
                            <td>
                                <p class="font-medium text-slate-800">{{ line.product?.name }}</p>
                                <p class="text-xs text-slate-400">{{ line.product?.sku }}</p>
                            </td>
                            <td class="text-right text-slate-600">{{ Number(line.quantity) }} {{
                                line.product?.unit?.short_name }}</td>
                            <td class="text-right text-slate-600">{{ money(line.unit_price) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(line.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                <dl class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd class="text-slate-800">{{ money(sale.subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Discount</dt>
                        <dd class="text-slate-800">- {{ money(sale.discount) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-semibold">
                        <dt class="text-slate-800">Total</dt>
                        <dd class="text-slate-800">{{ money(sale.total) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Paid</dt>
                        <dd class="text-slate-800">{{ money(sale.paid_amount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Due</dt>
                        <dd class="text-slate-800">{{ money(due) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Returns -->
        <div v-if="hasReturns" class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Returns</h3>
                <p class="text-xs text-slate-500">
                    This invoice has returns, so it cannot be edited or cancelled. Cancel the returns first.
                </p>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                <li v-for="r in sale.returns" :key="r.id" class="flex items-center justify-between gap-4 px-5 py-3">
                    <RouterLink :to="`/returns/${r.id}`" class="font-medium text-slate-800 hover:text-brand-700">
                        {{ r.reference_no }}
                    </RouterLink>
                    <span class="text-slate-500">{{ formatDate(r.return_date) }}</span>
                    <span class="font-medium text-slate-800">{{ money(r.total) }}</span>
                </li>
            </ul>
        </div>

        <!-- Notes -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Notes</h3>
            </div>
            <div class="card-body whitespace-pre-line text-sm text-slate-600">
                {{ sale.notes || 'No notes.' }}
            </div>
        </div>
    </div>

    <!-- Printable invoice (visible only when printing) -->
    <InvoiceSheet v-if="sale" type="sale" :doc="sale" />

    <CancelDialog v-model="showCancel" title="Cancel Sale"
        :description="`This will cancel ${sale?.reference_no} and put the sold stock back into inventory. The invoice stays in the list as Cancelled and can no longer be edited.`"
        :processing="cancelling" :error="cancelError" @confirm="confirmCancel" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import InvoiceSheet from '../components/ui/invoicesheet.vue';
import CancelDialog from '../components/ui/canceldialog.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const sale = ref(null);
const loading = ref(true);
const notice = ref('');

const showCancel = ref(false);
const cancelling = ref(false);
const cancelError = ref('');

const money = (value) =>
    Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const formatDateTime = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

const isCancelled = computed(() => sale.value?.status === 'cancelled');
const hasReturns = computed(() => (sale.value?.returns?.length ?? 0) > 0);
const hasPayments = computed(() => (sale.value?.payment_allocations?.length ?? 0) > 0);

// A sale that has returns can no longer be edited or cancelled
const canChange = computed(() => sale.value?.status === 'completed' && !hasReturns.value && !hasPayments.value);

const due = computed(() => (isCancelled.value ? 0 : Number(sale.value.total) - Number(sale.value.paid_amount)));

const statusClass = computed(() => {
    if (isCancelled.value) return 'badge-danger';
    return { paid: 'badge-success', partial: 'badge-warning', unpaid: 'badge-danger' }[sale.value.payment_status] ?? '';
});
const statusLabel = computed(() => {
    if (isCancelled.value) return 'Cancelled';
    return { paid: 'Paid', partial: 'Partial', unpaid: 'Unpaid' }[sale.value.payment_status] ?? sale.value.payment_status;
});

const infoRows = computed(() => [
    { label: 'Invoice', value: sale.value.reference_no },
    { label: 'Date', value: formatDate(sale.value.sale_date) },
    { label: 'Customer', value: sale.value.customer_name + (sale.value.customer_id ? '' : ' (walk-in)') },
    { label: 'Customer Phone', value: sale.value.customer_phone },
]);

async function loadSale() {
    const { data } = await api.get(`/api/sales/${route.params.id}`);
    sale.value = data.data;
}

function flash(message) {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 3000);
}

function printInvoice() {
    // The browser uses the page title as the default PDF file name
    const original = document.title;
    document.title = sale.value.reference_no;
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
        await api.post(`/api/sales/${sale.value.id}/cancel`, { reason });
        showCancel.value = false;
        await loadSale();
        flash('Sale cancelled successfully.');
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
        await loadSale();
    } catch {
        router.replace('/sales');
        return;
    }
    loading.value = false;
});
</script>