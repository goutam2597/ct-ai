<script setup lang="ts">
import Layout from './Layout.vue';

defineProps<{
    workspaces: Array<{
        id: number;
        name: string;
        owner: string | null;
        plan: string | null;
        status: string | null;
        brands: number;
        members: number;
        answersThisMonth: number;
    }>;
    app: { name: string };
}>();
</script>

<template>
    <Layout title="Workspaces" :app="app">
        <h1 class="text-2xl font-bold text-white">Workspaces</h1>
        <p class="mt-1 text-sm text-neutral-400">Every customer workspace in this installation.</p>

        <div class="mt-6 overflow-x-auto rounded-xl border border-neutral-800">
            <table class="w-full text-sm">
                <thead class="bg-neutral-900 text-left text-xs uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Workspace</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Brands</th>
                        <th class="px-4 py-3 text-right">Members</th>
                        <th class="px-4 py-3 text-right">Answers/mo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-800">
                    <tr v-for="ws in workspaces" :key="ws.id" class="hover:bg-neutral-900/50">
                        <td class="px-4 py-3 font-medium text-neutral-100">{{ ws.name }}</td>
                        <td class="px-4 py-3 text-neutral-400">{{ ws.owner }}</td>
                        <td class="px-4 py-3 text-neutral-300">{{ ws.plan ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="ws.status === 'active' ? 'bg-green-500/10 text-green-500'
                                    : ws.status === 'trialing' ? 'bg-blue-500/10 text-blue-400'
                                    : ws.status === 'past_due' ? 'bg-amber-500/10 text-amber-500'
                                    : 'bg-neutral-500/10 text-neutral-400'"
                            >
                                {{ ws.status ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-neutral-300">{{ ws.brands }}</td>
                        <td class="px-4 py-3 text-right text-neutral-300">{{ ws.members }}</td>
                        <td class="px-4 py-3 text-right text-neutral-300">{{ ws.answersThisMonth }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Layout>
</template>
