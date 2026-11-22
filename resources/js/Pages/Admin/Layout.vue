<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ title: string; app: { name: string } }>();
</script>

<template>
    <Head :title="title" />

    <main class="min-h-screen bg-neutral-950 text-neutral-200">
        <header class="border-b border-neutral-800 bg-neutral-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-6">
                    <span class="font-bold text-white">{{ app.name }} · Admin</span>
                    <nav class="flex items-center gap-4 text-sm">
                        <a href="/admin" class="text-neutral-400 hover:text-white">Dashboard</a>
                        <a href="/admin/workspaces" class="text-neutral-400 hover:text-white">Workspaces</a>
                        <a href="/admin/engines" class="text-neutral-400 hover:text-white">Engines</a>
                    </nav>
                </div>
                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''">
                    <button class="text-sm text-neutral-400 hover:text-white">Log out</button>
                </form>
            </div>
        </header>

        <div class="mx-auto max-w-6xl px-6 py-8">
            <slot />
        </div>
    </main>
</template>
