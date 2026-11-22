<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    steps: {
        requirementsMet: boolean;
        requirements: Array<{ label: string; ok: boolean; hint: string | null }>;
        migrated: boolean;
        accountCreated: boolean;
    };
    envExists: boolean;
}>();

const dbForm = useForm({
    driver: 'sqlite',
    database: '',
    host: '127.0.0.1',
    port: 3306,
    name: '',
    user: '',
    password: '',
});

const accountForm = useForm({
    name: '',
    email: '',
    password: '',
});

const finishForm = useForm({});
</script>

<template>
    <Head title="Install CLOT" />

    <main class="mx-auto max-w-3xl px-6 py-12">
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">Install CLOT</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
            This installer seals itself when it finishes — it can never run again on this install.
        </p>

        <section class="mt-8 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
            <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">1 · Server requirements</h2>
            <ul class="mt-3 space-y-1.5 text-sm">
                <li v-for="check in props.steps.requirements" :key="check.label" class="flex items-start gap-2">
                    <span :class="check.ok ? 'text-green-600' : 'text-red-600'">{{ check.ok ? '✓' : '✗' }}</span>
                    <span class="text-neutral-700 dark:text-neutral-300">
                        {{ check.label }}
                        <span v-if="!check.ok && check.hint" class="block text-xs text-neutral-500">{{ check.hint }}</span>
                    </span>
                </li>
            </ul>
        </section>

        <section
            class="mt-6 rounded-xl border p-5"
            :class="props.steps.requirementsMet ? 'border-neutral-200 dark:border-neutral-800' : 'border-neutral-300 opacity-50 dark:border-neutral-700'"
        >
            <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">2 · Database</h2>
            <p class="mt-1 text-xs text-neutral-500">
                {{ props.steps.migrated ? 'Migrated ✓' : 'Test the connection, write the .env, then migrate.' }}
            </p>
            <form class="mt-3 space-y-3" @submit.prevent="dbForm.post('/setup/database')">
                <div class="flex items-center gap-3 text-sm">
                    <label class="w-24 text-neutral-600 dark:text-neutral-400">Driver</label>
                    <select v-model="dbForm.driver" class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900">
                        <option value="sqlite">SQLite</option>
                        <option value="mysql">MySQL / MariaDB</option>
                    </select>
                </div>
                <template v-if="dbForm.driver === 'sqlite'">
                    <div class="flex items-center gap-3 text-sm">
                        <label class="w-24 text-neutral-600 dark:text-neutral-400">File</label>
                        <input v-model="dbForm.database" type="text" placeholder="database/database.sqlite"
                            class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                    </div>
                </template>
                <template v-else>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <input v-model="dbForm.host" type="text" placeholder="Host"
                            class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900">
                        <input v-model="dbForm.port" type="number" placeholder="Port"
                            class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900">
                        <input v-model="dbForm.name" type="text" placeholder="Database name"
                            class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900">
                        <input v-model="dbForm.user" type="text" placeholder="User"
                            class="rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900">
                        <input v-model="dbForm.password" type="password" placeholder="Password"
                            class="col-span-2 rounded-lg border border-neutral-300 px-2 py-1.5 dark:border-neutral-700 dark:bg-neutral-900">
                    </div>
                </template>
                <button
                    :disabled="!props.steps.requirementsMet || dbForm.processing"
                    class="rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                >
                    Test &amp; migrate
                </button>
                <p v-if="dbForm.errors.database" class="text-sm text-red-600">{{ dbForm.errors.database }}</p>
            </form>
        </section>

        <section
            class="mt-6 rounded-xl border p-5"
            :class="props.steps.migrated ? 'border-neutral-200 dark:border-neutral-800' : 'border-neutral-300 opacity-50 dark:border-neutral-700'"
        >
            <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">3 · First operator account</h2>
            <p class="mt-1 text-xs text-neutral-500">{{ props.steps.accountCreated ? 'Created ✓' : 'The installer will never overwrite this account.' }}</p>
            <form class="mt-3 space-y-3" @submit.prevent="accountForm.post('/setup/account')">
                <input v-model="accountForm.name" type="text" placeholder="Name"
                    class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                <input v-model="accountForm.email" type="email" placeholder="Email"
                    class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                <input v-model="accountForm.password" type="password" placeholder="Password (min 8 chars)"
                    class="w-full rounded-lg border border-neutral-300 px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-900">
                <button
                    :disabled="!props.steps.migrated || accountForm.processing"
                    class="rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                >
                    Create account
                </button>
                <p v-if="accountForm.errors.account" class="text-sm text-red-600">{{ accountForm.errors.account }}</p>
            </form>
        </section>

        <section class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
            <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">4 · Verify &amp; seal</h2>
            <p class="mt-1 text-xs text-neutral-500">
                Sends a queue canary, then seals the installer. After sealing, /setup returns 404.
            </p>
            <button
                :disabled="!props.steps.accountCreated || finishForm.processing"
                class="mt-3 rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                @click="finishForm.post('/setup/finish')"
            >
                Finish installation
            </button>
            <p v-if="finishForm.errors.finish" class="mt-2 text-sm text-red-600">{{ finishForm.errors.finish }}</p>
        </section>
    </main>
</template>
