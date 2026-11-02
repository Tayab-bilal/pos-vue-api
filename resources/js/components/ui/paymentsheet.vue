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
                <h2 class="text-xl font-bold tracking-wide uppercase">{{ kind.document }}</h2>
                <p class="mt-2"><span class="text-slate-500">No:</span> <strong>{{ doc.reference_no }}</strong></p>
                <p><span class="text-slate-500">Date:</span> {{ formatDate(doc.payment_date) }}</p>
                <p><span class="text-slate-500">Method:</span> {{ paymentMethods[doc.method] ?? doc.method }}</p>
                <p v-if="doc.transaction_ref"><span class="text-slate-500">Ref:</span> {{ doc.transaction_ref }}</p>
                <p><span class="text-slate-500">Status:</span> {{ isCancelled ? 'Cancelled' : 'Completed' }}</p>
            </div>
        </div>

        <!-- Party -->
        <div class="mt-4 rounded border border-slate-300 p-3">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                {{ isReceipt ? 'Received from' : 'Paid to' }}
            </p>
            <p class="text-base font-semibold">{{ doc.party_name || '-' }}</p>
            <p v-if="doc.party_phone">Phone: {{ doc.party_phone }}</p>
        </div>

        <!-- Amount -->
        <div class="mt-4 flex items-center justify-between rounded border-2 border-slate-800 px-4 py-3">
            <span class="text-sm font-semibold tracking-wide uppercase">{{ isReceipt ? 'Amount received' : 'Amount paid' }}</span>
            <span class="text-2xl font-bold">{{ company.currency }} {{ money(doc.amount) }}</span>
        </div>

        <!-- Allocations -->
        <table class="mt-4 w-full border-collapse">
            <thead>
                <tr class="border-b-2 border-slate-800 text-left text-xs uppercase">
                    <th class="py-2">Settled against</th>
                    <th class="py-2">Date</th>
                    <th class="py-2 text-right">Invoice Total</th>
                    <th class="py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="Number(doc.opening_amount) > 0" class="border-b border-slate-200 print:break-inside-avoid">
                    <td class="py-2 font-medium">Opening balance</td>
                    <td class="py-2 text-slate-500">-</td>
                    <td class="py-2 text-right text-slate-500">-</td>
                    <td class="py-2 text-right font-medium">{{ money(doc.opening_amount) }}</td>
                </tr>
                <tr v-for="line in doc.allocations" :key="line.id" class="border-b border-slate-200 print:break-inside-avoid">
                    <td class="py-2 font-medium">{{ invoiceOf(line)?.reference_no }}</td>
                    <td class="py-2 text-slate-500">{{ formatDate(isReceipt ? invoiceOf(line)?.sale_date : invoiceOf(line)?.purchase_date) }}</td>
                    <td class="py-2 text-right text-slate-500">{{ money(invoiceOf(line)?.total) }}</td>
                    <td class="py-2 text-right font-medium">{{ money(line.amount) }}</td>
                </tr>
            </tbody>
        </table>
        <p class="mt-1 text-right text-xs text-slate-400">All amounts are in {{ company.currency }}</p>

        <!-- Notes -->
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
            <div class="border-t border-slate-400 pt-1">{{ isReceipt ? 'Received by' : 'Paid by' }}</div>
            <div class="border-t border-slate-400 pt-1">{{ isReceipt ? 'Customer signature' : 'Supplier signature' }}</div>
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
import { paymentKinds, paymentMethods } from '../../config/payments';

const props = defineProps({
    doc: { type: Object, required: true },
});

const isReceipt = computed(() => props.doc.type === 'receipt');
const kind = computed(() => paymentKinds[props.doc.type]);
const isCancelled = computed(() => props.doc.status === 'cancelled');

const invoiceOf = (line) => (isReceipt.value ? line.sale : line.purchase);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const printedOn = new Date().toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>