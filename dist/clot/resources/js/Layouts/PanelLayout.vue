<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{ title: string }>();

const page = usePage<{
    app: { name: string };
    auth: {
        user: { id: number; name: string; email: string } | null;
        workspace: { id: number; name: string; role: string } | null;
    };
    panel: { brands: Array<{ id: number; name: string }>; alerts: Array<{ id: number; rule: string; title: string; created_at: string }>; isSample: boolean };
}>();

const app = computed(() => page.props.app);
const auth = computed(() => page.props.auth);
const panel = computed(() => page.props.panel ?? { brands: [], alerts: [], isSample: false });

const sidebarOpen = ref(document.documentElement.classList.contains('panel-sidebar-collapsed') === false);
const bellOpen = ref(false);

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
    document.documentElement.classList.toggle('panel-sidebar-collapsed', !sidebarOpen.value);
    try { localStorage.setItem('clot-sidebar-collapsed', sidebarOpen.value ? '0' : '1'); } catch (e) {}
};

const toggleTheme = () => {
    const isDark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('clot_theme_panel', isDark ? 'dark' : 'light'); } catch (e) {}
};

const logout = () => useForm({}).post('/logout');
</script>

<template>
    <Head :title="props.title" />

    <div class="min-h-screen bg-[var(--brand-bg)]">
        <!-- Sidebar (mono-lms pattern: fixed dark, collapsible, RTL aware) -->
        <aside
            class="sidebar-scroll fixed inset-y-0 left-0 z-40 flex w-[var(--panel-sidebar-width)] flex-col justify-between overflow-y-auto bg-[#0b0d0e] text-slate-400 transition-[width] duration-300 rtl:left-auto rtl:right-0"
        >
            <div>
                <Link href="/workspaces" class="flex h-14 items-center gap-2 border-b border-slate-800 px-4 text-white">
                    <span class="truncate text-sm font-bold" v-if="sidebarOpen">{{ app.name }}</span>
                    <span class="mx-auto font-bold text-[var(--brand-accent)]" v-else>C</span>
                </Link>

                <div v-if="sidebarOpen && auth.workspace" class="px-3 py-3">
                    <p class="truncate text-xs text-slate-500">Workspace</p>
                    <p class="truncate text-sm font-semibold text-slate-200">{{ auth.workspace.name }}</p>
                </div>

                <nav class="space-y-1 px-3 py-2 text-sm">
                    <Link
                        v-for="brand in panel.brands"
                        :key="brand.id"
                        :href="`/brands/${brand.id}`"
                        class="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-white/5 hover:text-slate-200"
                        :title="brand.name"
                    >
                        <span class="flex size-6 shrink-0 items-center justify-center rounded bg-white/10 text-xs font-bold text-white">
                            {{ brand.name.charAt(0) }}
                        </span>
                        <span v-if="sidebarOpen" class="truncate">{{ brand.name }}</span>
                    </Link>
                </nav>

                <div class="mt-2 border-t border-slate-800 px-3 py-2 text-sm" v-if="auth.workspace">
                    <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500" v-if="sidebarOpen">Manage</p>
                    <Link href="/workspaces" class="block rounded-lg px-2 py-2 hover:bg-white/5 hover:text-slate-200" :title="'Workspaces'">
                        <span v-if="sidebarOpen">Workspaces</span><span v-else class="mx-auto block w-4 text-center">⌂</span>
                    </Link>
                    <Link href="/alerts" class="block rounded-lg px-2 py-2 hover:bg-white/5 hover:text-slate-200" :title="'Alerts'">
                        <span v-if="sidebarOpen">Alerts</span><span v-else class="mx-auto block w-4 text-center">🔔</span>
                    </Link>
                    <Link href="/billing" class="block rounded-lg px-2 py-2 hover:bg-white/5 hover:text-slate-200" :title="'Billing'">
                        <span v-if="sidebarOpen">Billing</span><span v-else class="mx-auto block w-4 text-center">$</span>
                    </Link>
                </div>
            </div>

            <div class="px-3 pb-3">
                <button class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left hover:bg-white/5" @click="logout">
                    <span class="w-4 text-center">⎋</span>
                    <span v-if="sidebarOpen" class="truncate text-sm">Log out ({{ auth.user?.name }})</span>
                </button>
            </div>
        </aside>

        <!-- Content -->
        <div class="flex min-h-screen flex-col transition-[padding] duration-300 ltr:pl-[var(--panel-sidebar-width)] rtl:pr-[var(--panel-sidebar-width)]">
            <div v-if="panel.isSample" class="bg-amber-500 px-4 py-1.5 text-center text-xs font-semibold text-amber-950">
                Sample data — this workspace contains demo content, not real measurements.
            </div>
            <header class="flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center gap-3">
                    <button class="rounded-lg px-2 py-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800" @click="toggleSidebar">☰</button>
                    <span class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ title }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <button class="rounded-lg px-2 py-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800" title="Light / dark" @click="toggleTheme">◐</button>

                    <div class="relative">
                        <button class="relative rounded-lg px-2 py-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800" @click="bellOpen = !bellOpen">
                            🔔
                            <span
                                v-if="panel.alerts.length"
                                class="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white"
                            >{{ panel.alerts.length }}</span>
                        </button>
                        <div
                            v-if="bellOpen"
                            class="absolute right-0 z-50 mt-2 w-80 rounded-xl border border-neutral-200 bg-white p-3 shadow-xl dark:border-neutral-800 dark:bg-neutral-900"
                        >
                            <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Recent alerts</p>
                            <p v-if="!panel.alerts.length" class="px-2 py-3 text-sm text-neutral-500">Nothing yet — quiet is good.</p>
                            <Link
                                v-for="alert in panel.alerts"
                                :key="alert.id"
                                href="/alerts"
                                class="block rounded-lg px-2 py-2 text-sm text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="bellOpen = false"
                            >
                                {{ alert.title }}
                            </Link>
                            <Link href="/alerts" class="block px-2 py-2 text-xs text-neutral-500 underline" @click="bellOpen = false">
                                All alerts & channels
                            </Link>
                        </div>
                    </div>

                    <span class="hidden text-sm text-neutral-600 md:block dark:text-neutral-400">{{ auth.user?.name }}</span>
                </div>
            </header>

            <slot />
        </div>
    </div>
</template>
