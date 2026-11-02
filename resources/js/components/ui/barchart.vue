<template>
    <div v-if="items.length">
        <div class="flex gap-1" :style="{ height: `${height}px` }">
            <div
                v-for="(item, index) in items"
                :key="index"
                class="flex h-full min-w-0 flex-1 flex-col"
                :title="`${item.label}: ${fmt(item.value)}`"
            >
                <div class="flex items-end justify-center" :style="{ height: `${topShare}%` }">
                    <div
                        v-if="item.value > 0"
                        class="w-full max-w-10 rounded-t bg-brand-500"
                        :style="{ height: `${(item.value / posMax) * 100}%` }"
                    ></div>
                </div>
                <div class="flex items-start justify-center" :style="{ height: `${100 - topShare}%` }">
                    <div
                        v-if="item.value < 0"
                        class="w-full max-w-10 rounded-b bg-red-400"
                        :style="{ height: `${(Math.abs(item.value) / negMax) * 100}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Labels: with many bars only some are written, so they do not overlap -->
        <div class="mt-1 flex gap-1 border-t border-slate-200 pt-1">
            <div v-for="(item, index) in items" :key="index" class="relative h-4 min-w-0 flex-1">
                <span v-if="index % step === 0" class="absolute left-1/2 -translate-x-1/2 text-[10px] whitespace-nowrap text-slate-500">
                    {{ item.label }}
                </span>
            </div>
        </div>

        <p class="mt-1 text-right text-[11px] text-slate-400">
            Highest: {{ fmt(posMax) }}<template v-if="negMax > 0"> | Lowest: - {{ fmt(negMax) }}</template>
        </p>
    </div>
    <p v-else class="py-6 text-center text-sm text-slate-500">No data for this period.</p>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] }, // [{ label, value }]
    height: { type: Number, default: 160 },
    format: { type: Function, default: null },
});

const fmt = (value) =>
    props.format ? props.format(value) : Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });

// The chart has a zero line: positive bars go up from it, negative bars (losses) go down
const posMax = computed(() => Math.max(0, ...props.items.map((i) => Number(i.value))));
const negMax = computed(() => Math.max(0, ...props.items.map((i) => -Number(i.value))));
const topShare = computed(() => {
    const range = posMax.value + negMax.value;
    return range > 0 ? (posMax.value / range) * 100 : 100;
});
const step = computed(() => Math.max(1, Math.ceil(props.items.length / 10)));
</script>