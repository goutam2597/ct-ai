<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const page = usePage();

const props = defineProps<{
    workspaces: Array<{
        id: number;
        name: string | null;
        slug: string | null;
        role: string;
        isCurrent: boolean;
    }>;
    hasCurrent: boolean;
}>();

const form = useForm({ name: '' });

const submit = () => form.post('/workspaces', { preserveScroll: true });

const switchTo = (id: number) => {
    useForm({}).post(`/workspaces/${id}/switch`);
};
</script>

<template>
    <Head title="Workspaces" />

    <PanelLayout title="Workspaces">
    <div class="mx-auto max-w-3xl px-6 py-12">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Your workspaces</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Everything you create lives inside one of them.
        </p>

        <ul class="mt-6 divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
            <li
                v-for="workspace in props.workspaces"
                :key="workspace.id"
                class="flex items-center justify-between gap-4 px-5 py-4"
            >
                <div>
                    <p class="font-medium text-neutral-900 dark:text-neutral-100">
                        {{ workspace.name }}
                        <span
                            v-if="workspace.isCurrent"
                            class="ml-2 rounded-full bg-[var(--brand-primary)]/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[var(--brand-primary)]"
                        >
                            Current
                        </span>
                    </p>
                    <p class="mt-0.5 text-xs text-neutral-500">{{ workspace.role }}</p>
                </div>
                <button
                    v-if="!workspace.isCurrent"
                    class="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm font-medium hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800"
                    @click="switchTo(workspace.id)"
                >
                    Switch
                </button>
            </li>
        </ul>

        <form class="mt-8 flex items-start gap-3" @submit.prevent="submit">
            <input
                v-model="form.name"
                type="text"
                placeholder="New workspace name"
                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
            >
            <button
                type="submit"
                :disabled="form.processing"
                class="shrink-0 rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
            >
                Create workspace
            </button>
        </form>
        <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
        </div>
</PanelLayout>
</template>
