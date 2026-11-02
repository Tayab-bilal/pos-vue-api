<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-900 to-slate-900 px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex flex-col items-center text-center text-white">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500 text-2xl font-bold shadow-lg shadow-brand-500/30">P</div>
                <h1 class="mt-4 text-2xl font-semibold">POS System</h1>
                <p class="mt-1 text-sm text-brand-200/80">Inventory &amp; Sales</p>
            </div>

            <div class="rounded-2xl bg-white p-8 shadow-2xl">
                <h2 class="text-lg font-semibold text-slate-800">Sign in</h2>
                <p class="mt-1 text-sm text-slate-500">Enter your email and password to continue.</p>

                <div v-if="expired && !message" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Your session has ended. Please sign in again.
                </div>
                <div v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ message }}
                </div>

                <form class="mt-5 space-y-4" novalidate @submit.prevent="submit">
                    <div>
                        <label class="form-label" for="email">Email</label>
                        <input
                            id="email"
                            ref="emailInput"
                            v-model="form.email"
                            type="email"
                            class="form-input"
                            autocomplete="username"
                            placeholder="you@example.com"
                        />
                        <p v-if="errors.email" class="form-error">{{ errors.email[0] }}</p>
                    </div>

                    <div>
                        <label class="form-label" for="password">Password</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="form-input pr-16"
                                autocomplete="current-password"
                                placeholder="Your password"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 px-3 text-xs font-medium text-slate-500 hover:text-slate-800"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        <p v-if="errors.password" class="form-error">{{ errors.password[0] }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                        Keep me signed in on this computer
                    </label>

                    <button type="submit" class="btn btn-primary w-full" :disabled="loading">
                        {{ loading ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-brand-200/70">Authorised users only.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const form = reactive({ email: '', password: '', remember: false });
const showPassword = ref(false);
const loading = ref(false);
const errors = ref({});
const message = ref('');
const emailInput = ref(null);

const expired = route.query.expired === '1';

// Only paths inside this app are allowed, so a crafted link can never send someone to another site
const target = computed(() => {
    const redirect = route.query.redirect;
    return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/';
});

async function submit() {
    errors.value = {};
    message.value = '';
    loading.value = true;

    try {
        await auth.login(form.email, form.password, form.remember);
        router.replace(target.value);
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else if (e.response?.status === 419) {
            message.value = 'This page expired. Please refresh it and try again.';
        } else if (e.response?.status === 429) {
            message.value = 'Too many attempts. Please wait a minute and try again.';
        } else if (e.response) {
            message.value = 'Something went wrong. Please try again.';
        } else if (e.isAxiosError) {
            message.value = 'Could not reach the server. Check that it is running.';
        } else {
            message.value = e.message;
        }

        // A wrong password should never stay on screen
        form.password = '';
    } finally {
        loading.value = false;
    }
}

onMounted(() => emailInput.value?.focus());
</script>