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
                <h2 class="text-xl font-bold tracking-wide uppercase">Stock Adjustment</h2>
                <p class="mt-2"><span class="text-slate-500">No:</span> <strong>{{ doc.reference_no }}</strong></p>
                <p><span class="text-slate-500">Date:</span> {{ formatDate(doc.adjustment_date) }}</p>
                <p><span class="text-slate-500">Type:</span> {{ adjustmentTypes[doc.type]?.label ?? doc.type }}</p>
                <p><span class="text-slate-500">Status:</span> {{ isCancelled ? 'Cancelled' : 'Completed' }}</p>
            </div>
        </div>

        <!-- Reason -->
        <div v-if="doc.reason" class="mt-4 rounded border border-slate-300 p-3">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Reason</p>
            <p class="text-base font-semibold">{{ doc.reason }}</p>
        </div>

        <!-- Items -->
        <table class="mt-4 w-full border-collapse">
            <thead>
                <tr class="border-b-2 border-slate-800 text-left text-xs uppercase">
                    <th class="w-10 py-2">#</th>
                    <th class="py-2">Product</th>
                    <th class="py-2 text-right">Before</th>
                    <th class="py-2 text-right">Change</th>
                    <th class="py-2 text-right">After</th>
                    <th class="py-2 text-right">Unit Cost</th>
                    <th class="py-2 text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, index) in doc.items" :key="line.id" class="border-b border-slate-200 print:break-inside-avoid">
                    <td class="py-2 text-slate-500">{{ index + 1 }}</td>
                    <td class="py-2">
                        <p class="font-medium">{{ line.product?.name }}</p>
                        <p class="text-xs text-slate-500">{{ line.product?.sku }}</p>
                    </td>
                    <td class="py-2 text-right">{{ Number(line.quantity_before) }}</td>
                    <td class="py-2 text-right font-medium">{{ signed(line.difference) }}</td>
                    <td class="py-2 text-right">{{ Number(line.quantity_after) }} {{ line.product?.unit?.short_name }}</td>
                    <td class="py-2 text-right">{{ money(line.unit_cost) }}</td>
                    <td class="py-2 text-right font-medium">{{ money(Number(line.difference) * Number(line.unit_cost)) }}</td>
                </tr>
            </tbody>
        </table>
        <p class="mt-1 text-right text-xs text-slate-400">All amounts are in {{ company.currency }}</p>

        <!-- Totals -->
        <div class="mt-4 flex justify-end print:break-inside-avoid">
            <dl class="w-72 space-y-1">
                <div class="flex justify-between border-t-2 border-slate-800 pt-1 text-base font-bold">
                    <dt>Net value change</dt>
                    <dd>{{ money(doc.value_change) }}</dd>
                </div>
            </dl>
        </div>

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
            <div class="border-t border-slate-400 pt-1">Prepared by</div>
            <div class="border-t border-slate-400 pt-1">Approved by</div>
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
import { adjustmentTypes } from '../../config/stock';

const props = defineProps({
    doc: { type: Object, required: true },
});

const isCancelled = computed(() => props.doc.status === 'cancelled');

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const signed = (value) => (Number(value) > 0 ? `+${Number(value)}` : `${Number(value)}`);

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const printedOn = new Date().toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>