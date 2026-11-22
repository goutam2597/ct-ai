<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ token: string; email: string; app: { name: string } }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});
const submit = () => form.post('/reset-password');
</script>

<template>
    <Head title="Reset password" />

    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-6">
        <div class="w-full max-w-sm">
            <h1 class="text-xl font-bold text-white">Choose a new password</h1>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <input v-model="form.email" type="email" placeholder="Email address" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <input v-model="form.password" type="password" placeholder="New password (min 8 chars)" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <input v-model="form.password_confirmation" type="password" placeholder="Confirm password" required
                    class="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2.5 text-sm text-neutral-100 placeholder-neutral-500">
                <p v-if="form.errors.email || form.errors.password" class="text-sm text-red-400">
                    {{ form.errors.email || form.errors.password }}
                </p>
                <button :disabled="form.processing"
                    class="w-full rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50">
                    Reset password
                </button>
            </form>
        </div>
    </main>
</template>
