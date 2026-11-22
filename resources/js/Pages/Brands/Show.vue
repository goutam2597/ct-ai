<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    brand: { id: number; name: string; engines: string[]; runsPerPrompt: number; nextSweepAt: string | null };
    metrics: {
        mentionRate: { point: number | null; low: number | null; high: number | null };
        citationRate: { point: number | null; low: number | null; high: number | null };
        shareOfVoice: number | null;
        answersCollected: number;
        answersFailed: number;
        lowConfidence: boolean;
    };
    engines: Array<{ engine: string; rate: number | null; low: number | null; high: number | null; answers: number }>;
    daily: Array<{ day: string; answers: number; rate: number | null }>;
    allowance: { used: number; total: number };
    cost: { monthUsd: number; capUsd: number };
}>();

const pct = (value: number | null): string =>
    value === null ? '–' : `${Math.round(value * 100)}%`;

const sweep = useForm({});
const runSweep = () => sweep.post(`/brands/${props.brand.id}/sweep`, { preserveScroll: true });

const maxDaily = Math.max(...props.daily.map((d) => d.answers), 1);
</script>

<template>
    <Head :title="brand.name" />

    <main class="mx-auto max-w-6xl px-6 py-10">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">{{ brand.name }}</h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ brand.engines.length }} engines · {{ brand.runsPerPrompt }} runs per prompt ·
                    next sweep {{ brand.nextSweepAt ? new Date(brand.nextSweepAt).toLocaleString() : 'not scheduled' }}
                </p>
            </div>
            <button
                class="rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                :disabled="sweep.processing"
                @click="runSweep"
            >
                Run sweep now
            </button>
        </header>

        <p v-if="metrics.lowConfidence" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
            Low confidence: fewer than 30 answers in this period. Shown, but not used to call a trend.
        </p>

        <section class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Mention rate</p>
                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-50">{{ pct(metrics.mentionRate.point) }}</p>
                <p class="mt-1 text-xs text-neutral-500">
                    95% range {{ pct(metrics.mentionRate.low) }} – {{ pct(metrics.mentionRate.high) }}
                </p>
            </div>
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Share of voice</p>
                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-50">{{ metrics.shareOfVoice ?? '–' }}%</p>
                <p class="mt-1 text-xs text-neutral-500">of all tracked-brand mentions</p>
            </div>
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Citation rate</p>
                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-50">{{ pct(metrics.citationRate.point) }}</p>
                <p class="mt-1 text-xs text-neutral-500">
                    95% range {{ pct(metrics.citationRate.low) }} – {{ pct(metrics.citationRate.high) }}
                </p>
            </div>
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Answers · 30 days</p>
                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-neutral-50">{{ metrics.answersCollected }}</p>
                <p class="mt-1 text-xs text-neutral-500">{{ metrics.answersFailed }} failed · excluded from rates</p>
            </div>
        </section>

        <section class="mt-8 rounded-xl border border-neutral-200 dark:border-neutral-800">
            <h2 class="border-b border-neutral-200 px-5 py-3 text-sm font-semibold text-neutral-700 dark:border-neutral-800 dark:text-neutral-300">
                By engine — the band is the 95% range
            </h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-neutral-500">
                        <th class="px-5 py-2">Engine</th>
                        <th class="px-5 py-2">Rate</th>
                        <th class="px-5 py-2">Range</th>
                        <th class="px-5 py-2 text-right">Answers</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    <tr v-for="row in engines" :key="row.engine">
                        <td class="px-5 py-2.5 font-medium text-neutral-800 dark:text-neutral-200">{{ row.engine }}</td>
                        <td class="px-5 py-2.5 text-neutral-900 dark:text-neutral-100">{{ pct(row.rate) }}</td>
                        <td class="px-5 py-2.5 text-neutral-500">{{ pct(row.low) }} – {{ pct(row.high) }}</td>
                        <td class="px-5 py-2.5 text-right text-neutral-500">{{ row.answers }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800 lg:col-span-2">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Mention rate by day</h2>
                <div class="mt-4 flex h-32 items-end gap-1">
                    <div
                        v-for="point in daily"
                        :key="point.day"
                        class="flex-1 rounded-t bg-[var(--brand-accent)]"
                        :style="{ height: (point.rate ?? 0) + '%', minHeight: point.rate === null ? '0' : '4px' }"
                        :title="`${point.day}: ${point.rate ?? '–'}% of ${point.answers}`"
                    />
                </div>
                <p class="mt-2 text-xs text-neutral-500">Hover a bar for the day's sample size.</p>
            </div>
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">This month</h2>
                <p class="mt-4 text-sm text-neutral-600 dark:text-neutral-400">
                    Answers used
                    <span class="float-right font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ allowance.used }} / {{ allowance.total }}
                    </span>
                </p>
                <div class="mt-2 h-2 rounded-full bg-neutral-200 dark:bg-neutral-800">
                    <div
                        class="h-2 rounded-full bg-[var(--brand-primary)]"
                        :style="{ width: Math.min(100, allowance.used / Math.max(allowance.total, 1) * 100) + '%' }"
                    />
                </div>
                <p class="mt-4 text-sm text-neutral-600 dark:text-neutral-400">
                    AI spend
                    <span class="float-right font-semibold text-neutral-900 dark:text-neutral-100">
                        US${{ cost.monthUsd.toFixed(2) }} / cap US${{ cost.capUsd.toFixed(2) }}
                    </span>
                </p>
                <p class="mt-1 text-xs text-neutral-500">Collection pauses when the cap or allowance is reached.</p>
            </div>
        </section>
    </main>
</template>
