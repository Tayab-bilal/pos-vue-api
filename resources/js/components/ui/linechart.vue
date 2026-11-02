
<template>
    <div class="relative w-full min-w-0 select-none">
        <!-- Chart: Directly inside the parent container -->
        <div
            class="relative w-full"
            :style="{ height: `${height}px` }"
        >
            <svg
                v-if="points.length"
                class="h-full w-full overflow-visible"
                viewBox="0 0 720 260"
                preserveAspectRatio="none"
                role="img"
                aria-label="Sales performance line chart"
            >
                <defs>
                    <!-- Premium Blue Line -->
                    <linearGradient
                        :id="lineGradientId"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="0"
                    >
                        <stop
                            offset="0%"
                            stop-color="#2563eb"
                        />
                        <stop
                            offset="100%"
                            stop-color="#0891b2"
                        />
                    </linearGradient>

                    <!-- Soft Area Fill -->
                    <linearGradient
                        :id="fillGradientId"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stop-color="#3b82f6"
                            stop-opacity="0.20"
                        />
                        <stop
                            offset="65%"
                            stop-color="#3b82f6"
                            stop-opacity="0.06"
                        />
                        <stop
                            offset="100%"
                            stop-color="#3b82f6"
                            stop-opacity="0"
                        />
                    </linearGradient>

                    <!-- Selected Point Glow -->
                    <filter
                        :id="glowFilterId"
                        x="-100%"
                        y="-100%"
                        width="300%"
                        height="300%"
                    >
                        <feGaussianBlur
                            stdDeviation="3"
                            result="blur"
                        />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>

                    <!-- Chart Drawing Area -->
                    <clipPath :id="clipId">
                        <rect
                            x="64"
                            y="18"
                            width="640"
                            height="194"
                            rx="2"
                        />
                    </clipPath>
                </defs>

                <!-- Horizontal Grid Lines & Y-Axis Labels -->
                <g
                    v-for="tick in ticks"
                    :key="tick.index"
                >
                    <line
                        x1="64"
                        :y1="tick.y"
                        x2="704"
                        :y2="tick.y"
                        stroke="#e9eef5"
                        stroke-width="1"
                    />

                    <text
                        x="54"
                        :y="tick.y + 4"
                        text-anchor="end"
                        font-size="10"
                        font-weight="500"
                        fill="#94a3b8"
                    >
                        {{ tick.label }}
                    </text>
                </g>

                <g :clip-path="`url(#${clipId})`">
                    <!-- Gradient Area Under the Curve -->
                    <path
                        :d="areaPath"
                        :fill="`url(#${fillGradientId})`"
                        class="chart-area"
                    />

                    <!-- Vertical Hover Guide -->
                    <line
                        v-if="hoveredPoint"
                        :x1="hoveredPoint.x"
                        y1="18"
                        :x2="hoveredPoint.x"
                        y2="212"
                        stroke="#94a3b8"
                        stroke-opacity="0.6"
                        stroke-width="1"
                        stroke-dasharray="4 5"
                    />

                    <!-- Main Revenue Line -->
                    <path
                        :d="linePath"
                        fill="none"
                        :stroke="`url(#${lineGradientId})`"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="chart-line"
                    />

                    <!-- Data Points -->
                    <g
                        v-for="(point, index) in points"
                        :key="`${point.label}-${index}`"
                        class="cursor-pointer"
                        @mouseenter="hoveredIndex = index"
                        @mouseleave="hoveredIndex = null"
                        @focus="hoveredIndex = index"
                        @blur="hoveredIndex = null"
                        tabindex="0"
                        role="button"
                        :aria-label="`${point.label}: ${format(point.value)}`"
                    >
                        <!-- Invisible Hover Hit Area -->
                        <circle
                            :cx="point.x"
                            :cy="point.y"
                            r="14"
                            fill="transparent"
                        />

                        <!-- Active Halo -->
                        <circle
                            v-if="hoveredIndex === index"
                            :cx="point.x"
                            :cy="point.y"
                            r="9"
                            fill="#60a5fa"
                            fill-opacity="0.20"
                        />

                        <!-- Point Border -->
                        <circle
                            :cx="point.x"
                            :cy="point.y"
                            :r="hoveredIndex === index ? 5 : 3.5"
                            fill="#ffffff"
                            stroke="#2563eb"
                            :stroke-width="
                                hoveredIndex === index ? 2.5 : 2
                            "
                            class="chart-point"
                        />

                        <!-- X-Axis Labels -->
                        <text
                            :x="point.x"
                            y="238"
                            text-anchor="middle"
                            font-size="11"
                            :font-weight="
                                hoveredIndex === index ? '700' : '500'
                            "
                            :fill="
                                hoveredIndex === index
                                    ? '#1d4ed8'
                                    : '#94a3b8'
                            "
                        >
                            {{ point.label }}
                        </text>
                    </g>
                </g>
            </svg>

            <!-- Floating Interactive Tooltip -->
            <div
                v-if="hoveredPoint"
                class="pointer-events-none absolute z-20 min-w-[125px] -translate-x-1/2 -translate-y-[calc(100%+12px)] rounded-xl border border-slate-700 bg-slate-900 px-3.5 py-3 shadow-xl"
                :style="tooltipStyle"
            >
                <p
                    class="mb-1 text-[10px] font-medium uppercase tracking-wider text-slate-400"
                >
                    {{ hoveredPoint.label }}
                </p>

                <p class="text-base font-bold text-white">
                    {{ format(hoveredPoint.value) }}
                </p>

                <div class="mt-2 flex items-center gap-1.5">
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-blue-400"
                    ></span>

                    <span class="text-[10px] text-slate-400">
                        Sales revenue
                    </span>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="!points.length"
                class="absolute inset-0 flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70"
            >
                <div
                    class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50"
                >
                    <i
                        class="bi bi-graph-up text-xl text-blue-600"
                    ></i>
                </div>

                <p class="text-sm font-semibold text-slate-700">
                    No sales data available
                </p>

                <p
                    class="mt-1 text-center text-xs text-slate-400"
                >
                    Your sales trend will appear here.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, useId } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },

    format: {
        type: Function,
        default: (value) =>
            `Rs ${Number(value).toLocaleString('en-PK')}`,
    },

    height: {
        type: Number,
        default: 280,
    },
});

const hoveredIndex = ref(null);

const uniqueId = useId().replace(/:/g, '');

const lineGradientId = `salesLine-${uniqueId}`;
const fillGradientId = `salesFill-${uniqueId}`;
const glowFilterId = `salesGlow-${uniqueId}`;
const clipId = `salesClip-${uniqueId}`;

// Prepare chart data
const chart = computed(() => {
    const values = props.items.map((item) =>
        Math.max(0, Number(item.value) || 0)
    );

    const highest = Math.max(0, ...values);
    const max = highest > 0 ? highest * 1.2 : 10;

    return {
        max,
        values,
    };
});

// Calculate points
const points = computed(() => {
    const left = 75;
    const right = 695;
    const top = 24;
    const bottom = 208;
    const count = props.items.length;

    return props.items.map((item, index) => {
        const value = chart.value.values[index];

        return {
            label: item.label,
            value,

            x:
                count === 1
                    ? (left + right) / 2
                    : left +
                      (index * (right - left)) / (count - 1),

            y:
                bottom -
                (value / chart.value.max) * (bottom - top),
        };
    });
});

// Currently hovered point
const hoveredPoint = computed(() => {
    if (hoveredIndex.value === null) {
        return null;
    }

    return points.value[hoveredIndex.value] ?? null;
});

// Tooltip position
const tooltipStyle = computed(() => {
    if (!hoveredPoint.value) {
        return {};
    }

    const x = hoveredPoint.value.x;
    const y = hoveredPoint.value.y;

    return {
        left: `${(x / 720) * 100}%`,
        top: `${(y / 260) * 100}%`,
    };
});

// Y-axis ticks
const ticks = computed(() => {
    return Array.from({ length: 5 }, (_, index) => {
        const value =
            (chart.value.max * (4 - index)) / 4;

        return {
            index,
            value,
            y: 24 + index * 46,
            label: formatAxisValue(value),
        };
    });
});

// Format axis labels
function formatAxisValue(value) {
    const abs = Math.abs(value);

    if (abs >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(1)}M`;
    }

    if (abs >= 10_000) {
        return `${(value / 1_000).toFixed(0)}k`;
    }

    if (abs >= 1_000) {
        return `${(value / 1_000).toFixed(1)}k`;
    }

    return Number(value.toFixed(0)).toLocaleString('en-PK');
}

// Smooth cubic Bezier curve
function createCubicSmoothPath(data) {
    if (!data.length) {
        return '';
    }

    if (data.length === 1) {
        return `M ${data[0].x} ${data[0].y}`;
    }

    let path = `M ${data[0].x} ${data[0].y}`;

    for (let i = 0; i < data.length - 1; i++) {
        const current = data[i];
        const next = data[i + 1];

        const distance = (next.x - current.x) / 3;

        path +=
            ` C ${current.x + distance} ${current.y},` +
            ` ${next.x - distance} ${next.y},` +
            ` ${next.x} ${next.y}`;
    }

    return path;
}

// Main line path
const linePath = computed(() =>
    createCubicSmoothPath(points.value)
);

// Shaded area under the line
const areaPath = computed(() => {
    if (!points.value.length) {
        return '';
    }

    const first = points.value[0];
    const last = points.value[points.value.length - 1];

    return (
        `${linePath.value}` +
        ` L ${last.x} 208` +
        ` L ${first.x} 208 Z`
    );
});
</script>

<style scoped>
.chart-line {
    transition:
        stroke 250ms ease,
        opacity 250ms ease;
}

.chart-area {
    transition: opacity 300ms ease;
}

.chart-point {
    transition:
        r 180ms ease,
        stroke-width 180ms ease;
}

@media (prefers-reduced-motion: reduce) {
    .chart-line,
    .chart-area,
    .chart-point {
        transition: none;
    }
}
</style>
