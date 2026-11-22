<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ app: { name: string } }>();

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login');
</script>

<template>
    <Head title="Sign in" />

    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-6">
        <div class="w-full max-w-sm">
            <p class="text-center text-xs font-semibold uppercase tracking-widest text-neutral-500">{{ app.name }}</p>
            <h1 class="mt-2 text-center text-xl font-bold text-white">Sign in to your account</h1>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <input v-model="form.email" type="email" placeholder="Email address" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <input v-model="form.password" type="password" placeholder="Password" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <label class="flex items-center gap-2 text-xs text-neutral-400">
                    <input v-model="form.remember" type="checkbox"> Remember me
                </label>
                <p v-if="form.errors.email" class="text-sm text-red-400">{{ form.errors.email }}</p>
                <button :disabled="form.processing"
                    class="w-full rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50">
                    Sign in
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-neutral-500">
                No account?
                <a href="/register" class="text-neutral-300 underline">Create one</a> ·
                <a href="/" class="text-neutral-300 underline">Back to site</a>
            </p>
        </div>
    </main>
</template>
