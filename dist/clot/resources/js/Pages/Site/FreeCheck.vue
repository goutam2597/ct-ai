<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    result: {
        brand: string;
        grade: string;
        mentionRate: number;
        answersCount: number;
        namedCount: number;
        answers: Array<{ question: string; text: string; named: boolean }>;
    } | null;
    app: { name: string };
}>();

const form = useForm({
    email: '',
    brand_name: '',
    website: '',
    offering: '',
});
const submit = () => form.post('/free-ai-visibility-check');

const gradeColor = (grade: string): string =>
    grade === 'A' ? 'text-green-500' : grade === 'B' ? 'text-lime-500'
        : grade === 'C' ? 'text-yellow-500' : grade === 'D' ? 'text-orange-500' : 'text-red-500';
</script>

<template>
    <Head title="Free AI visibility check" />

    <main class="min-h-screen bg-neutral-950 text-neutral-200">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <span class="font-bold tracking-tight text-white">{{ app.name }}</span>
            <a href="/" class="text-sm text-neutral-400 hover:text-white">Back to site</a>
        </header>

        <section class="mx-auto max-w-2xl px-6 py-12">
            <h1 class="text-3xl font-bold text-white">Does AI recommend your brand?</h1>
            <p class="mt-3 text-neutral-400">
                Enter your brand and what you sell. We ask an AI assistant the questions your buyers ask
                and show you, in about a minute, whether it names you. Free, no card.
            </p>

            <form v-if="!result" class="mt-10 space-y-4" @submit.prevent="submit">
                <input v-model="form.email" type="email" placeholder="Your email (we send the result here too)" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm placeholder-neutral-500">
                <input v-model="form.brand_name" type="text" placeholder="Brand name" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm placeholder-neutral-500">
                <input v-model="form.website" type="text" placeholder="Website (example.com)" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm placeholder-neutral-500">
                <input v-model="form.offering" type="text" placeholder="What you sell (e.g. CRM for agencies)" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm placeholder-neutral-500">
                <p v-if="form.errors.free_check" class="text-sm text-red-400">{{ form.errors.free_check }}</p>
                <button :disabled="form.processing"
                    class="rounded-lg bg-[var(--brand-primary)] px-6 py-3 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50">
                    {{ form.processing ? 'Asking the AI…' : 'Check my brand' }}
                </button>
            </form>

            <div v-else class="mt-10 rounded-2xl border border-neutral-800 bg-neutral-900 p-8">
                <div class="flex items-center gap-6">
                    <span class="text-6xl font-bold" :class="gradeColor(result.grade)">{{ result.grade }}</span>
                    <div>
                        <p class="font-semibold text-white">{{ result.brand }}</p>
                        <p class="text-sm text-neutral-400">
                            Named in {{ result.namedCount }} of {{ result.answersCount }} answers
                            ({{ result.mentionRate }}%)
                        </p>
                    </div>
                </div>
                <div class="mt-6 space-y-4">
                    <div v-for="(answer, i) in result.answers" :key="i"
                        class="rounded-lg border border-neutral-800 p-4 text-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ answer.question }}</p>
                        <p class="mt-2 text-neutral-300">{{ answer.text }}</p>
                        <p class="mt-2 text-xs" :class="answer.named ? 'text-green-500' : 'text-red-400'">
                            {{ answer.named ? '✓ Named you' : '✗ Did not name you' }}
                        </p>
                    </div>
                </div>
                <a href="/register" class="mt-6 inline-block rounded-lg bg-[var(--brand-primary)] px-6 py-3 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)]">
                    Track all nine engines — start free
                </a>
            </div>
        </section>
    </main>
</template>
