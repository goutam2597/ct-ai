<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const page = usePage<{
    app: { name: string };
    keys: Array<{ id: number; name: string; creator: string | null; lastUsedAt: string | null; createdAt: string | null }>;
    createdToken: string | null;
}>();

const props = page.props;
const createForm = useForm({ name: '' });
const save = () => createForm.post('/api-keys', { preserveScroll: true });
const revoke = (id: number) => useForm({}).delete(`/api-keys/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="API & MCP" />

    <PanelLayout title="API & MCP">
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">API & MCP</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Read your AI visibility data from your own tools, or let an AI assistant read it over MCP.
                Keys act as the person who created them, with their permissions. 120 requests/minute.
            </p>

            <div v-if="props.createdToken" class="mt-6 rounded-xl border border-green-300 bg-green-50 p-5 dark:border-green-800 dark:bg-green-950">
                <p class="text-sm font-semibold text-green-800 dark:text-green-300">Your new key — shown once, copy it now:</p>
                <code class="mt-2 block break-all rounded bg-neutral-900 px-3 py-2 text-sm text-green-400">{{ props.createdToken }}</code>
            </div>

            <form class="mt-8 flex items-start gap-3" @submit.prevent="save">
                <input v-model="createForm.name" type="text" placeholder="Key name (e.g. Claude Desktop)"
                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                <button class="shrink-0 rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                    :disabled="createForm.processing">
                    Create key
                </button>
            </form>
            <p v-if="createForm.errors.name" class="mt-2 text-sm text-red-600">{{ createForm.errors.name }}</p>

            <ul class="mt-8 divide-y divide-neutral-200 rounded-xl border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                <li v-for="key in props.keys" :key="key.id" class="flex items-center justify-between px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ key.name }}</p>
                        <p class="text-xs text-neutral-500">
                            by {{ key.creator }} · last used {{ key.lastUsedAt ? new Date(key.lastUsedAt).toLocaleString() : 'never' }}
                        </p>
                    </div>
                    <button class="text-xs text-red-500 hover:underline" @click="revoke(key.id)">Revoke</button>
                </li>
                <li v-if="!props.keys.length" class="px-4 py-3 text-sm text-neutral-500">No keys yet.</li>
            </ul>

            <section class="mt-10 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">REST API</h2>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-neutral-900 p-4 text-xs text-neutral-300"><code>curl https://your-domain/api/v1/brands \
  -H "Authorization: Bearer vg_…"</code></pre>
                <p class="mt-2 text-xs text-neutral-500">
                    GET /api/v1/brands · /brands/{'{'}id{'}'} · /prompts · /competitors · /sources · /answers ·
                    /opportunities — every rate carries its 95% range. POST /brands/{'{'}id{'}'}/prompts adds questions
                    (needs a member's key).
                </p>
            </section>

            <section class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">MCP server</h2>
                <p class="mt-2 text-xs text-neutral-500">Add to Claude Desktop / Cursor config:</p>
                <pre class="mt-2 overflow-x-auto rounded-lg bg-neutral-900 p-4 text-xs text-neutral-300"><code>{
  "mcpServers": {
    "clot": {
      "type": "http",
      "url": "https://your-domain/mcp/visibility",
      "headers": { "Authorization": "Bearer vg_…" }
    }
  }
}</code></pre>
            </section>
        </div>
    </PanelLayout>
</template>
