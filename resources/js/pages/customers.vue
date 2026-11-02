<template>
    <PageHeader title="Customers" subtitle="Manage the customers you sell to">
        <RouterLink to="/customers/create" class="btn btn-primary">
            <AppIcon name="plus" size="h-4 w-4" />
            Add Customer
        </RouterLink>
    </PageHeader>

    <div v-if="notice" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ notice }}
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs" placeholder="Search name, company, phone or email..." />
            <select v-model="status" class="form-input max-w-40">
                <option value="">All status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th class="text-right">Opening Balance</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-right">Credit Limit</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="py-10 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="9" class="py-10 text-center text-slate-500">No customers found.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="(item, index) in items" :key="item.id">
                            <td class="text-slate-500">{{ (meta.from ?? 1) + index }}</td>
                            <td>
                                <RouterLink :to="`/customers/${item.id}`" class="font-medium text-slate-800 hover:text-brand-700">{{ item.name }}</RouterLink>
                                <p v-if="item.company_name" class="text-xs text-slate-400">{{ item.company_name }}</p>
                            </td>
                            <td class="text-slate-500">{{ item.phone || '-' }}</td>
                            <td class="text-slate-500">{{ item.city || '-' }}</td>
                            <td class="text-right text-slate-500">{{ money(item.opening_balance) }}</td>
                            <td class="text-right">
                                <span class="font-medium" :class="Number(item.outstanding) > 0 ? 'text-red-600' : 'text-slate-800'">
                                    {{ money(item.outstanding) }}
                                </span>
                                <p v-if="overLimit(item)" class="text-xs text-red-500">Over credit limit</p>
                            </td>
                            <td class="text-right text-slate-500">
                                {{ Number(item.credit_limit) > 0 ? money(item.credit_limit) : 'No limit' }}
                            </td>
                            <td>
                                <span class="badge" :class="item.is_active ? 'badge-success' : 'badge-danger'">
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <RouterLink :to="`/customers/${item.id}`" class="icon-btn" title="View" aria-label="View">
                                        <AppIcon name="eye" size="h-4 w-4" />
                                    </RouterLink>
                                    <RouterLink :to="`/customers/${item.id}/edit`" class="icon-btn" title="Edit" aria-label="Edit">
                                        <AppIcon name="pencil" size="h-4 w-4" />
                                    </RouterLink>
                                    <button class="icon-btn icon-btn-danger" title="Delete" aria-label="Delete" @click="askDelete(item)">
                                        <AppIcon name="trash" size="h-4 w-4" />
                                    </button>
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

    <!-- Delete confirmation -->
    <AppModal v-model="showDelete" title="Delete Customer" width="max-w-md">
        <p class="text-sm text-slate-600">
            Are you sure you want to delete <strong>{{ toDelete?.name }}</strong>? This action cannot be undone.
        </p>
        <p v-if="deleteError" class="form-error mt-3">{{ deleteError }}</p>
        <template #footer>
            <button class="btn btn-secondary" @click="showDelete = false">Cancel</button>
            <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">
                {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import AppModal from '../components/ui/appmodel.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const items = ref([]);
const meta = ref({});
const loading = ref(false);
const deleting = ref(false);
const notice = ref('');

const search = ref('');
const status = ref('');
const page = ref(1);

const showDelete = ref(false);
const toDelete = ref(null);
const deleteError = ref('');

const money = (value) => Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// A credit limit of 0 means no limit
const overLimit = (item) => Number(item.credit_limit) > 0 && Number(item.outstanding) > Number(item.credit_limit);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/customers', {
            params: { search: search.value, is_active: status.value, page: page.value },
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

function askDelete(item) {
    toDelete.value = item;
    deleteError.value = '';
    showDelete.value = true;
}

async function confirmDelete() {
    deleting.value = true;
    deleteError.value = '';
    try {
        await api.delete(`/api/customers/${toDelete.value.id}`);
        showDelete.value = false;
        flash('Customer deleted successfully.');
        if (items.value.length === 1 && page.value > 1) page.value--;
        await load();
    } catch (e) {
        deleteError.value = e.response?.data?.message ?? 'Something went wrong. Please try again.';
    } finally {
        deleting.value = false;
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
watch(status, () => {
    page.value === 1 ? load() : (page.value = 1);
});
watch(page, load);

onMounted(() => {
    // Show a success message sent from the Add/Edit page
    const saved = route.query.saved;
    if (saved) {
        flash(saved === 'created' ? 'Customer created successfully.' : 'Customer updated successfully.');
        router.replace({ path: '/customers' });
    }
    load();
});
</script>