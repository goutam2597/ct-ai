<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{ status: string | null; app: { name: string } }>();

const form = useForm({});
const resend = () => form.post('/email/verification-notification', { preserveScroll: true });
</script>

<template>
    <Head title="Verify your email" />

    <main class="flex min-h-screen items-center justify-center bg-neutral-950 px-6">
        <div class="w-full max-w-md text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-neutral-500">{{ app.name }}</p>
            <h1 class="mt-2 text-xl font-bold text-white">Confirm your email</h1>
            <p class="mt-4 text-sm text-neutral-400">
                We sent a verification link to your inbox. Click it to continue.
            </p>

            <p v-if="status === 'verification-link-sent'" class="mt-4 text-sm text-green-500">
                A new verification link has been sent.
            </p>

            <div class="mt-8 flex items-center justify-center gap-4">
                <button
                    class="rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                    :disabled="form.processing"
                    @click="resend"
                >
                    Resend link
                </button>
                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                    <button class="text-sm text-neutral-400 underline">Log out</button>
                </form>
            </div>
        </div>
    </main>
</template>
