<template>
    <div ref="root" class="relative">
        <div class="relative">
            <AppIcon name="search" size="h-4 w-4" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
                ref="input"
                v-model="query"
                type="text"
                role="combobox"
                autocomplete="off"
                spellcheck="false"
                aria-autocomplete="list"
                :placeholder="placeholder"
                :aria-expanded="open"
                :aria-controls="`${uid}-list`"
                :aria-activedescendant="active >= 0 ? `${uid}-item-${active}` : undefined"
                class="w-full rounded-full border border-slate-200 bg-slate-50 py-2 pl-9 pr-16 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                @focus="open = true"
                @keydown="onKeydown"
            />
            <div class="absolute inset-y-0 right-2 flex items-center">
                <button
                    v-if="query"
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded-full text-slate-400 hover:bg-slate-200 hover:text-slate-700"
                    aria-label="Clear search"
                    @mousedown.prevent
                    @click="clear"
                >
                    &#x2715;
                </button>
                <kbd
                    v-else-if="shortcut"
                    class="hidden rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[0.625rem] font-medium text-slate-400 lg:inline"
                >
                    Ctrl K
                </kbd>
            </div>
        </div>

        <!-- Results -->
        <div
            v-if="open"
            :id="`${uid}-list`"
            role="listbox"
            class="absolute left-0 right-0 top-full z-50 mt-2 max-h-[70vh] min-w-[18rem] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"
        >
            <div v-if="!trimmed" class="px-4 py-4">
                <p class="text-sm font-medium text-slate-700">Search everything</p>
                <p class="mt-1 text-xs text-slate-500">
                    Type a product name, scan a barcode, or enter an invoice number, a customer name or a phone number.
                </p>
            </div>
            <div v-else-if="tooShort" class="px-4 py-4 text-sm text-slate-500">Type at least 2 characters.</div>
            <div v-else-if="error" class="px-4 py-4 text-sm text-red-600">Could not search. Please try again.</div>
            <div v-else-if="loading && !groups.length" class="px-4 py-4 text-sm text-slate-500">Searching...</div>
            <div v-else-if="!groups.length" class="px-4 py-4 text-sm text-slate-500">No results for "{{ trimmed }}".</div>

            <template v-else>
                <div v-for="group in view" :key="group.key">
                    <p class="sticky top-0 z-10 flex items-center gap-2 bg-slate-50 px-4 py-1.5 text-[0.6875rem] font-semibold uppercase tracking-wider text-slate-500">
                        <AppIcon :name="groupIcons[group.key] ?? 'search'" size="h-3.5 w-3.5" />
                        {{ group.label }}
                    </p>
                    <ul>
                        <li
                            v-for="item in group.items"
                            :id="`${uid}-item-${item.idx}`"
                            :key="`${item.type}-${item.id}`"
                            role="option"
                            :aria-selected="item.idx === active"
                            class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2"
                            :class="item.idx === active ? 'bg-brand-50' : ''"
                            @mouseenter="active = item.idx"
                            @mousedown.prevent
                            @click="go(item)"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">
                                    <template v-for="(part, n) in parts(item.title)" :key="n">
                                        <mark v-if="part.hit" class="rounded bg-brand-100 px-0.5 text-brand-900">{{ part.text }}</mark>
                                        <template v-else>{{ part.text }}</template>
                                    </template>
                                </p>
                                <p v-if="item.subtitle" class="truncate text-xs text-slate-500">{{ item.subtitle }}</p>
                            </div>
                            <span v-if="item.badge" class="badge shrink-0" :class="toneClasses[item.tone] ?? toneClasses.neutral">
                                {{ item.badge }}
                            </span>
                        </li>
                    </ul>
                </div>
            </template>

            <div class="hidden gap-4 border-t border-slate-100 px-4 py-2 text-[0.6875rem] text-slate-400 sm:flex">
                <span>↑ ↓ move</span>
                <span>Enter open</span>
                <span>Esc close</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from './appicon.vue';
import { groupIcons, searchLink, toneClasses } from '../../config/search';
import api from '../../services/api';

const props = defineProps({
    shortcut: { type: Boolean, default: false }, // "/" and Ctrl+K focus this box
    autofocus: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Search products, customers, invoices...' },
});
const emit = defineEmits(['done']);

const router = useRouter();
const route = useRoute();
const uid = useId();

const root = ref(null);
const input = ref(null);

const query = ref('');
const open = ref(false);
const loading = ref(false);
const error = ref(false);
const groups = ref([]);
const active = ref(-1);

let timer = null;
let controller = null;
let pending = null;
let seq = 0; // each search gets a number, so a slow old answer can never replace a newer one

const trimmed = computed(() => query.value.trim());
const tooShort = computed(() => trimmed.value.length < 2);

// Every result gets a running number, for the keyboard
const view = computed(() => {
    let i = 0;
    return groups.value.map((group) => ({ ...group, items: group.items.map((item) => ({ ...item, idx: i++ })) }));
});
const flat = computed(() => view.value.flatMap((group) => group.items));

// The matching part of a title is highlighted
function parts(text) {
    const value = String(text ?? '');
    const needle = trimmed.value.toLowerCase();
    const at = needle ? value.toLowerCase().indexOf(needle) : -1;

    if (at === -1) return [{ text: value, hit: false }];

    return [
        { text: value.slice(0, at), hit: false },
        { text: value.slice(at, at + needle.length), hit: true },
        { text: value.slice(at + needle.length), hit: false },
    ].filter((part) => part.text);
}

async function run() {
    controller?.abort();
    controller = new AbortController();

    const mine = ++seq;
    loading.value = true;
    error.value = false;

    try {
        const { data } = await api.get('/api/search', { params: { q: trimmed.value }, signal: controller.signal });
        if (mine !== seq) return;

        groups.value = data.data;
        active.value = flat.value.length ? 0 : -1;
    } catch (e) {
        // A search that was replaced by a newer one is not an error
        if (mine !== seq || e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError') return;

        groups.value = [];
        error.value = true;
    } finally {
        if (mine === seq) loading.value = false;
    }
}

watch(query, () => {
    open.value = true;
    active.value = -1;
    clearTimeout(timer);
    timer = null;

    if (tooShort.value) {
        seq++;
        controller?.abort();
        groups.value = [];
        loading.value = false;
        error.value = false;
        return;
    }

    loading.value = true;
    error.value = false;
    // Wait for a short pause in typing, so a request is not sent for every letter
    timer = setTimeout(() => {
        timer = null;
        pending = run();
    }, 250);
});

async function openSelected() {
    if (tooShort.value) return;

    // A barcode scanner presses Enter right after the last digit, so do not wait for the pause
    if (timer) {
        clearTimeout(timer);
        timer = null;
        pending = run();
    }
    if (pending) await pending;

    const target = flat.value[active.value >= 0 ? active.value : 0];
    if (target) go(target);
}

function go(item) {
    const to = searchLink(item.type, item.id);
    if (!to) return;

    router.push(to);
    close(true);
}

function close(clearText = false) {
    open.value = false;
    if (clearText) query.value = '';
    input.value?.blur();
    emit('done');
}

function clear() {
    query.value = '';
    input.value?.focus();
}

function onKeydown(event) {
    const count = flat.value.length;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;
        if (count) active.value = (active.value + 1) % count;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (count) active.value = (active.value - 1 + count) % count;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        openSelected();
    } else if (event.key === 'Escape') {
        // First Escape hides the list, the second one leaves the box
        if (open.value && trimmed.value) open.value = false;
        else close();
    }
}

// Keep the highlighted result in view while moving with the keyboard
watch(active, async (index) => {
    if (index < 0) return;
    await nextTick();
    document.getElementById(`${uid}-item-${index}`)?.scrollIntoView({ block: 'nearest' });
});

// Moving to another page closes the list
watch(
    () => route.fullPath,
    () => (open.value = false),
);

function onDocumentMousedown(event) {
    if (open.value && root.value && !root.value.contains(event.target)) open.value = false;
}

// "/" or Ctrl+K from anywhere on the page jumps into the search box
function onGlobalKey(event) {
    const tag = event.target?.tagName ?? '';
    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || event.target?.isContentEditable;
    const ctrlK = (event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 'k';
    const slash = event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !typing;

    if (ctrlK || slash) {
        event.preventDefault();
        input.value?.focus();
        input.value?.select();
    }
}

onMounted(() => {
    document.addEventListener('mousedown', onDocumentMousedown);
    if (props.shortcut) document.addEventListener('keydown', onGlobalKey);
    if (props.autofocus) input.value?.focus();
});

onBeforeUnmount(() => {
    clearTimeout(timer);
    controller?.abort();
    document.removeEventListener('mousedown', onDocumentMousedown);
    document.removeEventListener('keydown', onGlobalKey);
});
</script>