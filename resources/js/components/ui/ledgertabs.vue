<template>
    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
        <RouterLink
            v-for="tab in tabs"
            :key="tab.to"
            :to="tab.to"
            class="-mb-px border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition"
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
    { label: 'Customers (receivable)', to: '/ledgers', match: (p) => p === '/ledgers' || p.startsWith('/ledgers/customers') },
    { label: 'Suppliers (payable)', to: '/ledgers/suppliers', match: (p) => p.startsWith('/ledgers/suppliers') },
];

const isActive = (tab) => tab.match(route.path);
</script>