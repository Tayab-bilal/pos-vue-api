<template>
    <div class="relative mx-auto hidden w-full max-w-[210mm] bg-white text-[13px] leading-relaxed text-slate-800 print:block print:max-w-none">
        <!-- Cancelled watermark -->
        <div v-if="isCancelled" class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <span class="-rotate-30 rounded border-4 border-red-300 px-6 py-2 text-6xl font-bold tracking-widest text-red-300 uppercase">
                Cancelled
            </span>
        </div>

        <!-- Header -->
        <div class="flex items-start justify-between border-b-2 border-slate-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold">{{ company.name }}</h1>
                <p v-if="company.tagline" class="text-slate-500">{{ company.tagline }}</p>
                <p v-if="company.address" class="mt-2">{{ company.address }}</p>
                <p>
                    <span v-if="company.phone">Phone: {{ company.phone }}</span>
                    <span v-if="company.phone && company.email"> | </span>
                    <span v-if="company.email">{{ company.email }}</span>
                </p>
                <p v-if="company.taxNumber">Tax No: {{ company.taxNumber }}</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold tracking-wide uppercase">Expense Voucher</h2>
                <p class="mt-2"><span class="text-slate-500">No:</span> <strong>{{ doc.reference_no }}</strong></p>
                <p><span class="text-slate-500">Date:</span> {{ formatDate(doc.expense_date) }}</p>
                <p><span class="text-slate-500">Status:</span> {{ isCancelled ? 'Cancelled' : 'Completed' }}</p>
            </div>
        </div>

        <!-- Paid to -->
        <div class="mt-4 rounded border border-slate-300 p-3">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Paid to</p>
            <p class="text-base font-semibold">{{ doc.paid_to || '-' }}</p>
        </div>

        <!-- Amount -->
        <div class="mt-4 flex items-center justify-between rounded border-2 border-slate-800 px-4 py-3">
            <span class="text-sm font-semibold tracking-wide uppercase">Amount paid</span>
            <span class="text-2xl font-bold">{{ company.currency }} {{ money(doc.amount) }}</span>
        </div>

        <!-- Details -->
        <table class="mt-4 w-full border-collapse">
            <tbody>
                <tr v-for="row in rows" :key="row.label" class="border-b border-slate-200 print:break-inside-avoid">
                    <td class="w-40 py-2 text-slate-500">{{ row.label }}</td>
                    <td class="py-2 font-medium">{{ row.value || '-' }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="doc.notes" class="mt-4 print:break-inside-avoid">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Notes</p>
            <p class="whitespace-pre-line">{{ doc.notes }}</p>
        </div>

        <div v-if="isCancelled" class="mt-4 text-red-600 print:break-inside-avoid">
            <p class="text-xs font-semibold tracking-wide uppercase">Cancelled</p>
            <p>{{ doc.cancel_reason }}</p>
        </div>

        <!-- Signatures -->
        <div class="mt-16 grid grid-cols-2 gap-16 text-center text-xs text-slate-500 print:break-inside-avoid">
            <div class="border-t border-slate-400 pt-1">Paid by</div>
            <div class="border-t border-slate-400 pt-1">Received by</div>
        </div>

        <!-- Footer -->
        <div class="mt-8 border-t border-slate-200 pt-2 text-center text-xs text-slate-400">
            <p v-if="company.footerNote">{{ company.footerNote }}</p>
            <p>Printed on {{ printedOn }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { company } from '../../config/company';
import { paymentMethods } from '../../config/payments';

const props = defineProps({
    doc: { type: Object, required: true },
});

const isCancelled = computed(() => props.doc.status === 'cancelled');

const rows = computed(() => [
    { label: 'Category', value: props.doc.category?.name },
    { label: 'Description', value: props.doc.description },
    { label: 'Method', value: paymentMethods[props.doc.method] ?? props.doc.method },
    { label: 'Reference', value: props.doc.transaction_ref },
]);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const printedOn = new Date().toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>