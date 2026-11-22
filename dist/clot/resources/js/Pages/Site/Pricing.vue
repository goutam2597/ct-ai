<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    plans: Array<{
        slug: string;
        name: string;
        description: string | null;
        priceUsd: number;
        flags: Record<string, unknown>;
        isDefault: boolean;
    }>;
    app: { name: string };
}>();

const lines = (flags: Record<string, unknown>): string[] => [
    `${flags.answers_per_month ?? '–'} answers/mo`,
    `${flags.brands ?? '–'} brand(s) · ${flags.seats ?? '–'} seats`,
    `${flags.prompts ?? '–'} tracked prompts`,
    ...(Array.isArray(flags.engines) ? [`${flags.engines.length} engines`] : []),
    flags.api_access ? 'API & MCP access' : '',
    flags.white_label ? 'White-label client reports' : '',
    flags.byok ? 'Your own AI provider keys' : '',
].filter(Boolean) as string[];
</script>

<template>
    <Head title="Pricing" />

    <main class="min-h-screen bg-neutral-950 text-neutral-200">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <span class="font-bold tracking-tight text-white">{{ app.name }}</span>
            <a href="/" class="text-sm text-neutral-400 hover:text-white">Back to site</a>
        </header>

        <section class="mx-auto max-w-5xl px-6 py-12 text-center">
            <h1 class="text-3xl font-bold text-white">Pay for the answers you measure</h1>
            <p class="mt-3 text-neutral-400">
                Split your monthly answers across questions, engines and runs. When the allowance runs
                out, collection pauses — you are never charged for going over.
            </p>

            <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="plan in plans"
                    :key="plan.slug"
                    class="flex flex-col rounded-xl border p-6 text-left"
                    :class="plan.isDefault
                        ? 'border-[var(--brand-primary)] ring-1 ring-[var(--brand-primary)]'
                        : 'border-neutral-800 bg-neutral-900'"
                >
                    <h2 class="font-semibold text-white">{{ plan.name }}</h2>
                    <p class="mt-1 text-xs text-neutral-500">{{ plan.description }}</p>
                    <p class="mt-4 text-3xl font-bold text-white">
                        ${{ plan.priceUsd }}<span class="text-sm font-normal text-neutral-500"> /mo</span>
                    </p>
                    <ul class="mt-4 flex-1 space-y-1.5 text-xs text-neutral-400">
                        <li v-for="line in lines(plan.flags)" :key="line">✓ {{ line }}</li>
                    </ul>
                    <a
                        :href="plan.priceUsd > 0 ? '/register' : '/register'"
                        class="mt-6 rounded-lg px-4 py-2 text-center text-sm font-semibold"
                        :class="plan.isDefault
                            ? 'bg-[var(--brand-primary)] text-white hover:bg-[var(--brand-primary-hover)]'
                            : 'border border-neutral-700 text-neutral-200 hover:border-neutral-500'"
                    >
                        {{ plan.isDefault ? 'Get started' : 'Try free for 7 days' }}
                    </a>
                </div>
            </div>
        </section>
    </main>
</template>
