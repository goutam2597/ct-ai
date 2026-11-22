<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import BrandTabs from './BrandTabs.vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps<{
    app: { name: string };
    brand: { id: number; name: string };
    links: Array<{
        id: number;
        url: string;
        revoked: boolean;
        createdAt: string | null;
    }>;
}>();

const createForm = useForm({});
const create = () => createForm.post(`/brands/${props.brand.id}/share-links`, { preserveScroll: true });

const revoke = (linkId: number) => useForm({}).delete(`/brands/${props.brand.id}/share-links/${linkId}`, { preserveScroll: true });

const copy = (url: string) => {
    navigator.clipboard.writeText(url);
};
</script>

<template>
    <Head :title="`Share links — ${props.brand.name}`" />

    <PanelLayout :title="`Share links — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="share-links" />

        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                Share links · {{ props.brand.name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Read-only report links. No login needed, never indexed, revocable.
            </p>

            <button
                class="mt-6 rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                :disabled="createForm.processing"
                @click="create"
            >
                Create share link
            </button>

            <ul class="mt-8 space-y-3">
                <li v-for="link in props.links" :key="link.id" class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <code class="block truncate text-sm text-neutral-700 dark:text-neutral-300">{{ link.url }}</code>
                            <p class="mt-1 text-xs text-neutral-500">
                                Created {{ link.createdAt ? new Date(link.createdAt).toLocaleString() : '' }}
                                <span v-if="link.revoked" class="ml-2 text-red-500">· Revoked</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button v-if="!link.revoked" class="text-xs text-neutral-500 underline" @click="copy(link.url)">Copy</button>
                            <a v-if="!link.revoked" :href="link.url" target="_blank" class="text-xs text-neutral-500 underline">Open</a>
                            <button v-if="!link.revoked" class="text-xs text-red-500 hover:underline" @click="revoke(link.id)">Revoke</button>
                        </div>
                    </div>
                </li>
                <li v-if="!props.links.length" class="rounded-xl border border-neutral-200 px-4 py-8 text-center text-sm text-neutral-500 dark:border-neutral-800">
                    No share links yet.
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
