<template>
    <div class="mb-4 flex items-end gap-1 overflow-x-auto border-b border-slate-200">
        <RouterLink
            v-for="tab in reportTabs"
            :key="tab.to"
            :to="tab.to"
            class="-mb-px border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition"
            :class="isActive(tab) ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
        >
            {{ tab.label }}
        </RouterLink>
        <RouterLink to="/ledgers" class="ml-auto shrink-0 pb-2 pl-4 text-xs whitespace-nowrap text-brand-700 hover:underline">
            Receivable and payable: see Ledgers
        </RouterLink>
    </div>
</template>

<script setup>
import { useRoute } from 'vue-router';
import { reportTabs } from '../../config/reports';

const route = useRoute();

const isActive = (tab) => (tab.exact ? route.path === tab.to : route.path.startsWith(tab.to));
</script>