<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import BrandTabs from './BrandTabs.vue';

const page = usePage<{
    app: { name: string };
    brand: { id: number; name: string };
    answers: Array<{
        id: number;
        status: string;
        engine: string;
        method: string;
        model: string | null;
        named: boolean;
        cited: boolean;
        text: string | null;
        failureReason: string | null;
        costUsd: string | null;
        createdAt: string | null;
        prompt: string;
    }>;
    filters: { engine: string; status: string; named: string };
}>();

const props = page.props;

const setFilter = (key: string, value: string) => {
    const params = new URLSearchParams(window.location.search);
    if (value === '') params.delete(key); else params.set(key, value);
    router.get(`/brands/${props.brand.id}/answers${params.toString() ? `?${params.toString()}` : ''}`, {}, { preserveState: true });
};

const fmtCost = (cost: string | null): string =>
    cost === null ? '–' : `US$${parseFloat(cost).toFixed(4)}`;
</script>

<template>
    <Head :title="`${brand.name} — answers`" />

    <PanelLayout :title="`${brand.name} — answers`">
        <BrandTabs :brand="props.brand" active="answers" />
        <div class="mx-auto max-w-5xl px-6 py-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">Answers · {{ brand.name }}</h1>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Full text, method and cost. Failed answers keep their reason and are excluded from rates.
                    </p>
                </div>
                <Link :href="`/brands/${brand.id}`" class="text-sm text-neutral-500 underline">← Overview</Link>
            </div>

            <div class="mt-6 flex flex-wrap gap-2 text-sm">
                <label class="flex items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-neutral-500">Engine</span>
                    <select :value="props.filters.engine" class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900"
                        @change="setFilter('engine', ($event.target as HTMLSelectElement).value)">
                        <option value="">All</option>
                        <option v-for="e in ['chatgpt','gemini','google-ai-overviews','google-ai-mode','claude','perplexity','grok','deepseek','le-chat']" :key="e" :value="e">{{ e }}</option>
                    </select>
                </label>
                <label class="flex items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-neutral-500">Status</span>
                    <select :value="props.filters.status" class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900"
                        @change="setFilter('status', ($event.target as HTMLSelectElement).value)">
                        <option value="">All</option>
                        <option value="collected">Collected</option>
                        <option value="failed">Failed</option>
                    </select>
                </label>
                <label class="flex items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-neutral-500">Named</span>
                    <select :value="props.filters.named" class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900"
                        @change="setFilter('named', ($event.target as HTMLSelectElement).value)">
                        <option value="">All</option>
                        <option value="1">Named you</option>
                        <option value="0">Not named</option>
                    </select>
                </label>
            </div>

            <ul class="mt-6 space-y-4">
                <li v-for="answer in answers" :key="answer.id" class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold text-neutral-700 dark:text-neutral-300">{{ answer.engine }}</span>
                        <span class="rounded bg-neutral-100 px-2 py-0.5 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">{{ answer.method }}</span>
                        <span v-if="answer.model" class="text-neutral-500">{{ answer.model }}</span>
                        <span :class="answer.status === 'collected'
                            ? (answer.named ? 'text-green-600' : 'text-neutral-500')
                            : 'text-red-500'">
                            {{ answer.status === 'collected' ? (answer.named ? '✓ Named you' : '· not named') : '✗ failed' }}
                        </span>
                        <span v-if="answer.cited" class="text-green-600">· cited you</span>
                        <span class="ml-auto text-neutral-500">{{ fmtCost(answer.costUsd) }}</span>
                    </div>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ answer.prompt }}</p>
                    <p class="mt-2 text-sm leading-6 text-neutral-700 dark:text-neutral-300" v-if="answer.text">{{ answer.text }}</p>
                    <p class="mt-2 text-sm text-red-500" v-else-if="answer.failureReason">{{ answer.failureReason }}</p>
                </li>
                <li v-if="!answers.length" class="rounded-xl border border-neutral-200 px-4 py-8 text-center text-sm text-neutral-500 dark:border-neutral-800">
                    No answers match these filters.
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
