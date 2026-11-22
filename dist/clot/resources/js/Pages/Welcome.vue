<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    app: { name: string; locale: string };
}>();
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-neutral-950 px-6 text-neutral-200">
        <div class="w-full max-w-xl rounded-2xl border border-neutral-800 bg-neutral-900 p-8 shadow-xl">
            <p class="text-xs font-semibold uppercase tracking-widest text-neutral-500">CLOT</p>
            <h1 class="mt-2 text-2xl font-bold text-white">
                {{ app.name }} — AI visibility tracker
            </h1>
            <p class="mt-3 text-sm leading-6 text-neutral-400">
                Scaffolded on Laravel 13, Inertia 3 and Vue 3 (TypeScript). Sprint 0 foundation:
                modular monolith, tenancy-first, cost-governed collection. Panels follow the
                mono-lms design language: dark sidebar, per-panel themes, dynamic brand variables.
            </p>
            <dl class="mt-6 grid grid-cols-3 gap-4 text-center">
                <div class="rounded-lg bg-neutral-800/60 p-3">
                    <dt class="text-[11px] uppercase tracking-wide text-neutral-500">Stack</dt>
                    <dd class="mt-1 text-sm font-semibold text-neutral-200">Laravel 13 · Vue 3</dd>
                </div>
                <div class="rounded-lg bg-neutral-800/60 p-3">
                    <dt class="text-[11px] uppercase tracking-wide text-neutral-500">Phase</dt>
                    <dd class="mt-1 text-sm font-semibold text-neutral-200">Sprint 0</dd>
                </div>
                <div class="rounded-lg bg-neutral-800/60 p-3">
                    <dt class="text-[11px] uppercase tracking-wide text-neutral-500">Quality gates</dt>
                    <dd class="mt-1 text-sm font-semibold text-neutral-200">Pest · PHPStan</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
