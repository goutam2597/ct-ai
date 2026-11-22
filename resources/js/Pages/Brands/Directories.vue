<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandTabs from './BrandTabs.vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps<{
    app: { name: string };
    brand: { id: number; name: string };
    directories: Array<{
        name: string;
        domain: string;
        category: string;
        cost: string;
        priority: string;
        cited_in_answers: number;
        rival_gap: number;
        gap_detail: string | null;
    }>;
}>();

const priorityColor = (p: string): string =>
    p === 'High' ? 'text-red-600 bg-red-50 dark:bg-red-950 dark:text-red-400'
    : p === 'Medium' ? 'text-amber-600 bg-amber-50 dark:bg-amber-950 dark:text-amber-400'
    : 'text-neutral-500 bg-neutral-100 dark:bg-neutral-800 dark:text-neutral-400';
</script>

<template>
    <Head :title="`Directories — ${props.brand.name}`" />

    <PanelLayout :title="`Directories — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="directories" />

        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                Directories · {{ props.brand.name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                AI answers lean on review sites. CLOT ranks them, writes the text to submit, but never
                submits for you — these sites forbid automated submissions.
            </p>

            <ul class="mt-8 space-y-3">
                <li v-for="dir in props.directories" :key="dir.domain" class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ dir.name }}</p>
                            <p class="text-xs text-neutral-500">{{ dir.domain }} · {{ dir.category }} · {{ dir.cost }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="priorityColor(dir.priority)">
                            {{ dir.priority }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-xs">
                        <span class="text-neutral-500">Cited in {{ dir.cited_in_answers }} answers</span>
                        <span v-if="dir.gap_detail" class="text-amber-600 dark:text-amber-400">{{ dir.gap_detail }}</span>
                    </div>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
