<template>
    <!-- Colours (bars, badges) are kept when printing -->
    <div style="print-color-adjust: exact; -webkit-print-color-adjust: exact">
        <PageHeader :title="title" :subtitle="subtitle" class="print:hidden">
            <button type="button" class="btn btn-secondary" :disabled="!ready" @click="printReport">
                <AppIcon name="printer" size="h-4 w-4" />
                Print
            </button>
        </PageHeader>

        <ReportTabs class="print:hidden" />

        <ReportFilter v-if="showFilter" v-model:from="from" v-model:to="to" class="mb-6" />

        <!-- Heading that appears only on paper -->
        <div class="mb-4 hidden border-b-2 border-slate-800 pb-3 print:block">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-bold">{{ company.name }}</h1>
                    <p v-if="company.address" class="text-xs text-slate-500">{{ company.address }}</p>
                    <p class="text-xs text-slate-500">
                        <span v-if="company.phone">Phone: {{ company.phone }}</span>
                        <span v-if="company.phone && company.email"> | </span>
                        <span v-if="company.email">{{ company.email }}</span>
                    </p>
                </div>
                <div class="text-right">
                    <h2 class="text-lg font-bold tracking-wide uppercase">{{ title }}</h2>
                    <p class="text-xs"><span class="text-slate-500">Period:</span> {{ periodText || periodLabel(from, to) }}</p>
                    <p class="text-xs"><span class="text-slate-500">Printed:</span> {{ printedOn }}</p>
                    <p class="text-xs text-slate-400">Amounts in {{ company.currency }}</p>
                </div>
            </div>
        </div>

        <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 print:hidden">
            {{ error }}
        </div>

        <div v-if="!ready && loading" class="card print:hidden">
            <div class="card-body py-10 text-center text-slate-500">Loading...</div>
        </div>

        <!-- While a new period loads, the old figures stay visible but faded -->
        <div v-else-if="ready" class="space-y-6" :class="{ 'opacity-60': loading }">
            <slot />
        </div>
    </div>
</template>

<script setup>
import PageHeader from './pageheader.vue';
import AppIcon from './appicon.vue';
import ReportTabs from './reporttabs.vue';
import ReportFilter from './reportfilter.vue';
import { company } from '../../config/company';
import { periodLabel } from '../../config/reports';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    ready: { type: Boolean, default: false }, // the report data has arrived
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    showFilter: { type: Boolean, default: true },
    periodText: { type: String, default: '' }, // replaces the date range text (for reports without dates)
});

const from = defineModel('from', { default: '' });
const to = defineModel('to', { default: '' });

const printedOn = new Date().toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

function printReport() {
    // The browser uses the page title as the default PDF file name
    const original = document.title;
    document.title = `${props.title} - ${props.periodText || periodLabel(from.value, to.value)}`;
    window.print();
    document.title = original;
}
</script>