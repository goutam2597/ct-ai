<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Layout from './Layout.vue';

const props = defineProps<{
    gateways: Array<{
        key: string;
        label: string;
        secretConfigured: boolean;
        webhookSecretConfigured: boolean;
        webhookUrl: string;
    }>;
    envWritable: boolean;
    app: { name: string };
}>();

// One form state per gateway.
const forms = Object.fromEntries(
    props.gateways.map((g) => [
        g.key,
        useForm({ gateway: g.key, secret_key: '', webhook_secret: '' }),
    ]),
) as Record<string, ReturnType<typeof useForm>>;

const save = (key: string) => forms[key].post('/admin/billing/keys', { preserveScroll: true });
</script>

<template>
    <Head title="Payment providers" />

    <Layout title="Payment providers" :app="app">
        <h1 class="text-2xl font-bold text-white">Payment providers</h1>
        <p class="mt-1 text-sm text-neutral-400">
            Keys are written to the server's .env and never shown again. Configure the webhook URL on
            the provider's dashboard.
        </p>

        <section
            v-for="gateway in gateways"
            :key="gateway.key"
            class="mt-6 rounded-xl border border-neutral-800 bg-neutral-900 p-5"
        >
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-white">{{ gateway.label }}</h2>
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="gateway.secretConfigured ? 'bg-green-500/10 text-green-500' : 'bg-amber-500/10 text-amber-500'"
                >
                    {{ gateway.secretConfigured ? 'Configured' : 'Keys missing' }}
                </span>
            </div>

            <div class="mt-3 text-xs text-neutral-500">
                Webhook URL:
                <code class="rounded bg-neutral-800 px-1.5 py-0.5 text-neutral-300">{{ gateway.webhookUrl }}</code>
            </div>

            <form
                class="mt-4 grid grid-cols-2 gap-3"
                @submit.prevent="save(gateway.key)"
            >
                <input
                    v-model="forms[gateway.key].secret_key"
                    type="password"
                    :placeholder="gateway.secretConfigured ? 'Secret key (set — enter to replace)' : 'Secret key'"
                    class="rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-2 text-sm text-neutral-100 placeholder-neutral-500"
                >
                <input
                    v-model="forms[gateway.key].webhook_secret"
                    type="password"
                    :placeholder="gateway.webhookSecretConfigured ? 'Webhook secret (set — enter to replace)' : 'Webhook secret'"
                    class="rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-2 text-sm text-neutral-100 placeholder-neutral-500"
                >
                <button
                    class="col-span-2 rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                    :disabled="forms[gateway.key].processing"
                >
                    Save keys
                </button>
            </form>
        </section>
    </Layout>
</template>
