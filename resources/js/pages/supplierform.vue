<template>
    <PageHeader :title="isEdit ? 'Edit Supplier' : 'Add Supplier'" :subtitle="isEdit ? 'Update supplier details' : 'Add a new vendor to your system'">
        <RouterLink to="/suppliers" class="btn btn-secondary">Back to Suppliers</RouterLink>
    </PageHeader>

    <div v-if="loading" class="card">
        <div class="card-body py-10 text-center text-slate-500">Loading...</div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
        <!-- Contact information -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Contact Information</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label">Name *</label>
                    <input v-model="form.name" type="text" class="form-input" placeholder="Contact person" />
                    <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Company Name</label>
                    <input v-model="form.company_name" type="text" class="form-input" placeholder="Optional" />
                    <p v-if="errors.company_name" class="form-error">{{ errors.company_name[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Phone</label>
                    <input v-model="form.phone" type="text" class="form-input" placeholder="e.g. 0300 1234567" />
                    <p v-if="errors.phone" class="form-error">{{ errors.phone[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-input" placeholder="name@example.com" />
                    <p v-if="errors.email" class="form-error">{{ errors.email[0] }}</p>
                </div>

                <div>
                    <label class="form-label">City</label>
                    <input v-model="form.city" type="text" class="form-input" placeholder="e.g. Lahore" />
                    <p v-if="errors.city" class="form-error">{{ errors.city[0] }}</p>
                </div>

                <div>
                    <label class="form-label">Tax Number</label>
                    <input v-model="form.tax_number" type="text" class="form-input" placeholder="NTN / tax ID (optional)" />
                    <p v-if="errors.tax_number" class="form-error">{{ errors.tax_number[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Address</label>
                    <textarea v-model="form.address" rows="2" class="form-input" placeholder="Optional"></textarea>
                    <p v-if="errors.address" class="form-error">{{ errors.address[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Account -->
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="font-semibold text-slate-800">Account &amp; Notes</h3>
            </div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label">Opening Balance</label>
                    <input v-model="form.opening_balance" type="number" min="0" step="0.01" class="form-input" />
                    <p class="mt-1 text-xs text-slate-500">Amount you already owe this supplier before using the system.</p>
                    <p v-if="errors.opening_balance" class="form-error">{{ errors.opening_balance[0] }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea v-model="form.notes" rows="3" class="form-input" placeholder="Optional"></textarea>
                    <p v-if="errors.notes" class="form-error">{{ errors.notes[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Status and actions -->
        <div class="card">
            <div class="card-body flex flex-wrap items-center justify-between gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Active
                </label>

                <div class="flex items-center gap-2">
                    <p v-if="formError" class="form-error mr-2">{{ formError }}</p>
                    <RouterLink to="/suppliers" class="btn btn-secondary">Cancel</RouterLink>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        {{ saving ? 'Saving...' : isEdit ? 'Update Supplier' : 'Save Supplier' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';
import api from '../services/api';

const route = useRoute();
const router = useRouter();

const supplierId = route.params.id ?? null;
const isEdit = computed(() => !!supplierId);

const loading = ref(isEdit.value);
const saving = ref(false);
const errors = ref({});
const formError = ref('');

const form = reactive({
    name: '',
    company_name: '',
    phone: '',
    email: '',
    city: '',
    address: '',
    tax_number: '',
    opening_balance: 0,
    notes: '',
    is_active: true,
});

async function loadSupplier() {
    const { data } = await api.get(`/api/suppliers/${supplierId}`);
    const s = data.data;
    Object.assign(form, {
        name: s.name,
        company_name: s.company_name ?? '',
        phone: s.phone ?? '',
        email: s.email ?? '',
        city: s.city ?? '',
        address: s.address ?? '',
        tax_number: s.tax_number ?? '',
        opening_balance: s.opening_balance,
        notes: s.notes ?? '',
        is_active: s.is_active,
    });
}

async function save() {
    saving.value = true;
    errors.value = {};
    formError.value = '';

    // Send empty optional text fields as null
    const payload = { ...form };
    ['company_name', 'phone', 'email', 'city', 'address', 'tax_number', 'notes'].forEach((key) => {
        payload[key] = payload[key] || null;
    });

    try {
        if (isEdit.value) {
            await api.put(`/api/suppliers/${supplierId}`, payload);
        } else {
            await api.post('/api/suppliers', payload);
        }
        router.push({ path: '/suppliers', query: { saved: isEdit.value ? 'updated' : 'created' } });
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

onMounted(async () => {
    if (!isEdit.value) return;
    try {
        await loadSupplier();
    } catch {
        // The supplier does not exist (or was deleted), so go back to the list
        router.replace('/suppliers');
        return;
    }
    loading.value = false;
});
</script>