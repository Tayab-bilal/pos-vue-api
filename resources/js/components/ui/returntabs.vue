<template>
    <div class="mb-4 flex gap-1 border-b border-slate-200">
        <RouterLink
            v-for="tab in tabs"
            :key="tab.to"
            :to="tab.to"
            class="-mb-px border-b-2 px-4 py-2 text-sm font-medium transition"
            :class="isActive(tab) ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
        >
            {{ tab.label }}
        </RouterLink>
    </div>
</template>

<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();

const tabs = [
    { label: 'Sales Returns', to: '/returns', exact: true },
    { label: 'Purchase Returns', to: '/returns/purchases', exact: false },
];

const isActive = (tab) => (tab.exact ? route.path === tab.to : route.path.startsWith(tab.to));
</script>