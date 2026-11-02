<template>
    <AppModal :model-value="modelValue" :title="title" width="max-w-md" @update:model-value="emit('update:modelValue', $event)">
        <p class="text-sm text-slate-600">{{ description }}</p>

        <div class="mt-4">
            <label class="form-label">Reason *</label>
            <textarea v-model="reason" rows="3" class="form-input" placeholder="Why is this being cancelled?"></textarea>
            <p v-if="localError || error" class="form-error">{{ localError || error }}</p>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" @click="emit('update:modelValue', false)">Keep it</button>
            <button type="button" class="btn btn-danger" :disabled="processing" @click="submit">
                {{ processing ? 'Cancelling...' : 'Confirm Cancel' }}
            </button>
        </template>
    </AppModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import AppModal from './appmodel.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: 'Cancel' },
    description: { type: String, default: '' },
    processing: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'confirm']);

const reason = ref('');
const localError = ref('');

// Start with an empty reason every time the popup opens
watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            reason.value = '';
            localError.value = '';
        }
    },
);

function submit() {
    if (reason.value.trim().length < 3) {
        localError.value = 'Please enter a reason (at least 3 characters).';
        return;
    }
    localError.value = '';
    emit('confirm', reason.value.trim());
}
</script>