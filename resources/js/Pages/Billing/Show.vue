<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    plans: Array<{
        id: number;
        slug: string;
        name: string;
        description: string | null;
        priceUsd: number;
        flags: Record<string, unknown>;
        isCurrent: boolean;
    }>;
    current: { plan: string | null; allowance: number; engines: string[]; maxBrands: number };
}>();

const checkout = (id: number) => useForm({}).post(`/billing/checkout/${id}`);

const fmt = (flags: Record<string, unknown>): string[] => {
    const out: string[] = [];
    out.push(`${flags.answers_per_month ?? '–'} answers/mo`);
    out.push(`${flags.brands ?? '–'} brand(s)`);
    out.push(`${flags.seats ?? '–'} seats`);
    if (flags.api_access) out.push('API & MCP');
    if (flags.white_label) out.push('White-label reports');
    if (flags.byok) out.push('Your own AI keys');
    return out;
};
</script>

<template>
    <Head title="Plan and billing" />

    <main class="mx-auto max-w-5xl px-6 py-10">
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">Plan and billing</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            Current: <strong>{{ current.plan ?? '—' }}</strong> · {{ current.allowance }} answers/mo ·
            {{ current.engines.length }} engines · {{ current.maxBrands }} brand(s)
        </p>

        <section class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="plan in props.plans"
                :key="plan.id"
                class="flex flex-col rounded-xl border p-5"
                :class="plan.isCurrent
                    ? 'border-[var(--brand-primary)] ring-1 ring-[var(--brand-primary)]'
                    : 'border-neutral-200 dark:border-neutral-800'"
            >
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ plan.name }}</h2>
                <p class="mt-1 text-xs text-neutral-500">{{ plan.description }}</p>
                <p class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                    ${{ plan.priceUsd }}<span class="text-sm font-normal text-neutral-500"> /mo</span>
                </p>
                <ul class="mt-3 flex-1 space-y-1 text-xs text-neutral-600 dark:text-neutral-400">
                    <li v-for="line in fmt(plan.flags)" :key="line">✓ {{ line }}</li>
                </ul>
                <button
                    class="mt-4 rounded-lg px-3 py-2 text-sm font-semibold"
                    :class="plan.isCurrent
                        ? 'cursor-default bg-neutral-100 text-neutral-500 dark:bg-neutral-800'
                        : 'bg-[var(--brand-primary)] text-white hover:bg-[var(--brand-primary-hover)]'"
                    :disabled="plan.isCurrent || checkout.processing"
                    @click="checkout(plan.id)"
                >
                    {{ plan.isCurrent ? 'Current plan' : 'Choose this plan' }}
                </button>
            </div>
        </section>
    </main>
</template>
