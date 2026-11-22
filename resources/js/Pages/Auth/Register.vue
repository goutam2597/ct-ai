<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ app: { name: string } }>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    workspace: '',
});
const submit = () => form.post('/register');
</script>

<template>
    <Head title="Create account" />

    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-6">
        <div class="w-full max-w-sm">
            <p class="text-center text-xs font-semibold uppercase tracking-widest text-neutral-500">{{ app.name }}</p>
            <h1 class="mt-2 text-center text-xl font-bold text-white">Start free — no card needed</h1>
            <p class="mt-1 text-center text-xs text-neutral-500">Your workspace lands on the Free plan.</p>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <input v-model="form.name" type="text" placeholder="Your name" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <input v-model="form.email" type="email" placeholder="Email address" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <input v-model="form.workspace" type="text" placeholder="Workspace name (optional)"
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <input v-model="form.password" type="password" placeholder="Password (min 8 chars)" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <input v-model="form.password_confirmation" type="password" placeholder="Confirm password" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <p v-if="form.errors.email || form.errors.password" class="text-sm text-red-400">
                    {{ form.errors.email || form.errors.password }}
                </p>
                <button :disabled="form.processing"
                    class="w-full rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50">
                    Create account
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-neutral-500">
                Have an account? <a href="/login" class="text-neutral-300 underline">Sign in</a>
            </p>
        </div>
    </main>
</template>
