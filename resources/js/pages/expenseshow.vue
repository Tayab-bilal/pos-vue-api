<template>
    <PageHeader :title="expense?.reference_no ?? 'Expense Details'" subtitle="Expense voucher" class="print:hidden">
        <RouterLink to="/expenses" class="btn btn-secondary">Back to Expenses</RouterLink>
        <template v-if="expense">
            <RouterLink v-if="expense.status === 'completed'" :to="`/expenses/${expense.id}/edit`" class="btn btn-secondary">
                <AppIcon name="pencil" size="h-4 w-4" />
                Edit
            </RouterLink>
            <button type="button" class="btn btn-secondary" @click="printDoc">
                <AppIcon name="printer" size="h-4 w-4" />
                Print
            </button>
            <button v-if="expense.status === 'completed'" type="button" class="btn btn-danger" @click="openCancel">
                Cancel Expense
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="card print:hidden">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="expense" class="space-y-6 print:hidden">
        <div v-if="notice" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ notice }}
        </div>

        <div v-if="isCancelled" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">This expense was cancelled on {{ formatDateTime(expense.cancelled_at) }}.</p>
            <p class="mt-1">Reason: {{ expense.cancel_reason }}</p>
            <p class="mt-1 text-xs">A cancelled expense is not counted in totals and reports, and cannot be edited.</p>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Amount</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(expense.amount) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Category</p>
                    <p class="mt-2 text-xl font-bold text-slate-800">{{ expense.category?.name }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Method</p>
                    <p class="mt-2 text-xl font-bold text-slate-800">{{ paymentMethods[expense.method] ?? expense.method }}</p>
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
                <h3 class="font-semibold text-slate-800">Expense Information</h3>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div v-for="row in rows" :key="row.label" class="flex justify-between gap-4 px-5 py-3">
                    <dt class="text-slate-500">{{ row.label }}</dt>
                    <dd class="text-right font-medium text-slate-800">{{ row.value || '-' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Notes -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Notes</h3>
            </div>
            <div class="card-body whitespace-pre-line text-sm text-slate-600">
                {{ expense.notes || 'No notes.' }}
            </div>
        </div>
    </div>

    <!-- Printable voucher (visible only when printing) -->
    <ExpenseSheet v-if="expense" :doc="expense" />

    <CancelDialog
        v-model="showCancel"
        title="Cancel Expense"
        :description="`This will cancel ${expense?.reference_no}. It will no longer be counted in totals and reports, and cannot be edited.`"
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
import ExpenseSheet from '../components/ui/expensesheet.vue';
import CancelDialog from '../components/ui/canceldialog.vue';
import { paymentMethods } from '../config/payments';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const expense = ref(null);
const loading = ref(true);
const notice = ref('');

const showCancel = ref(false);
const cancelling = ref(false);
const cancelError = ref('');

const isCancelled = computed(() => expense.value?.status === 'cancelled');

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const formatDateTime = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

const rows = computed(() => [
    { label: 'Number', value: expense.value.reference_no },
    { label: 'Date', value: formatDate(expense.value.expense_date) },
    { label: 'Category', value: expense.value.category?.name },
    { label: 'Paid to', value: expense.value.paid_to },
    { label: 'Description', value: expense.value.description },
    { label: 'Reference', value: expense.value.transaction_ref },
    { label: 'Created', value: formatDateTime(expense.value.created_at) },
]);

async function loadExpense() {
    const { data } = await api.get(`/api/expenses/${route.params.id}`);
    expense.value = data.data;
}

function flash(message) {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 3000);
}

function printDoc() {
    // The browser uses the page title as the default PDF file name
    const original = document.title;
    document.title = expense.value.reference_no;
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
        await api.post(`/api/expenses/${expense.value.id}/cancel`, { reason });
        showCancel.value = false;
        await loadExpense();
        flash('Expense cancelled successfully.');
    } catch (e) {
        const data = e.response?.data;
        cancelError.value = data?.errors?.reason?.[0] ?? data?.message ?? 'Something went wrong. Please try again.';
    } finally {
        cancelling.value = false;
    }
}

onMounted(async () => {
    try {
        await loadExpense();
    } catch {
        router.replace('/expenses');
        return;
    }
    loading.value = false;
});
</script>