<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

defineProps<{
    events: Array<{
        id: number;
        rule: string;
        title: string;
        body: Record<string, unknown> | null;
        createdAt: string | null;
    }>;
    channels: Array<{ id: number; type: string; target: string; isActive: boolean }>;
}>();

const channelForm = useForm({ type: 'webhook', target: '' });
const addChannel = () => channelForm.post('/alerts/channels', { preserveScroll: true });
const removeChannel = (id: number) => useForm({}).delete(`/alerts/channels/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Alerts" />

    <PanelLayout title="Alerts">
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">Alerts</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
              Quiet when nothing changes. Sent once, the morning the ranges separate.
            </p>

            <section class="mt-8">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Channels</h2>
                <ul class="mt-3 divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                    <li v-for="channel in channels" :key="channel.id" class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="rounded bg-neutral-100 px-2 py-0.5 text-xs font-semibold uppercase text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                            {{ channel.type }}
                        </span>
                        <span class="mx-4 flex-1 truncate text-neutral-600 dark:text-neutral-400">{{ channel.target }}</span>
                        <button class="text-xs text-red-500 hover:underline" @click="removeChannel(channel.id)">Remove</button>
                    </li>
                    <li v-if="!channels.length" class="px-4 py-3 text-sm text-neutral-500">
                        Nothing connected yet — alerts arrive by email and in the bell.
                    </li>
                </ul>
                <form class="mt-3 flex items-start gap-3" @submit.prevent="addChannel">
                    <select v-model="channelForm.type" class="rounded-lg border border-neutral-300 px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <option value="webhook">Signed webhook</option>
                        <option value="slack">Slack incoming webhook</option>
                    </select>
                    <input v-model="channelForm.target" type="url" placeholder="https://hooks.example/clot"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <button class="shrink-0 rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50" :disabled="channelForm.processing">
                        Connect
                    </button>
                </form>
                <p v-if="channelForm.errors.target" class="mt-2 text-sm text-red-600">{{ channelForm.errors.target }}</p>
            </section>

            <section class="mt-10">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Recent alerts</h2>
                <ul class="mt-3 space-y-3">
                    <li v-for="event in events" :key="event.id" class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-800">
                        <div class="flex items-center justify-between">
                            <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ event.title }}</p>
                            <span class="text-xs text-neutral-500">{{ event.createdAt ? new Date(event.createdAt).toLocaleString() : '' }}</span>
                        </div>
                        <p class="mt-1 text-xs uppercase tracking-wide text-neutral-400">{{ event.rule }}</p>
                    </li>
                    <li v-if="!events.length" class="rounded-xl border border-neutral-200 px-4 py-6 text-center text-sm text-neutral-500 dark:border-neutral-800">
                        No alerts yet.
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
