<template>
    <PageHeader :title="adjustment?.reference_no ?? 'Adjustment Details'" subtitle="Stock adjustment" class="print:hidden">
        <RouterLink to="/stock/adjustments" class="btn btn-secondary">Back to Adjustments</RouterLink>
        <template v-if="adjustment">
            <button type="button" class="btn btn-secondary" @click="printDoc">
                <AppIcon name="printer" size="h-4 w-4" />
                Print
            </button>
            <button v-if="adjustment.status === 'completed'" type="button" class="btn btn-danger" @click="openCancel">
                Cancel Adjustment
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="card print:hidden">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="adjustment" class="space-y-6 print:hidden">
        <div v-if="notice" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ notice }}
        </div>

        <div v-if="isCancelled" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">This adjustment was cancelled on {{ formatDateTime(adjustment.cancelled_at) }}.</p>
            <p class="mt-1">Reason: {{ adjustment.cancel_reason }}</p>
            <p class="mt-1 text-xs">The stock change was reversed.</p>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Type</p>
                    <p class="mt-2 text-xl font-bold text-slate-800">{{ adjustmentTypes[adjustment.type]?.label ?? adjustment.type }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Date</p>
                    <p class="mt-2 text-xl font-bold text-slate-800">{{ formatDate(adjustment.adjustment_date) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Net Value Change</p>
                    <p class="mt-2 text-2xl font-bold" :class="Number(adjustment.value_change) < 0 ? 'text-red-600' : 'text-slate-800'">
                        {{ money(adjustment.value_change) }}
                    </p>
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
                <h3 class="font-semibold text-slate-800">Adjustment Information</h3>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Reference</dt>
                    <dd class="font-medium text-slate-800">{{ adjustment.reference_no }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Reason</dt>
                    <dd class="text-right font-medium text-slate-800">{{ adjustment.reason || '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">Created</dt>
                    <dd class="font-medium text-slate-800">{{ formatDateTime(adjustment.created_at) }}</dd>
                </div>
            </dl>
        </div>

        <!-- Items -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Products</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-right">Before</th>
                            <th class="text-right">Change</th>
                            <th class="text-right">After</th>
                            <th class="text-right">Unit Cost</th>
                            <th class="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in adjustment.items" :key="line.id">
                            <td class="text-slate-500">{{ index + 1 }}</td>
                            <td>
                                <p class="font-medium text-slate-800">{{ line.product?.name }}</p>
                                <p class="text-xs text-slate-400">{{ line.product?.sku }}</p>
                            </td>
                            <td class="text-right text-slate-600">{{ Number(line.quantity_before) }}</td>
                            <td class="text-right font-medium" :class="Number(line.difference) < 0 ? 'text-red-600' : 'text-emerald-600'">
                                {{ signed(line.difference) }}
                            </td>
                            <td class="text-right text-slate-600">{{ Number(line.quantity_after) }} {{ line.product?.unit?.short_name }}</td>
                            <td class="text-right text-slate-600">{{ money(line.unit_cost) }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(Number(line.difference) * Number(line.unit_cost)) }}</td>
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
                {{ adjustment.notes || 'No notes.' }}
            </div>
        </div>
    </div>

    <!-- Printable document (visible only when printing) -->
    <AdjustmentSheet v-if="adjustment" :doc="adjustment" />

    <CancelDialog
        v-model="showCancel"
        title="Cancel Adjustment"
        :description="`This will cancel ${adjustment?.reference_no} and reverse its stock change. If the stock has already been used since (for example sold), the cancellation will be rejected.`"
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
import AdjustmentSheet from '../components/ui/adjustmentsheet.vue';
import CancelDialog from '../components/ui/canceldialog.vue';
import { adjustmentTypes } from '../config/stock';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const adjustment = ref(null);
const loading = ref(true);
const notice = ref('');

const showCancel = ref(false);
const cancelling = ref(false);
const cancelError = ref('');

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const signed = (value) => (Number(value) > 0 ? `+${Number(value)}` : `${Number(value)}`);

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const formatDateTime = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

const isCancelled = computed(() => adjustment.value?.status === 'cancelled');

async function loadAdjustment() {
    const { data } = await api.get(`/api/stock-adjustments/${route.params.id}`);
    adjustment.value = data.data;
}

function flash(message) {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 3000);
}

function printDoc() {
    // The browser uses the page title as the default PDF file name
    const original = document.title;
    document.title = adjustment.value.reference_no;
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
        await api.post(`/api/stock-adjustments/${adjustment.value.id}/cancel`, { reason });
        showCancel.value = false;
        await loadAdjustment();
        flash('Stock adjustment cancelled successfully.');
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
        await loadAdjustment();
    } catch {
        router.replace('/stock/adjustments');
        return;
    }
    loading.value = false;
});
</script>