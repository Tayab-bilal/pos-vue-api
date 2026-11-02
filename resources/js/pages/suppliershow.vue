<template>
    <PageHeader :title="supplier?.name ?? 'Supplier Details'" subtitle="Complete supplier information">
        <RouterLink to="/suppliers" class="btn btn-secondary">Back to Suppliers</RouterLink>
        <RouterLink v-if="supplier" :to="`/ledgers/suppliers/${supplier.id}`" class="btn btn-secondary">View Ledger</RouterLink>
        <RouterLink v-if="supplier && supplier.is_active" :to="`/payments/suppliers/create?supplier_id=${supplier.id}`"
            class="btn btn-secondary">
            Make Payment
        </RouterLink>
        <RouterLink v-if="supplier" :to="`/suppliers/${supplier.id}/edit`" class="btn btn-primary">Edit Supplier
        </RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <div v-else-if="supplier" class="space-y-6">
        <!-- Summary cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Outstanding (we owe)</p>
                    <p class="mt-2 text-2xl font-bold"
                        :class="Number(supplier.balance?.outstanding) > 0 ? 'text-red-600' : 'text-slate-800'">
                        {{ money(supplier.balance?.outstanding) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">Opening {{ money(supplier.balance?.opening_balance) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Total Purchases</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(supplier.balance?.documents_total) }}</p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ supplier.balance?.documents_count ?? 0 }} purchases, paid {{
                            money(supplier.balance?.paid_total) }}
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Returns to Supplier</p>
                    <p class="mt-2 text-2xl font-bold text-slate-800">{{ money(supplier.balance?.returns_total) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-slate-500">Status</p>
                    <span class="badge mt-3" :class="supplier.is_active ? 'badge-success' : 'badge-danger'">
                        {{ supplier.is_active ? 'Active' : 'Inactive' }}
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
                        <dd class="mt-1 whitespace-pre-line font-medium text-slate-800">{{ supplier.address || '-' }}
                        </dd>
                    </div>
                    <div class="px-5 py-3">
                        <dt class="text-slate-500">Notes</dt>
                        <dd class="mt-1 whitespace-pre-line font-medium text-slate-800">{{ supplier.notes || '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Created</dt>
                        <dd class="font-medium text-slate-800">{{ formatDate(supplier.created_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Last Updated</dt>
                        <dd class="font-medium text-slate-800">{{ formatDate(supplier.updated_at) }}</dd>
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

const supplier = ref(null);
const loading = ref(true);

const money = (value) =>
    Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '-';

const contactRows = computed(() => [
    { label: 'Name', value: supplier.value.name },
    { label: 'Company', value: supplier.value.company_name },
    { label: 'Phone', value: supplier.value.phone },
    { label: 'Email', value: supplier.value.email },
    { label: 'City', value: supplier.value.city },
    { label: 'Tax Number', value: supplier.value.tax_number },
]);

onMounted(async () => {
    try {
        const { data } = await api.get(`/api/suppliers/${route.params.id}`);
        supplier.value = data.data;
    } catch {
        router.replace('/suppliers');
        return;
    }
    loading.value = false;
});
</script>