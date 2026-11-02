<template>
    <PageHeader :title="purchaseReturn?.reference_no ?? 'Return Details'" subtitle="Purchase return (debit note)" class="print:hidden">
        <RouterLink to="/returns/purchases" class="btn btn-secondary">Back to Returns</RouterLink>
        <template v-if="purchaseReturn">
            <button type="button" class="btn btn-secondary" @click="printNote">
                <AppIcon name="printer" size="h-4 w-4" />
                Print
            </button>
            <button v-if="purchaseReturn.status === 'completed'" type="button" class="btn btn-danger" @click="openCancel">
                Cancel Return
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="card print:hidden">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="purchaseReturn" class="space-y-6 print:hidden">
        <div v-if="notice" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ notice }}
        </div>

        <div v-if="isCancelled" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">This return was cancelled on {{ formatDateTime(purchaseReturn.cancelled_at) }}.</p>
            <p class="mt-1">Reason: {{ purchaseReturn.cancel_reason }}</p>
            <p class="mt-1 text-xs">The returned goods were put back into stock, and the quantity can be returned again.</p>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Total Return Value</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(purchaseReturn.total) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Cash Received</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(purchaseReturn.cash_received) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Adjusted Against Due</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(purchaseReturn.due_adjustment) }}</p>
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
                <h3 class="font-semibold text-slate-800">Return Information</h3>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Return No</dt>
                    <dd class="font-medium text-slate-800">{{ purchaseReturn.reference_no }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Date</dt>
                    <dd class="font-medium text-slate-800">{{ formatDate(purchaseReturn.return_date) }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Original Purchase</dt>
                    <dd>
                        <RouterLink :to="`/purchases/${purchaseReturn.purchase_id}`" class="font-medium text-brand-700 hover:underline">
                            {{ purchaseReturn.purchase?.reference_no }}
                        </RouterLink>
                    </dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Supplier</dt>
                    <dd class="font-medium text-slate-800">
                        {{ purchaseReturn.supplier_name }}{{ purchaseReturn.supplier_id ? '' : ' (one-time supplier)' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Settlement</dt>
                    <dd class="font-medium text-slate-800">
                        {{ purchaseReturn.refund_method === 'cash' ? 'Cash received' : 'Adjust against due' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Reason</dt>
                    <dd class="text-right font-medium text-slate-800">{{ purchaseReturn.reason || '-' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Returned Items</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-right">Quantity</th>
                            <th class="text-right">Unit Cost</th>
                            <th class="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in purchaseReturn.items" :key="line.id">
                            <td class="text-slate-500">{{ index + 1 }}</td>
                            <td>
                                <p class="font-medium text-slate-800">{{ line.product?.name }}</p>
                                <p class="text-xs text-slate-400">{{ line.product?.sku }}</p>
                            </td>
                            <td class="text-right text-slate-600">{{ Number(line.quantity) }} {{ line.product?.unit?.short_name }}</td>
                            <td class="text-right text-slate-600">{{ money(line.unit_cost) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(line.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                <dl class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Items value</dt>
                        <dd class="text-slate-800">{{ money(purchaseReturn.subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Purchase discount share</dt>
                        <dd class="text-slate-800">- {{ money(purchaseReturn.discount) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-semibold">
                        <dt class="text-slate-800">Total return value</dt>
                        <dd class="text-slate-800">{{ money(purchaseReturn.total) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Notes -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Notes</h3>
            </div>
            <div class="card-body whitespace-pre-line text-sm text-slate-600">
                {{ purchaseReturn.notes || 'No notes.' }}
            </div>
        </div>
    </div>

    <!-- Printable debit note (visible only when printing) -->
    <CreditNoteSheet v-if="purchaseReturn" type="purchase" :doc="purchaseReturn" />

    <CancelDialog
        v-model="showCancel"
        title="Cancel Return"
        :description="`This will cancel ${purchaseReturn?.reference_no} and put the returned goods back into stock. The quantity can be returned again.`"
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
import CreditNoteSheet from '../components/ui/creditnotesheet.vue';
import CancelDialog from '../components/ui/canceldialog.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const purchaseReturn = ref(null);
const loading = ref(true);
const notice = ref('');

const showCancel = ref(false);
const cancelling = ref(false);
const cancelError = ref('');

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const formatDateTime = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

const isCancelled = computed(() => purchaseReturn.value?.status === 'cancelled');

async function loadReturn() {
    const { data } = await api.get(`/api/purchase-returns/${route.params.id}`);
    purchaseReturn.value = data.data;
}

function flash(message) {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 3000);
}

function printNote() {
    // The browser uses the page title as the default PDF file name
    const original = document.title;
    document.title = purchaseReturn.value.reference_no;
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
        await api.post(`/api/purchase-returns/${purchaseReturn.value.id}/cancel`, { reason });
        showCancel.value = false;
        await loadReturn();
        flash('Purchase return cancelled successfully.');
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
        await loadReturn();
    } catch {
        router.replace('/returns/purchases');
        return;
    }
    loading.value = false;
});
</script>