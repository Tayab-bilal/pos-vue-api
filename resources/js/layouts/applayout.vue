<template>
    <div class="min-h-screen">
        <!-- Mobile overlay -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/60 backdrop-blur-sm lg:hidden print:hidden"
                @click="sidebarOpen = false"
            ></div>
        </Transition>

        <!-- Sidebar: slides out of view when hidden. On phones it opens over the page. -->
        <aside
            class="print:hidden fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-gradient-to-b from-brand-900 to-slate-900 text-white transition-transform duration-300 ease-in-out"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                ui.sidebarHidden ? 'lg:-translate-x-full' : 'lg:translate-x-0',
                { 'shadow-xl': sidebarVisible },
            ]"
            :aria-hidden="!sidebarVisible"
            :inert="sidebarVisible ? null : ''"
        >
            <!-- Brand -->
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-lg font-bold shadow-lg shadow-brand-500/30">
                    P
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-base font-semibold">POS System</p>
                    <p class="truncate text-[0.6875rem] text-brand-200/70">Inventory &amp; Sales</p>
                </div>
                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white"
                    title="Hide menu (Ctrl+B)"
                    aria-label="Hide menu"
                    @click="toggleSidebar"
                >
                    <AppIcon name="chevronsleft" size="h-4 w-4" />
                </button>
            </div>

            <!-- Menu -->
            <nav class="sidebar-scroll flex-1 pl-3 pr-2 py-4">
                <div v-for="group in menu" :key="group.heading" class="mb-5">
                    <p class="mb-2 px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-brand-200/60">
                        {{ group.heading }}
                    </p>

                    <div class="space-y-0.5">
                        <RouterLink
                            v-for="item in group.items"
                            :key="item.path"
                            :to="item.path"
                            class="group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
                            :class="
                                isActive(item.path)
                                    ? 'bg-white/10 font-medium text-white'
                                    : 'text-slate-300 hover:bg-white/5 hover:text-white'
                            "
                            @click="sidebarOpen = false"
                        >
                            <span
                                v-if="isActive(item.path)"
                                class="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-brand-500"
                            ></span>
                            <AppIcon
                                :name="item.icon"
                                :class="isActive(item.path) ? 'text-brand-500' : 'text-slate-400 group-hover:text-brand-200'"
                            />
                            {{ item.label }}
                        </RouterLink>
                    </div>
                </div>
            </nav>

            <!-- Signed-in user -->
            <div class="shrink-0 border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-lg bg-white/5 px-3 py-2.5">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold">
                        {{ auth.initial }}
                    </div>
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="truncate text-sm font-medium">{{ auth.user?.name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ auth.user?.email }}</p>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white"
                        title="Sign out"
                        aria-label="Sign out"
                        @click="signOut"
                    >
                        <AppIcon name="logout" size="h-4 w-4" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main area: it takes the sidebar's space back when the sidebar is hidden -->
        <div
            class="transition-[padding] duration-300 ease-in-out print:pl-0"
            :class="ui.sidebarHidden ? 'lg:pl-0' : 'lg:pl-64'"
        >
            <!-- Top bar -->
            <header
                class="print:hidden sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 hover:text-slate-900"
                        :title="toggleTitle"
                        :aria-label="toggleTitle"
                        :aria-expanded="sidebarVisible"
                        @click="toggleSidebar"
                    >
                        <AppIcon name="menu" />
                    </button>

                    <!-- With the sidebar hidden on a large screen, a small logo keeps the brand in view -->
                    <div
                        v-if="isDesktop && ui.sidebarHidden"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-base font-bold text-white shadow-sm"
                    >
                        P
                    </div>

                    <div class="min-w-0 leading-tight">
                        <h2 class="truncate text-base font-semibold text-slate-900">{{ route.meta.title }}</h2>
                        <p class="hidden text-xs text-slate-500 sm:block">{{ today }}</p>
                    </div>
                </div>

                <!-- Search. On phones the search button on the right opens it. -->
                <GlobalSearch shortcut class="hidden max-w-md flex-1 md:block" />

                <!-- Right side -->
                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 md:hidden"
                        aria-label="Search"
                        @click="mobileSearch = true"
                    >
                        <AppIcon name="search" />
                    </button>

                    <RouterLink to="/sales/create" class="btn btn-primary hidden sm:inline-flex">
                        <AppIcon name="plus" size="h-4 w-4" />
                        New Sale
                    </RouterLink>

                    <button
                        class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                        aria-label="Notifications"
                    >
                        <AppIcon name="bell" />
                    </button>

                    <div class="hidden h-8 w-px bg-slate-200 sm:block"></div>

                    <!-- User menu -->
                    <div ref="menuRef" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2.5 rounded-lg px-1.5 py-1 hover:bg-slate-100"
                            aria-haspopup="true"
                            :aria-expanded="userMenuOpen"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                                {{ auth.initial }}
                            </div>
                            <div class="hidden text-left leading-tight sm:block">
                                <p class="text-sm font-medium text-slate-800">{{ auth.user?.name }}</p>
                                <p class="text-xs text-slate-500">Administrator</p>
                            </div>
                        </button>

                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 top-full z-50 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
                        >
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-medium text-slate-800">{{ auth.user?.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ auth.user?.email }}</p>
                            </div>

                            <div class="my-1 border-t border-slate-100"></div>

                            <!-- Text size -->
                            <div class="px-3 py-2">
                                <p class="mb-1.5 text-xs font-medium text-slate-500">Text size</p>
                                <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1">
                                    <button
                                        v-for="(label, key) in textSizes"
                                        :key="key"
                                        type="button"
                                        class="rounded-md py-1 text-xs font-medium transition"
                                        :class="
                                            ui.textSize === key
                                                ? 'bg-white text-slate-900 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-800'
                                        "
                                        @click="ui.setTextSize(key)"
                                    >
                                        {{ label }}
                                    </button>
                                </div>
                            </div>

                            <div class="my-1 border-t border-slate-100"></div>

                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                                @click="signOut"
                            >
                                <AppIcon name="logout" size="h-4 w-4" />
                                Sign out
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 print:p-0">
                <!-- On very wide screens the content stops growing, so it stays easy to read -->
                <div class="mx-auto w-full max-w-[100rem] print:max-w-none">
                    <RouterView :key="route.path" />
                </div>
            </main>
        </div>

        <!-- Search bar for phones -->
        <template v-if="mobileSearch">
            <div class="fixed inset-0 z-40 bg-slate-900/40 md:hidden print:hidden" @click="mobileSearch = false"></div>
            <div class="fixed inset-x-0 top-0 z-50 flex items-center gap-2 bg-white p-3 shadow-lg md:hidden print:hidden">
                <GlobalSearch autofocus class="min-w-0 flex-1" placeholder="Search..." @done="mobileSearch = false" />
                <button type="button" class="btn btn-secondary" @click="mobileSearch = false">Cancel</button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '../components/ui/appicon.vue';
import GlobalSearch from '../components/ui/globalsearch.vue';
import { menu } from '../config/menu';
import { useAuthStore } from '../stores/auth';
import { textSizes, useUiStore } from '../stores/ui';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ui = useUiStore();

const sidebarOpen = ref(false); // phones: the sidebar is open over the page
const userMenuOpen = ref(false);
const mobileSearch = ref(false);
const menuRef = ref(null);

// Large screens (1024px and up) keep the sidebar beside the page, phones open it over the page
const desktopQuery = window.matchMedia('(min-width: 1024px)');
const isDesktop = ref(desktopQuery.matches);

const sidebarVisible = computed(() => (isDesktop.value ? !ui.sidebarHidden : sidebarOpen.value));
const toggleTitle = computed(() => (sidebarVisible.value ? 'Hide menu (Ctrl+B)' : 'Show menu (Ctrl+B)'));

const today = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const isActive = (path) => (path === '/' ? route.path === '/' : route.path.startsWith(path));

function toggleSidebar() {
    if (isDesktop.value) {
        ui.toggleSidebar();
    } else {
        sidebarOpen.value = !sidebarOpen.value;
    }
}

async function signOut() {
    userMenuOpen.value = false;
    sidebarOpen.value = false;
    await auth.logout();
    router.replace('/login');
}

function onBreakpointChange(event) {
    isDesktop.value = event.matches;
    if (event.matches) sidebarOpen.value = false;
}

// Close the user menu when clicking anywhere else
function onDocumentClick(event) {
    if (userMenuOpen.value && menuRef.value && !menuRef.value.contains(event.target)) {
        userMenuOpen.value = false;
    }
}

function onKey(event) {
    if (event.key === 'Escape') {
        userMenuOpen.value = false;
        if (!isDesktop.value) sidebarOpen.value = false;
    }

    // Ctrl+B (Cmd+B on a Mac) shows or hides the sidebar
    if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 'b') {
        event.preventDefault();
        toggleSidebar();
    }
}

onMounted(() => {
    desktopQuery.addEventListener('change', onBreakpointChange);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKey);
});

onUnmounted(() => {
    desktopQuery.removeEventListener('change', onBreakpointChange);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKey);
});
</script>