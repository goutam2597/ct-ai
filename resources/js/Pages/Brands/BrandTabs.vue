<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    brand: { id: number; name: string };
    active: string;
}>();

const tabs = [
    { key: 'overview', label: 'Overview', path: '' },
    { key: 'answers', label: 'Answers', path: '/answers' },
    { key: 'opportunities', label: 'Opportunities', path: '/opportunities' },
    { key: 'briefs', label: 'Content briefs', path: '/content-briefs' },
    { key: 'site-check', label: 'Site check', path: '/site-check' },
    { key: 'share-links', label: 'Share links', path: '/share-links' },
];
</script>

<template>
    <nav class="flex flex-wrap gap-1 border-b border-neutral-200 px-6 dark:border-neutral-800" aria-label="Brand tabs">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="`/brands/${props.brand.id}${tab.path}`"
            class="rounded-t-lg px-4 py-2.5 text-sm font-medium transition-colors"
            :class="active === tab.key
                ? 'border-b-2 border-[var(--brand-primary)] text-[var(--brand-primary)]'
                : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-200'"
            :aria-current="active === tab.key ? 'page' : undefined"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
