<template>
    <PageHeader
        :title="isEdit ? 'Edit Expense' : 'Add Expense'"
        :subtitle="isEdit ? 'Update this expense' : 'Record money spent on running the business'"
    >
        <RouterLink :to="isEdit ? `/expenses/${expenseId}` : '/expenses'" class="btn btn-secondary">
            {{ isEdit ? 'Back to Expense' : 'Back to Expenses' }}
        </RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Expense Details</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label">Category *</label>
                    <select v-model="form.expense_category_id" class="form-input">
                        <option value="">Select category</option>
                        <option v-for="c in activeCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <p v-if="errors.expense_category_id" class="form-error">{{ errors.expense_category_id[0] }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Missing a category?
                        <RouterLink to="/expenses/categories" class="text-brand-700 underline">Manage categories</RouterLink>
                    </p>
                </div>

                <div>
                    <label class="form-label">Date *</label>
                    <input v-model="form.expense_date" type="date" class="form-input" />
                    <p v-if="errors.expense_date" class="form-error">{{ errors.expense_date[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Amount *</label>
                    <input v-model="form.amount" type="number" min="0.01" step="0.01" class="form-input" />
                    <p v-if="errors.amount" class="form-error">{{ errors.amount[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Method *</label>
                    <select v-model="form.method" class="form-input">
                        <option v-for="(label, key) in paymentMethods" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="errors.method" class="form-error">{{ errors.method[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Paid to</label>
                    <input v-model="form.paid_to" type="text" class="form-input" placeholder="e.g. LESCO, landlord, employee name" />
                    <p v-if="errors.paid_to" class="form-error">{{ errors.paid_to[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Reference</label>
                    <input v-model="form.transaction_ref" type="text" class="form-input" placeholder="Cheque number, transfer ID or bill number (optional)" />
                    <p v-if="errors.transaction_ref" class="form-error">{{ errors.transaction_ref[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Description</label>
                    <input v-model="form.description" type="text" class="form-input" placeholder="e.g. October electricity bill" />
                    <p v-if="errors.description" class="form-error">{{ errors.description[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea v-model="form.notes" rows="3" class="form-input" placeholder="Optional"></textarea>
                    <p v-if="errors.notes" class="form-error">{{ errors.notes[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-end gap-2">
                <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                <RouterLink :to="isEdit ? `/expenses/${expenseId}` : '/expenses'" class="btn btn-secondary">Cancel</RouterLink>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                    {{ saving ? 'Saving...' : isEdit ? 'Update Expense' : 'Save Expense' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import { paymentMethods } from '../config/payments';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const expenseId = route.params.id ?? null;
const isEdit = computed(() => !!expenseId);

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const formError = ref('');

const categories = ref([]);
// Only active categories are offered. When editing, the expense's own category stays
// selectable even if it was deactivated later.
const activeCategories = computed(() => categories.value.filter((c) => c.is_active || c.id === form.expense_category_id));

const today = () => new Date().toLocaleDateString('en-CA');

const form = reactive({
    expense_category_id: '',
    expense_date: today(),
    amount: '',
    method: 'cash',
    transaction_ref: '',
    paid_to: '',
    description: '',
    notes: '',
});

async function loadCategories() {
    const { data } = await api.get('/api/expense-categories', { params: { per_page: 100 } });
    categories.value = data.data.data;
}

async function loadExpense() {
    const { data } = await api.get(`/api/expenses/${expenseId}`);
    const e = data.data;

    // A cancelled expense cannot be edited
    if (e.status === 'cancelled') {
        router.replace(`/expenses/${expenseId}`);
        return false;
    }

    Object.assign(form, {
        expense_category_id: e.expense_category_id,
        expense_date: e.expense_date,
        amount: Number(e.amount),
        method: e.method,
        transaction_ref: e.transaction_ref ?? '',
        paid_to: e.paid_to ?? '',
        description: e.description ?? '',
        notes: e.notes ?? '',
    });

    return true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    formError.value = '';

    // Send empty optional fields as null
    const payload = { ...form };
    ['transaction_ref', 'paid_to', 'description', 'notes'].forEach((key) => {
        payload[key] = payload[key] || null;
    });

    try {
        if (isEdit.value) {
            await api.put(`/api/expenses/${expenseId}`, payload);
        } else {
            await api.post('/api/expenses', payload);
        }
        router.push({ path: '/expenses', query: { saved: isEdit.value ? 'updated' : 'created' } });
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            formError.value = e.response?.data?.message ?? 'Something went wrong. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    try {
        await loadCategories();
        if (isEdit.value) {
            const ok = await loadExpense();
            if (!ok) return;
        }
    } catch {
        if (isEdit.value) {
            router.replace('/expenses');
            return;
        }
        formError.value = 'Could not load categories. Please refresh the page.';
    }
    loading.value = false;
});
</script>