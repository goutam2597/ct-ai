<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandTabs from './BrandTabs.vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps<{
    app: { name: string };
    brand: { id: number; name: string };
    readiness: {
        score: number;
        components: Array<{ name: string; weight: number; score: number; fixes: string[] }>;
        store: { connected: boolean; url: string | null };
    };
}>();

const scoreColor = (s: number): string =>
    s >= 80 ? 'text-green-600' : s >= 60 ? 'text-yellow-600' : s >= 40 ? 'text-orange-500' : 'text-red-500';
</script>

<template>
    <Head :title="`Commerce — ${props.brand.name}`" />

    <PanelLayout :title="`Commerce — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="commerce" />

        <div class="mx-auto max-w-4xl px-6 py-8">
            <div class="flex items-center gap-6">
                <div class="text-center">
                    <p class="text-5xl font-bold" :class="scoreColor(props.readiness.score)">{{ props.readiness.score }}</p>
                    <p class="text-xs text-neutral-500">/ 100</p>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-neutral-900 dark:text-neutral-50">AI Commerce Readiness</h1>
                    <p class="text-sm text-neutral-500">
                        How ready your products are to be found, shown and bought through AI assistants.
                    </p>
                </div>
            </div>

            <section class="mt-8 space-y-4">
                <div
                    v-for="component in props.readiness.components"
                    :key="component.name"
                    class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800"
                >
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ component.name }}</p>
                        <span class="text-sm font-bold" :class="scoreColor(component.score)">{{ component.score }}/100</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-neutral-200 dark:bg-neutral-700">
                        <div class="h-2 rounded-full bg-[var(--brand-primary)]" :style="{ width: component.score + '%' }" />
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Weight: {{ component.weight }}%</p>
                    <ul v-if="component.fixes.length" class="mt-2 space-y-1 text-xs text-amber-600 dark:text-amber-400">
                        <li v-for="fix in component.fixes" :key="fix">→ {{ fix }}</li>
                    </ul>
                </div>
            </section>

            <section class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Store connection</h2>
                <p class="mt-2 text-sm" :class="props.readiness.store.connected ? 'text-green-600' : 'text-amber-600'">
                    {{ props.readiness.store.connected
                        ? `Connected to ${props.readiness.store.url}`
                        : 'No store connected — connect Shopify, WooCommerce, BigCommerce, Etsy or another platform.'
                    }}
                </p>
            </section>
        </div>
    </PanelLayout>
</template>
