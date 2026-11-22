<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandTabs from './BrandTabs.vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps<{
    app: { name: string };
    brand: { id: number; name: string };
    comparison: {
        you: { name: string; mention: { rate: number | null; low: number | null; high: number | null } };
        competitors: Array<{ name: string; mention: { rate: number | null; low: number | null; high: number | null } }>;
    };
}>();

const pct = (v: number | null): string => v === null ? '—' : Math.round(v * 100) + '%';
</script>

<template>
    <Head :title="`Competitors — ${props.brand.name}`" />

    <PanelLayout :title="`Competitors — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="competitors" />

        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                Competitors · {{ props.brand.name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Who gets named instead of you. Every rate carries its 95% range.
            </p>

            <div class="mt-8 rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-neutral-500 border-b border-neutral-200 dark:border-neutral-800">
                        <tr><th class="px-5 py-3">Brand</th><th class="px-5 py-3">Mention rate</th><th class="px-5 py-3">95% range</th></tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        <tr class="bg-[var(--brand-primary)]/5">
                            <td class="px-5 py-3 font-semibold text-neutral-900 dark:text-neutral-100">
                                {{ comparison.you.name }} <span class="text-xs text-neutral-400">(you)</span>
                            </td>
                            <td class="px-5 py-3 font-bold text-neutral-900 dark:text-neutral-100">{{ pct(comparison.you.mention.rate) }}</td>
                            <td class="px-5 py-3 text-neutral-500">{{ pct(comparison.you.mention.low) }} – {{ pct(comparison.you.mention.high) }}</td>
                        </tr>
                        <tr v-for="competitor in comparison.competitors" :key="competitor.name">
                            <td class="px-5 py-3 text-neutral-700 dark:text-neutral-300">{{ competitor.name }}</td>
                            <td class="px-5 py-3 text-neutral-900 dark:text-neutral-100">{{ pct(competitor.mention.rate) }}</td>
                            <td class="px-5 py-3 text-neutral-500">{{ pct(competitor.mention.low) }} – {{ pct(competitor.mention.high) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs text-neutral-500">
                Ranges overlap often, so small gaps are not a real lead. A change is real only when the ranges separate.
            </p>
        </div>
    </PanelLayout>
</template>
