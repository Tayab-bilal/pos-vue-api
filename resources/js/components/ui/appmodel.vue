<template>
    <Teleport to="body">
        <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>

            <div class="relative w-full rounded-xl bg-white shadow-xl" :class="width">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-800">{{ title }}</h3>
                    <button
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Close"
                        @click="close"
                    >
                        &#x2715;
                    </button>
                </div>

                <div class="px-5 py-4">
                    <slot />
                </div>

                <div v-if="$slots.footer" class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    width: { type: String, default: 'max-w-lg' },
});
const emit = defineEmits(['update:modelValue']);

const close = () => emit('update:modelValue', false);
const onKey = (e) => e.key === 'Escape' && props.modelValue && close();

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>