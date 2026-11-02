<template>
    <div class="card print:hidden">
        <div class="card-body flex flex-wrap items-end gap-4">
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="preset in presets"
                    :key="preset.key"
                    type="button"
                    class="btn px-3! py-1!"
                    :class="isActive(preset) ? 'btn-primary' : 'btn-secondary'"
                    @click="apply(preset)"
                >
                    {{ preset.label }}
                </button>
            </div>
            <div>
                <label class="form-label">From</label>
                <input v-model="from" type="date" class="form-input w-44" />
            </div>
            <div>
                <label class="form-label">To</label>
                <input v-model="to" type="date" class="form-input w-44" />
            </div>
            <p v-if="invalid" class="form-error">The end date cannot be before the start date.</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const from = defineModel('from', { default: '' });
const to = defineModel('to', { default: '' });

const iso = (date) => date.toLocaleDateString('en-CA'); // YYYY-MM-DD in local time

const presets = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();

    return [
        { key: 'today', label: 'Today', from: iso(now), to: iso(now) },
        { key: 'month', label: 'This month', from: iso(new Date(year, month, 1)), to: iso(new Date(year, month + 1, 0)) },
        { key: 'last', label: 'Last month', from: iso(new Date(year, month - 1, 1)), to: iso(new Date(year, month, 0)) },
        { key: 'year', label: 'This year', from: iso(new Date(year, 0, 1)), to: iso(new Date(year, 11, 31)) },
        { key: 'all', label: 'All time', from: '', to: '' },
    ];
});

const isActive = (preset) => from.value === preset.from && to.value === preset.to;

const invalid = computed(() => !!from.value && !!to.value && to.value < from.value);

function apply(preset) {
    from.value = preset.from;
    to.value = preset.to;
}
</script>