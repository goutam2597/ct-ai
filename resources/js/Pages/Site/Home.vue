<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ engines: string[]; app: { name: string } }>();
</script>

<template>
    <Head title="Does AI recommend your brand?" />

    <main class="min-h-screen bg-neutral-950 text-neutral-200">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <span class="font-bold tracking-tight text-white">{{ app.name }}</span>
            <nav class="flex items-center gap-5 text-sm">
                <a href="/pricing" class="text-neutral-400 hover:text-white">Pricing</a>
                <a href="/free-ai-visibility-check" class="text-neutral-400 hover:text-white">Free check</a>
                <a href="/login" class="rounded-lg border border-neutral-700 px-3 py-1.5 hover:border-neutral-500">Sign in</a>
            </nav>
        </header>

        <section class="mx-auto max-w-4xl px-6 py-24 text-center">
            <h1 class="text-4xl font-bold leading-tight text-white md:text-5xl">
                Buyers ask AI.<br>
                <span class="text-[var(--brand-accent)]">Are you in the answer?</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-neutral-400">
                Track how often AI assistants name your brand, who they pick instead, and which sources
                they trust — with an honest 95% range on every number.
            </p>
            <div class="mt-10 flex items-center justify-center gap-4">
                <a href="/free-ai-visibility-check"
                    class="rounded-lg bg-[var(--brand-primary)] px-6 py-3 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)]">
                    Check my brand — free
                </a>
                <a href="/pricing" class="rounded-lg border border-neutral-700 px-6 py-3 text-sm font-semibold hover:border-neutral-500">
                    See pricing
                </a>
            </div>
        </section>

        <section class="mx-auto max-w-5xl px-6 pb-24">
            <p class="text-center text-xs font-semibold uppercase tracking-widest text-neutral-500">Tracked engines</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <span v-for="engine in engines" :key="engine"
                    class="rounded-full border border-neutral-800 bg-neutral-900 px-4 py-1.5 text-sm text-neutral-300">
                    {{ engine }}
                </span>
            </div>
        </section>

        <section class="mx-auto max-w-5xl px-6 pb-24">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-6">
                    <h2 class="font-semibold text-white">Measured, not guessed</h2>
                    <p class="mt-2 text-sm text-neutral-400">
                        One AI answer proves nothing. Every rate carries its 95% Wilson range and a trend is
                        called only when the ranges separate.
                    </p>
                </div>
                <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-6">
                    <h2 class="font-semibold text-white">Read the evidence</h2>
                    <p class="mt-2 text-sm text-neutral-400">
                        Every answer is stored in full with its engine, method and cost — nothing is hidden
                        behind a score.
                    </p>
                </div>
                <div class="rounded-xl border border-neutral-800 bg-neutral-900 p-6">
                    <h2 class="font-semibold text-white">Know what to fix</h2>
                    <p class="mt-2 text-sm text-neutral-400">
                        A ranked list of the questions rivals win and the sites that cite them but not you.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
