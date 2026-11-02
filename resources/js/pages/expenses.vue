<template>
    <PageHeader title="Expenses" subtitle="Track the running costs of your business">
        <RouterLink to="/expenses/create" class="btn btn-primary">
            <AppIcon name="plus" size="h-4 w-4" />
            Add Expense
        </RouterLink>
    </PageHeader>

    <ExpenseTabs />

    <div v-if="notice" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ notice }}
    </div>

    <!-- Summary cards: they follow the filters below, and leave out cancelled expenses -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">Total Expenses</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(summary.total) }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-sm text-slate-500">Number of Expenses</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ Number(summary.count ?? 0) }}</p>
                <p class="mt-1 text-xs text-slate-400">Cancelled expenses are not counted</p>
            </div>
        </div>
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs" placeholder="Search number, paid to or description..." />
            <select v-model="categoryFilter" class="form-input max-w-48">
                <option value="">All categories</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="method" class="form-input max-w-44">
                <option value="">All methods</option>
                <option v-for="(label, key) in paymentMethods" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="docStatus" class="form-input max-w-40">
                <option value="">All status</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <input v-model="dateFrom" type="date" class="form-input w-40" aria-label="From date" />
                <span>to</span>
                <input v-model="dateTo" type="date" class="form-input w-40" aria-label="To date" />
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Details</th>
                        <th>Method</th>
                        <th class="text-right">Amount</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="8" class="py-10 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="8" class="py-10 text-center text-slate-500">No expenses found.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="item in items" :key="item.id" :class="{ 'opacity-60': item.status === 'cancelled' }">
                            <td>
                                <RouterLink :to="`/expenses/${item.id}`" class="font-medium text-slate-800 hover:text-brand-700">
                                    {{ item.reference_no }}
                                </RouterLink>
                            </td>
                            <td class="text-slate-500">{{ formatDate(item.expense_date) }}</td>
                            <td class="text-slate-800">{{ item.category?.name }}</td>
                            <td>
                                <p class="text-slate-800">{{ item.description || '-' }}</p>
                                <p v-if="item.paid_to" class="text-xs text-slate-400">Paid to {{ item.paid_to }}</p>
                            </td>
                            <td class="text-slate-500">{{ paymentMethods[item.method] ?? item.method }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(item.amount) }}</td>
                            <td>
                                <span class="badge" :class="item.status === 'cancelled' ? 'badge-danger' : 'badge-success'">
                                    {{ item.status === 'cancelled' ? 'Cancelled' : 'Completed' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="`/expenses/${item.id}`" class="icon-btn" title="View" aria-label="View">
                                        <AppIcon name="eye" size="h-4 w-4" />
                                    </RouterLink>
                                    <RouterLink
                                        v-if="item.status === 'completed'"
                                        :to="`/expenses/${item.id}/edit`"
                                        class="icon-btn"
                                        title="Edit"
                                        aria-label="Edit"
                                    >
                                        <AppIcon name="pencil" size="h-4 w-4" />
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
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import ExpenseTabs from '../components/ui/expensetabs.vue';
import { paymentMethods } from '../config/payments';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const items = ref([]);
const meta = ref({});
const summary = ref({});
const categories = ref([]);
const loading = ref(false);
const notice = ref('');

const search = ref('');
const categoryFilter = ref('');
const method = ref('');
const docStatus = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const page = ref(1);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

async function loadCategories() {
    try {
        const { data } = await api.get('/api/expense-categories', { params: { per_page: 100 } });
        categories.value = data.data.data;
    } catch {
        categories.value = [];
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/expenses', {
            params: {
                search: search.value,
                expense_category_id: categoryFilter.value,
                method: method.value,
                status: docStatus.value,
                date_from: dateFrom.value,
                date_to: dateTo.value,
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

function flash(message) {
    notice.value = message;
    setTimeout(() => (notice.value = ''), 3000);
}

// Wait briefly after typing so we don't send a request on every keystroke
let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        page.value === 1 ? load() : (page.value = 1);
    }, 400);
});
watch([categoryFilter, method, docStatus, dateFrom, dateTo], () => {
    page.value === 1 ? load() : (page.value = 1);
});
watch(page, load);

onMounted(() => {
    // Show a success message sent from the Add/Edit page
    if (route.query.saved) {
        flash(route.query.saved === 'updated' ? 'Expense updated successfully.' : 'Expense created successfully.');
        router.replace({ path: '/expenses' });
    }
    loadCategories();
    load();
});
</script>