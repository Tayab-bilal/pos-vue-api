<template>
    <PageHeader title="Ledgers" :subtitle="isCustomer ? 'What your customers owe you' : 'What you owe your suppliers'" />

    <LedgerTabs />

    <!-- Summary cards -->
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">{{ kind.totalLabel }}</p>
                <p class="mt-2 text-2xl font-bold" :class="Number(summary.total_outstanding) > 0 ? 'text-red-600' : 'text-slate-800'">
                    {{ money(summary.total_outstanding) }}
                </p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">With a balance</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ Number(summary.with_balance ?? 0) }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">Total {{ kind.plural }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ Number(summary.parties ?? 0) }}</p>
            </div>
        </div>
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs" :placeholder="`Search ${kind.party.toLowerCase()}, company or phone...`" />
            <select v-model="status" class="form-input max-w-40">
                <option value="">All status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="onlyDue" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                Only with a balance
            </label>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ kind.party }}</th>
                        <th>Phone</th>
                        <th class="text-right">Opening Balance</th>
                        <th class="text-right">{{ kind.outstandingLabel }}</th>
                        <th v-if="isCustomer" class="text-right">Credit Limit</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="isCustomer ? 8 : 7" class="py-10 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td :colspan="isCustomer ? 8 : 7" class="py-10 text-center text-slate-500">No {{ kind.plural.toLowerCase() }} found.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="(item, index) in items" :key="item.id">
                            <td class="text-slate-500">{{ (meta.from ?? 1) + index }}</td>
                            <td>
                                <RouterLink :to="kind.statementPath(item.id)" class="font-medium text-slate-800 hover:text-brand-700">
                                    {{ item.name }}
                                </RouterLink>
                                <p v-if="item.company_name" class="text-xs text-slate-400">{{ item.company_name }}</p>
                            </td>
                            <td class="text-slate-500">{{ item.phone || '-' }}</td>
                            <td class="text-right text-slate-500">{{ money(item.opening_balance) }}</td>
                            <td class="text-right">
                                <span class="font-medium" :class="Number(item.outstanding) > 0 ? 'text-red-600' : 'text-slate-800'">
                                    {{ money(item.outstanding) }}
                                </span>
                                <p v-if="overLimit(item)" class="text-xs text-red-500">Over credit limit</p>
                            </td>
                            <td v-if="isCustomer" class="text-right text-slate-500">
                                {{ Number(item.credit_limit) > 0 ? money(item.credit_limit) : 'No limit' }}
                            </td>
                            <td>
                                <span class="badge" :class="item.is_active ? 'badge-success' : 'badge-danger'">
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="kind.statementPath(item.id)" class="icon-btn" title="View ledger" aria-label="View ledger">
                                        <AppIcon name="ledgers" size="h-4 w-4" />
                                    </RouterLink>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
            <p>Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}</p>
            <div class="flex items-center gap-2">
                <button class="btn btn-secondary px-3! py-1!" :disabled="page <= 1" @click="page--">Previous</button>
                <span>Page {{ page }} / {{ meta.last_page ?? 1 }}</span>
                <button class="btn btn-secondary px-3! py-1!" :disabled="page >= (meta.last_page ?? 1)" @click="page++">Next</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import LedgerTabs from '../components/ui/ledgertabs.vue';
import { ledgerKinds } from '../config/ledger';
import api from '../services/api';

const route = useRoute();

// This page serves both lists. The router tells us which one.
const kind = computed(() => ledgerKinds[route.meta.kind]);
const isCustomer = computed(() => route.meta.kind === 'customer');

const items = ref([]);
const meta = ref({});
const summary = ref({});
const loading = ref(false);

const search = ref('');
const status = ref('');
const onlyDue = ref(false);
const page = ref(1);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// A credit limit of 0 means no limit
const overLimit = (item) => isCustomer.value && Number(item.credit_limit) > 0 && Number(item.outstanding) > Number(item.credit_limit);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get(kind.value.listApi, {
            params: {
                search: search.value,
                is_active: status.value,
                only_due: onlyDue.value ? 1 : '',
                page: page.value,
            },
        });
        summary.value = data.summary ?? {};
        items.value = data.data.data;
        meta.value = data.data;
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

// Wait briefly after typing so we don't send a request on every keystroke
let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        page.value === 1 ? load() : (page.value = 1);
    }, 400);
});
watch([status, onlyDue], () => {
    page.value === 1 ? load() : (page.value = 1);
});
watch(page, load);

onMounted(load);
</script>