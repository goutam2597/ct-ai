<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandTabs from './BrandTabs.vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps<{
    app: { name: string };
    brand: { id: number; name: string };
    sources: Array<{ domain: string; owner_type: string; citations: number }>;
}>();

const ownerLabel = (type: string): string =>
    ({ you: 'Yours', competitor: 'Competitor', third_party: 'Third party' })[type] ?? type;

const ownerColor = (type: string): string =>
    ({ you: 'text-green-600', competitor: 'text-red-500', third_party: 'text-neutral-500' })[type] ?? 'text-neutral-500';
</script>

<template>
    <Head :title="`Sources — ${props.brand.name}`" />

    <PanelLayout :title="`Sources — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="sources" />

        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                Sources · {{ props.brand.name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                The domains the engines cite. Classified as yours, a competitor's or a third party's —
                so the pages worth earning a place on stand out.
            </p>

            <div class="mt-8 rounded-xl border border-neutral-200 dark:border-neutral-800">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-neutral-500 border-b border-neutral-200 dark:border-neutral-800">
                        <tr><th class="px-5 py-3">Domain</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3 text-right">Citations</th></tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        <tr v-for="source in props.sources" :key="source.domain + source.owner_type">
                            <td class="px-5 py-3 font-medium text-neutral-800 dark:text-neutral-200">{{ source.domain }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-semibold" :class="ownerColor(source.owner_type)">{{ ownerLabel(source.owner_type) }}</span>
                            </td>
                            <td class="px-5 py-3 text-right text-neutral-500">{{ source.citations }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </PanelLayout>
</template>
