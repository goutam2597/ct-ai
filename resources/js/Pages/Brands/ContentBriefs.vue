<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const page = usePage<{
    app: { name: string };
    brand: { id: number; name: string };
    briefs: Array<{
        prompt_id: number;
        question: string;
        topic: string | null;
        mention_rate: number | null;
        range: [number | null, number | null];
        answers: number;
        named: number;
        rival_named_instead: string | null;
        cited_sources: string[];
        sample_answer: string | null;
        angle: string;
    }>;
}>();

const props = page.props;
const pct = (v: number | null) => v === null ? '—' : Math.round(v * 100) + '%';
</script>

<template>
    <Head :title="`Content briefs — ${props.brand.name}`" />

    <PanelLayout :title="`Content briefs — ${props.brand.name}`">
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                Content briefs · {{ props.brand.name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Page outlines for questions you're losing, built from the real answers and the sources
                the engines already cite. Edit, approve, publish.
            </p>

            <ul class="mt-8 space-y-4">
                <li v-for="brief in props.briefs" :key="brief.prompt_id" class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ brief.question }}</p>
                        <span class="text-xs text-neutral-500">{{ pct(brief.mention_rate) }} · {{ brief.answers }} answers</span>
                    </div>
                    <p v-if="brief.rival_named_instead" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        {{ brief.rival_named_instead }} is named instead of you
                    </p>
                    <p class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                        <strong>Angle:</strong> {{ brief.angle }}
                    </p>
                    <div v-if="brief.cited_sources.length" class="mt-3 flex flex-wrap gap-2">
                        <span v-for="source in brief.cited_sources" :key="source"
                            class="rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                            {{ source }}
                        </span>
                    </div>
                    <blockquote v-if="brief.sample_answer" class="mt-3 border-l-2 border-neutral-300 pl-3 text-xs text-neutral-500 dark:border-neutral-600">
                        {{ brief.sample_answer }}
                    </blockquote>
                </li>
                <li v-if="!props.briefs.length" class="rounded-xl border border-neutral-200 px-4 py-8 text-center text-sm text-neutral-500 dark:border-neutral-800">
                    No losing questions with enough data. Collect more answers first.
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
