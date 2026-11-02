<template>
    <PageHeader :title="customer?.name ?? 'Customer Details'" subtitle="Complete customer information">
        <RouterLink v-if="customer" :to="`/ledgers/customers/${customer.id}`" class="btn btn-secondary">View Ledger
        </RouterLink>
        <RouterLink to="/customers" class="btn btn-secondary">Back to Customers</RouterLink>
        <RouterLink v-if="customer" :to="`/ledgers/customers/${customer.id}`" class="btn btn-secondary">View Ledger
        </RouterLink>
        <RouterLink v-if="customer && customer.is_active" :to="`/payments/receipts/create?customer_id=${customer.id}`"
            class="btn btn-secondary">
            Receive Payment
        </RouterLink>
        <RouterLink v-if="customer" :to="`/customers/${customer.id}/edit`" class="btn btn-primary">Edit Customer
        </RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="customer" class="space-y-6">
        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Outstanding Balance</p>
                    <p class="mt-2 text-2xl font-bold"
                        :class="Number(customer.balance?.outstanding) > 0 ? 'text-red-600' : 'text-slate-800'">
                        {{ money(customer.balance?.outstanding) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">Opening {{ money(customer.balance?.opening_balance) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Credit Limit</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">
                        {{ Number(customer.credit_limit) > 0 ? money(customer.credit_limit) : 'No limit' }}
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Total Sales</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(customer.balance?.documents_total) }}</p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ customer.balance?.documents_count ?? 0 }} invoices, paid {{
                            money(customer.balance?.paid_total) }}
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Status</p>
                    <span class="badge mt-3" :class="customer.is_active ? 'badge-success' : 'badge-danger'">
                        {{ customer.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Contact Information</h3>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div v-for="row in contactRows" :key="row.label" class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">{{ row.label }}</dt>
                        <dd class="text-right font-medium text-slate-800">{{ row.value || '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="font-semibold text-slate-800">Address &amp; Notes</h3>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="px-5 py-3">
                        <dt class="text-slate-500">Address</dt>
                        <dd class="mt-1 whitespace-pre-line font-medium text-slate-800">{{ customer.address || '-' }}
                        </dd>
                    </div>
                    <div class="px-5 py-3">
                        <dt class="text-slate-500">Notes</dt>
                        <dd class="mt-1 whitespace-pre-line font-medium text-slate-800">{{ customer.notes || '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Created</dt>
                        <dd class="font-medium text-slate-800">{{ formatDate(customer.created_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Last Updated</dt>
                        <dd class="font-medium text-slate-800">{{ formatDate(customer.updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const customer = ref(null);
const loading = ref(true);

const money = (value) =>
    Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

const contactRows = computed(() => [
    { label: 'Name', value: customer.value.name },
    { label: 'Company', value: customer.value.company_name },
    { label: 'Phone', value: customer.value.phone },
    { label: 'Email', value: customer.value.email },
    { label: 'City', value: customer.value.city },
]);

onMounted(async () => {
    try {
        const { data } = await api.get(`/api/customers/${route.params.id}`);
        customer.value = data.data;
    } catch {
        router.replace('/customers');
        return;
    }
    loading.value = false;
});
</script>