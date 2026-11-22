<script setup lang="ts">
import Layout from './Layout.vue';

defineProps<{
    workspaces: number;
    users: number;
    brands: number;
    answersMonth: number;
    failuresMonth: number;
    spend: { todayUsd: number; capUsd: number; monthUsd: number };
    billing: { mrrUsd: number; active: number; trialing: number };
    health: { cronHeartbeat: boolean; queuePing: boolean };
    app: { name: string };
}>();
</script>

<template>
    <Layout title="Dashboard" :app="app">
        <h1 class="text-2xl font-bold text-white">Dashboard</h1>
        <p class="mt-1 text-sm text-neutral-400">Business overview: revenue, customers, AI usage and system health.</p>

        <section class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">MRR</p>
                <p class="mt-2 text-2xl font-bold text-white">US${{ billing.mrrUsd.toFixed(2) }}</p>
                <p class="mt-1 text-xs text-neutral-500">{{ billing.active }} active · {{ billing.trialing }} trialing</p>
            </div>
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Workspaces</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ workspaces }}</p>
                <p class="mt-1 text-xs text-neutral-500">{{ users }} users · {{ brands }} brands</p>
            </div>
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Answers this month</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ answersMonth }}</p>
                <p class="mt-1 text-xs text-neutral-500">{{ failuresMonth }} failed (excluded from rates)</p>
            </div>
            <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">AI spend today</p>
                <p class="mt-2 text-2xl font-bold text-white">US${{ spend.todayUsd.toFixed(2) }} <span class="text-sm font-normal text-neutral-500">/ cap US${{ spend.capUsd.toFixed(2) }}</span></p>
                <p class="mt-1 text-xs text-neutral-500">Month: US${{ spend.monthUsd.toFixed(2) }}</p>
            </div>
        </section>

        <section class="mt-6 rounded-xl border border-neutral-800 bg-neutral-900 p-5">
            <h2 class="text-sm font-semibold text-neutral-300">System health</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li class="flex items-center gap-2">
                    <span :class="health.cronHeartbeat ? 'text-green-500' : 'text-amber-500'">{{ health.cronHeartbeat ? '✓' : '⚠' }}</span>
                    Cron heartbeat {{ health.cronHeartbeat ? 'fresh' : 'stale — add the schedule:run cron entry' }}
                </li>
                <li class="flex items-center gap-2">
                    <span :class="health.queuePing ? 'text-green-500' : 'text-amber-500'">{{ health.queuePing ? '✓' : '⚠' }}</span>
                    Queue canary {{ health.queuePing ? 'processed' : 'not yet processed — start your queue worker' }}
                </li>
            </ul>
        </section>
    </Layout>
</template>
