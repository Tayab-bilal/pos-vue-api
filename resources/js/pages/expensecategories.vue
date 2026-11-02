<template>
    <PageHeader title="Expense Categories" subtitle="Group your expenses, e.g. Rent, Electricity, Salaries">
        <button class="btn btn-primary" @click="openCreate">
            <AppIcon name="plus" size="h-4 w-4" />
            Add Category
        </button>
    </PageHeader>

    <ExpenseTabs />

    <div v-if="notice" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ notice }}
    </div>

    <div class="card">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <input v-model="search" type="text" class="form-input max-w-xs" placeholder="Search categories..." />
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
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-right">Expenses</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="py-10 text-center text-slate-500">Loading...</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="6" class="py-10 text-center text-slate-500">No expense categories found.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="(item, index) in items" :key="item.id">
                            <td class="text-slate-500">{{ (meta.from ?? 1) + index }}</td>
                            <td class="font-medium text-slate-800">{{ item.name }}</td>
                            <td class="max-w-xs truncate text-slate-500">{{ item.description || '-' }}</td>
                            <td class="text-right text-slate-500">{{ item.expenses_count }}</td>
                            <td>
                                <span class="badge" :class="item.is_active ? 'badge-success' : 'badge-danger'">
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button class="icon-btn" title="Edit" aria-label="Edit" @click="openEdit(item)">
                                        <AppIcon name="pencil" size="h-4 w-4" />
                                    </button>
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

    <!-- Add / Edit modal -->
    <AppModal v-model="showForm" :title="editingId ? 'Edit Expense Category' : 'Add Expense Category'">
        <div class="space-y-4">
            <div>
                <label class="form-label">Name *</label>
                <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Electricity" />
                <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
            </div>
            <div>
                <label class="form-label">Description</label>
                <textarea v-model="form.description" rows="3" class="form-input" placeholder="Optional"></textarea>
                <p v-if="errors.description" class="form-error">{{ errors.description[0] }}</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                Active
            </label>
            <p v-if="formError" class="form-error">{{ formError }}</p>
        </div>
        <template #footer>
            <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
            <button class="btn btn-primary" :disabled="saving" @click="save">
                {{ saving ? 'Saving...' : 'Save' }}
            </button>
        </template>
    </AppModal>

    <!-- Delete confirmation -->
    <AppModal v-model="showDelete" title="Delete Expense Category" width="max-w-md">
        <p class="text-sm text-slate-600">
            Are you sure you want to delete <strong>{{ toDelete?.name }}</strong>? This action cannot be undone.
        </p>
        <p v-if="deleteError" class="form-error mt-3">{{ deleteError }}</p>
        <template #footer>
            <button class="btn btn-secondary" @click="showDelete = false">Cancel</button>
            <button class="btn btn-danger" :disabled="saving" @click="confirmDelete">
                {{ saving ? 'Deleting...' : 'Delete' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '../components/ui/pageheader.vue';
import AppIcon from '../components/ui/appicon.vue';
import AppModal from '../components/ui/appmodel.vue';
import ExpenseTabs from '../components/ui/expensetabs.vue';
import api from '../services/api';

const items = ref([]);
const meta = ref({});
const loading = ref(false);
const saving = ref(false);
const notice = ref('');

const search = ref('');
const status = ref('');
const page = ref(1);

const showForm = ref(false);
const editingId = ref(null);
const form = reactive({ name: '', description: '', is_active: true });
const errors = ref({});
const formError = ref('');

const showDelete = ref(false);
const toDelete = ref(null);
const deleteError = ref('');

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/api/expense-categories', {
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

function resetForm() {
    form.name = '';
    form.description = '';
    form.is_active = true;
    errors.value = {};
    formError.value = '';
}

function openCreate() {
    resetForm();
    editingId.value = null;
    showForm.value = true;
}

function openEdit(item) {
    resetForm();
    editingId.value = item.id;
    form.name = item.name;
    form.description = item.description ?? '';
    form.is_active = item.is_active;
    showForm.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    formError.value = '';
    try {
        if (editingId.value) {
            await api.put(`/api/expense-categories/${editingId.value}`, form);
            flash('Expense category updated successfully.');
        } else {
            await api.post('/api/expense-categories', form);
            flash('Expense category created successfully.');
        }
        showForm.value = false;
        await load();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            formError.value = 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

function askDelete(item) {
    toDelete.value = item;
    deleteError.value = '';
    showDelete.value = true;
}

async function confirmDelete() {
    saving.value = true;
    deleteError.value = '';
    try {
        await api.delete(`/api/expense-categories/${toDelete.value.id}`);
        showDelete.value = false;
        flash('Expense category deleted successfully.');
        if (items.value.length === 1 && page.value > 1) page.value--;
        await load();
    } catch (e) {
        deleteError.value = e.response?.data?.message ?? 'Something went wrong. Please try again.';
    } finally {
        saving.value = false;
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

onMounted(load);
</script>