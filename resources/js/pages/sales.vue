<template>
    <PageHeader title="Sales" subtitle="Track invoices and payments from customers">
        <RouterLink to="/sales/create" class="btn btn-primary">
            <AppIcon name="plus" size="h-4 w-4" />
            New Sale
        </RouterLink>
    </PageHeader>

    <div v-if="notice"
        class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ notice }}
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs"
                placeholder="Search invoice, customer or phone..." />
            <select v-model="paymentStatus" class="form-input max-w-40">
                <option value="">All payments</option>
                <option value="paid">Paid</option>
                <option value="partial">Partial</option>
                <option value="unpaid">Unpaid</option>
            </select>
            <select v-model="docStatus" class="form-input max-w-40">
                <option value="">All documents</option>
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
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th class="text-right">Items</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Due</th>
                        <th>Payment</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="py-10 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="9" class="py-10 text-center text-slate-500">No sales found.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="item in items" :key="item.id" :class="{ 'opacity-60': item.status === 'cancelled' }">
                            <td>
                                <RouterLink :to="`/sales/${item.id}`"
                                    class="font-medium text-slate-800 hover:text-brand-700">
                                    {{ item.reference_no }}
                                </RouterLink>
                            </td>
                            <td class="text-slate-500">{{ formatDate(item.sale_date) }}</td>
                            <td>
                                <p class="text-slate-800">{{ item.customer_name || '-' }}</p>
                                <p v-if="!item.customer_id" class="text-xs text-slate-400">Walk-in</p>
                            </td>
                            <td class="text-right text-slate-500">{{ item.items_count }}</td>
                            <td class="text-right font-medium text-slate-800">{{ money(item.total) }}</td>
                            <td class="text-right text-slate-500">{{ money(item.paid_amount) }}</td>
                            <td class="text-right text-slate-500">
                                {{ item.status === 'cancelled' ? '-' : money(Number(item.total) -
                                Number(item.paid_amount)) }}
                            </td>
                            <td>
                                <span v-if="item.status === 'cancelled'" class="badge badge-danger">Cancelled</span>
                                <span v-else class="badge" :class="statusClass(item.payment_status)">{{
                                    statusLabel(item.payment_status) }}</span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="`/sales/${item.id}`" class="icon-btn" title="View"
                                        aria-label="View">
                                        <AppIcon name="eye" size="h-4 w-4" />
                                    </RouterLink>
                                    <RouterLink v-if="item.status === 'completed'" :to="`/purchases/${item.id}/edit`"
                                        class="icon-btn" title="Edit" aria-label="Edit">
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
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
            <p>Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}</p>
            <div class="flex items-center gap-2">
                <button class="btn btn-secondary px-3! py-1!" :disabled="page <= 1" @click="page--">Previous</button>
                <span>Page {{ page }} / {{ meta.last_page ?? 1 }}</span>
                <button class="btn btn-secondary px-3! py-1!" :disabled="page >= (meta.last_page ?? 1)"
                    @click="page++">Next</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const items = ref([]);
const meta = ref({});
const loading = ref(false);
const notice = ref('');

const search = ref('');
const paymentStatus = ref('');
const docStatus = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const page = ref(1);

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

const statusClass = (s) => ({ paid: 'badge-success', partial: 'badge-warning', unpaid: 'badge-danger' })[s] ?? '';
const statusLabel = (s) => ({ paid: 'Paid', partial: 'Partial', unpaid: 'Unpaid' })[s] ?? s;

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/sales', {
            params: {
                search: search.value,
                payment_status: paymentStatus.value,
                status: docStatus.value,
                date_from: dateFrom.value,
                date_to: dateTo.value,
                page: page.value,
            },
        });
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
watch([paymentStatus, docStatus, dateFrom, dateTo], () => {
    page.value === 1 ? load() : (page.value = 1);
});
watch(page, load);

onMounted(() => {
    // Show a success message sent from the New Sale page
    if (route.query.saved) {
        flash(route.query.saved === 'updated' ? 'Sale updated successfully.' : 'Sale created successfully.');
        router.replace({ path: '/sales' });
    }
    load();
});
</script>