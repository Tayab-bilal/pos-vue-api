<template>
    <PageHeader :title="route.meta.title" subtitle="This module is coming soon" />
    <div class="card">
        <div class="card-body text-sm text-slate-500">	This page is under construction.</div>
    </div>
</template>

<script setup>
import { useRoute } from 'vue-router';
import PageHeader from '../components/ui/pageheader.vue';

const route = useRoute();
</script>