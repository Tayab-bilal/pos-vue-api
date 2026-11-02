<template>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-[1.375rem] leading-7 font-semibold tracking-tight text-slate-900">{{ title }}</h1>
            <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>