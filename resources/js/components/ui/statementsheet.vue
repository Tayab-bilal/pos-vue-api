<template>
    <div class="mx-auto hidden w-full max-w-[210mm] bg-white text-[12px] leading-relaxed text-slate-800 print:block print:max-w-none">
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
                <h2 class="text-xl font-bold tracking-wide uppercase">Account Statement</h2>
                <p class="mt-2"><span class="text-slate-500">Period:</span> {{ periodLabel }}</p>
                <p><span class="text-slate-500">Printed:</span> {{ printedOn }}</p>
            </div>
        </div>

        <!-- Party -->
        <div class="mt-4 flex items-start justify-between gap-4 rounded border border-slate-300 p-3">
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ isCustomer ? 'Customer' : 'Supplier' }}</p>
                <p class="text-base font-semibold">{{ ledger.party.name }}</p>
                <p v-if="ledger.party.company_name">{{ ledger.party.company_name }}</p>
                <p v-if="ledger.party.phone">Phone: {{ ledger.party.phone }}</p>
                <p v-if="ledger.party.address || ledger.party.city">
                    {{ [ledger.party.address, ledger.party.city].filter(Boolean).join(', ') }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    {{ isCustomer ? 'Amount due from customer' : 'Amount due to supplier' }}
                </p>
                <p class="text-xl font-bold">{{ company.currency }} {{ money(ledger.closing_balance) }}</p>
            </div>
        </div>

        <!-- Statement -->
        <table class="mt-4 w-full border-collapse">
            <thead>
                <tr class="border-b-2 border-slate-800 text-left text-[11px] uppercase">
                    <th class="py-2">Date</th>
                    <th class="py-2">Entry</th>
                    <th class="py-2">Reference</th>
                    <th class="py-2 text-right">Debit</th>
                    <th class="py-2 text-right">Credit</th>
                    <th class="py-2 text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-300 bg-slate-100 print:break-inside-avoid">
                    <td class="py-1.5">{{ ledger.from ? formatDate(ledger.from) : '' }}</td>
                    <td class="py-1.5 font-medium" colspan="4">{{ ledger.from ? 'Balance brought forward' : 'Opening balance' }}</td>
                    <td class="py-1.5 text-right font-semibold">{{ money(ledger.opening_balance) }}</td>
                </tr>
                <tr v-for="(entry, index) in ledger.entries" :key="index" class="border-b border-slate-200 print:break-inside-avoid">
                    <td class="py-1.5 whitespace-nowrap">{{ formatDate(entry.date) }}</td>
                    <td class="py-1.5">
                        <p class="font-medium">{{ entry.label }}</p>
                        <p v-if="entry.note" class="text-[10px] text-slate-500">{{ entry.note }}</p>
                    </td>
                    <td class="py-1.5">{{ entry.reference_no || '-' }}</td>
                    <td class="py-1.5 text-right">{{ entry.debit > 0 ? money(entry.debit) : '' }}</td>
                    <td class="py-1.5 text-right">{{ entry.credit > 0 ? money(entry.credit) : '' }}</td>
                    <td class="py-1.5 text-right font-semibold">{{ money(entry.balance) }}</td>
                </tr>
                <tr v-if="!ledger.entries.length">
                    <td colspan="6" class="py-4 text-center text-slate-500">No entries in this period.</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-bold print:break-inside-avoid">
                    <td class="py-2" colspan="3">Totals</td>
                    <td class="py-2 text-right">{{ money(ledger.total_debit) }}</td>
                    <td class="py-2 text-right">{{ money(ledger.total_credit) }}</td>
                    <td class="py-2 text-right">{{ money(ledger.closing_balance) }}</td>
                </tr>
            </tfoot>
        </table>
        <p class="mt-1 text-right text-[10px] text-slate-400">All amounts are in {{ company.currency }}</p>

        <!-- Explanation -->
        <p class="mt-3 text-[11px] text-slate-500">
            {{
                isCustomer
                    ? 'Debit increases what you owe us (sales). Credit reduces it (payments received and returns). A positive closing balance is the amount you owe us.'
                    : 'Credit increases what we owe you (purchases). Debit reduces it (payments made and returns). A positive closing balance is the amount we owe you.'
            }}
        </p>

        <!-- Signatures -->
        <div class="mt-14 grid grid-cols-2 gap-16 text-center text-xs text-slate-500 print:break-inside-avoid">
            <div class="border-t border-slate-400 pt-1">Prepared by</div>
            <div class="border-t border-slate-400 pt-1">{{ isCustomer ? 'Customer signature' : 'Supplier signature' }}</div>
        </div>

        <div class="mt-8 border-t border-slate-200 pt-2 text-center text-xs text-slate-400">
            <p v-if="company.footerNote">{{ company.footerNote }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { company } from '../../config/company';

const props = defineProps({
    ledger: { type: Object, required: true },
});

const isCustomer = computed(() => props.ledger.side === 'customer');

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const periodLabel = computed(() => {
    const { from, to } = props.ledger;
    if (from && to) return `${formatDate(from)} to ${formatDate(to)}`;
    if (from) return `From ${formatDate(from)}`;
    if (to) return `Up to ${formatDate(to)}`;
    return 'All entries';
});

const printedOn = new Date().toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>