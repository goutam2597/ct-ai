<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandTabs from './BrandTabs.vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps<{
    app: { name: string };
    brand: { id: number; name: string };
    tone: { positive: number; neutral: number; negative: number };
    yourMentions: { positive: number; negative: number };
    toneByEngine: Array<{ engine: string; positive: number; mentions: number }>;
    themes: Array<{ tone: string; mentions: number }>;
    engines: string[];
}>();

const toneColor = (tone: string): string =>
    ({ positive: 'text-green-600', neutral: 'text-neutral-500', negative: 'text-red-500' })[tone] ?? 'text-neutral-500';
</script>

<template>
    <Head :title="`Perception — ${props.brand.name}`" />

    <PanelLayout :title="`Perception — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="perception" />

        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                Perception · {{ props.brand.name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                How AI engines speak of you when they name you. Named is not the same as recommended.
            </p>

            <section class="mt-8 grid grid-cols-3 gap-4">
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Positive</p>
                    <p class="mt-2 text-3xl font-bold text-green-600">{{ props.tone.positive }}%</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Neutral</p>
                    <p class="mt-2 text-3xl font-bold text-neutral-500">{{ props.tone.neutral }}%</p>
                </div>
                <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Negative</p>
                    <p class="mt-2 text-3xl font-bold text-red-500">{{ props.tone.negative }}%</p>
                </div>
            </section>

            <section class="mt-8 rounded-xl border border-neutral-200 dark:border-neutral-800">
                <h2 class="border-b border-neutral-200 px-5 py-3 text-sm font-semibold text-neutral-700 dark:border-neutral-800 dark:text-neutral-300">
                    Tone by engine — positive share of mentions
                </h2>
                <ul class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    <li v-for="engine in toneByEngine" :key="engine.engine" class="flex items-center justify-between px-5 py-3 text-sm">
                        <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ engine.engine }}</span>
                        <div class="flex items-center gap-3">
                            <div class="h-2 w-24 rounded-full bg-neutral-200 dark:bg-neutral-700">
                                <div class="h-2 rounded-full bg-green-500" :style="{ width: engine.positive + '%' }" />
                            </div>
                            <span class="w-10 text-right font-semibold text-neutral-700 dark:text-neutral-300">{{ engine.positive }}%</span>
                            <span class="w-16 text-right text-xs text-neutral-400">{{ engine.mentions }} mentions</span>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Your mentions</h2>
                <div class="mt-3 flex items-center gap-6 text-sm">
                    <span class="text-green-600">✓ {{ yourMentions.positive }} positive</span>
                    <span class="text-red-500">✗ {{ yourMentions.negative }} negative</span>
                </div>
            </section>
        </div>
    </PanelLayout>
</template>
