<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Layout from './Layout.vue';

defineProps<{
    engines: Array<{ key: string; label: string; collector: string }>;
    app: { name: string };
}>();

const form = useForm({});
const test = (key: string) => form.post(`/admin/engines/${key}/test`, { preserveScroll: true });
</script>

<template>
    <Layout title="Answer engines" :app="app">
        <h1 class="text-2xl font-bold text-white">Answer engines</h1>
        <p class="mt-1 text-sm text-neutral-400">
            Where each engine's answers come from. Collector keys and models are configured in the
            engine settings (provider sprint); test calls are billed like any other answer.
        </p>

        <p v-if="form.errors.test" class="mt-4 rounded-lg border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-400">
            {{ form.errors.test }}
        </p>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div
                v-for="engine in engines"
                :key="engine.key"
                class="rounded-xl border border-neutral-800 bg-neutral-900 p-5"
            >
                <h2 class="font-semibold text-white">{{ engine.label }}</h2>
                <p class="mt-1 text-xs text-neutral-500">{{ engine.key }} · {{ engine.collector }}</p>
                <button
                    class="mt-4 rounded-lg border border-neutral-700 px-3 py-1.5 text-sm text-neutral-200 hover:border-neutral-500 disabled:opacity-50"
                    :disabled="form.processing"
                    @click="test(engine.key)"
                >
                    Run test call
                </button>
            </div>
        </div>
    </Layout>
</template>
