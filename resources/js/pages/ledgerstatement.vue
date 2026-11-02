<template>
    <PageHeader :title="ledger?.party?.name ?? kind.title" :subtitle="kind.title" class="print:hidden">
        <RouterLink :to="kind.listPath" class="btn btn-secondary">Back to Ledgers</RouterLink>
        <template v-if="ledger">
            <RouterLink :to="kind.partyPath(ledger.party.id)" class="btn btn-secondary">View {{ kind.party }}</RouterLink>
            <button type="button" class="btn btn-secondary" @click="printStatement">
                <AppIcon name="printer" size="h-4 w-4" />
                Print Statement
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="card print:hidden">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="ledger" class="space-y-6 print:hidden">
        <!-- Date range -->
        <div class="card">
            <div class="card-body flex flex-wrap items-end gap-3">
                <div>
                    <label class="form-label">From</label>
                    <input v-model="from" type="date" class="form-input w-44" />
                </div>
                <div>
                    <label class="form-label">To</label>
                    <input v-model="to" type="date" class="form-input w-44" />
                </div>
                <button type="button" class="btn btn-secondary" :disabled="!from && !to" @click="clearDates">Clear dates</button>
                <p v-if="dateError" class="form-error">{{ dateError }}</p>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">{{ ledger.from ? 'Balance brought forward' : 'Opening Balance' }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(ledger.opening_balance) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Total Debit</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(ledger.total_debit) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Total Credit</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(ledger.total_credit) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Closing Balance</p>
                    <p class="mt-2 text-2xl font-bold" :class="Number(ledger.closing_balance) > 0 ? 'text-red-600' : 'text-slate-800'">
                        {{ money(ledger.closing_balance) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">{{ isCustomer ? 'They owe you' : 'You owe them' }}</p>
                </div>
            </div>
        </div>

        <!-- Statement -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Statement</h3>
                <p class="text-xs text-slate-500">
                    {{ isCustomer ? 'Debit increases what the customer owes. Credit reduces it.' : 'Credit increases what you owe. Debit reduces it.' }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entry</th>
                            <th>Reference</th>
                            <th>Details</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-slate-50">
                            <td class="text-slate-500">{{ ledger.from ? formatDate(ledger.from) : '-' }}</td>
                            <td class="font-medium text-slate-800" colspan="3">
                                {{ ledger.from ? 'Balance brought forward' : 'Opening balance' }}
                            </td>
                            <td></td>
                            <td></td>
                            <td class="text-right font-semibold text-slate-800">{{ money(ledger.opening_balance) }}</td>
                        </tr>
                        <tr v-if="!ledger.entries.length">
                            <td colspan="7" class="py-8 text-center text-slate-500">No entries in this period.</td>
                        </tr>
                        <tr v-for="(entry, index) in ledger.entries" :key="index">
                            <td class="whitespace-nowrap text-slate-500">{{ formatDate(entry.date) }}</td>
                            <td class="font-medium text-slate-800">{{ entry.label }}</td>
                            <td>
                                <RouterLink v-if="entryLink(entry)" :to="entryLink(entry)" class="text-slate-700 hover:text-brand-700">
                                    {{ entry.reference_no }}
                                </RouterLink>
                                <span v-else class="text-slate-500">{{ entry.reference_no || '-' }}</span>
                            </td>
                            <td class="max-w-56 truncate text-slate-500">{{ entry.note || '-' }}</td>
                            <td class="text-right text-slate-800">{{ entry.debit > 0 ? money(entry.debit) : '' }}</td>
                            <td class="text-right text-slate-800">{{ entry.credit > 0 ? money(entry.credit) : '' }}</td>
                            <td class="text-right font-semibold text-slate-800">{{ money(entry.balance) }}</td>
                        </tr>
                        <tr class="border-t-2 border-slate-200 bg-slate-50">
                            <td colspan="4" class="font-semibold text-slate-800">Totals and closing balance</td>
                            <td class="text-right font-semibold text-slate-800">{{ money(ledger.total_debit) }}</td>
                            <td class="text-right font-semibold text-slate-800">{{ money(ledger.total_credit) }}</td>
                            <td class="text-right font-bold" :class="Number(ledger.closing_balance) > 0 ? 'text-red-600' : 'text-slate-800'">
                                {{ money(ledger.closing_balance) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p v-if="ledger.from || ledger.to" class="text-xs text-slate-400">
            Showing a date range. Everything before the start date is included in the balance brought forward.
            The current total outstanding is {{ money(ledger.current_outstanding) }}.
        </p>
    </div>

    <!-- Printable statement (visible only when printing) -->
    <StatementSheet v-if="ledger" :ledger="ledger" />
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import StatementSheet from '../components/ui/statementsheet.vue';
import { entryLink, ledgerKinds } from '../config/ledger';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

// This page serves both statements. The router tells us which one.
const kind = computed(() => ledgerKinds[route.meta.kind]);
const isCustomer = computed(() => route.meta.kind === 'customer');

const ledger = ref(null);
const loading = ref(true);
const dateError = ref('');

// The date range lives in the address, so a refresh or a shared link keeps it
const from = ref(route.query.from ?? '');
const to = ref(route.query.to ?? '');

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

async function load() {
    dateError.value = '';

    if (from.value && to.value && to.value < from.value) {
        dateError.value = 'The end date cannot be before the start date.';
        return;
    }

    try {
        const { data } = await api.get(kind.value.statementApi(route.params.id), {
            params: { from: from.value || undefined, to: to.value || undefined },
        });
        ledger.value = data.data;
    } catch {
        router.replace(kind.value.listPath);
        return;
    }
    loading.value = false;
}

function clearDates() {
    from.value = '';
    to.value = '';
}

function printStatement() {
    // The browser uses the page title as the default PDF file name
    const original = document.title;
    document.title = `Statement - ${ledger.value.party.name}`;
    window.print();
    document.title = original;
}

watch([from, to], () => {
    router.replace({ path: route.path, query: { from: from.value || undefined, to: to.value || undefined } });
    load();
});

onMounted(load);
</script>