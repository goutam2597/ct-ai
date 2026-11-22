<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ status: string | null; app: { name: string } }>();

const form = useForm({ email: '' });
const submit = () => form.post('/forgot-password');
</script>

<template>
    <Head title="Forgot password" />

    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-6">
        <div class="w-full max-w-sm">
            <h1 class="text-xl font-bold text-white">Forgot your password?</h1>
            <p class="mt-2 text-sm text-neutral-400">
                Enter your email and we send a reset link.
            </p>

            <p v-if="status" class="mt-4 text-sm text-green-500">{{ status }}</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <input v-model="form.email" type="email" placeholder="Email address" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <p v-if="form.errors.email" class="text-sm text-red-400">{{ form.errors.email }}</p>
                <button :disabled="form.processing"
                    class="w-full rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50">
                    Send reset link
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-neutral-500">
                <a href="/login" class="text-neutral-300 underline">Back to sign in</a>
            </p>
        </div>
    </main>
</template>
