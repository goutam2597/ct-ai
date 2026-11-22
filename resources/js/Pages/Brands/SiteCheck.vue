<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandTabs from './BrandTabs.vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps<{
    app: { name: string };
    brand: { id: number; name: string };
    check: {
        domain: string;
        score: number;
        checks: Record<string, { pass: boolean; detail: string }>;
        crawlers: {
            answer?: Array<{ bot: string; description: string; blocked: boolean }>;
            training?: Array<{ bot: string; description: string; blocked: boolean }>;
        };
        llmsTxt: string;
        checkedAt: string;
    };
}>();

const checkLabels: Record<string, string> = {
    home_page_loads: 'Home page loads',
    answer_crawlers_allowed: 'AI search crawlers are let in',
    pages_can_be_quoted: 'Pages can be quoted',
    llms_txt: 'llms.txt published',
    sitemap: 'Sitemap listed',
    title_and_description: 'Title and description',
    organisation_markup: 'Organisation markup',
    product_markup: 'Product, service or FAQ markup',
    enough_to_read: 'Enough to read',
};

const scoreColor = (score: number): string =>
    score >= 80 ? 'text-green-600' : score >= 60 ? 'text-yellow-600' : score >= 40 ? 'text-orange-500' : 'text-red-500';
</script>

<template>
    <Head :title="`Site check — ${props.brand.name}`" />

    <PanelLayout :title="`Site check — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="site-check" />

        <div class="mx-auto max-w-4xl px-6 py-8">
            <div class="flex items-center gap-6">
                <div class="text-center">
                    <p class="text-5xl font-bold" :class="scoreColor(props.check.score)">{{ props.check.score }}</p>
                    <p class="text-xs text-neutral-500">/ 100</p>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-neutral-900 dark:text-neutral-50">Site check</h1>
                    <p class="text-sm text-neutral-500">{{ props.check.domain }} · checked {{ new Date(props.check.checkedAt).toLocaleString() }}</p>
                </div>
            </div>

            <section class="mt-8 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Nine checks</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="(check, key) in props.check.checks" :key="key" class="flex items-start gap-2">
                        <span :class="check.pass ? 'text-green-600' : 'text-red-500'">{{ check.pass ? '✓' : '✗' }}</span>
                        <div>
                            <span class="text-neutral-700 dark:text-neutral-300">{{ checkLabels[key] ?? key }}</span>
                            <span v-if="!check.pass" class="block text-xs text-neutral-500">{{ check.detail }}</span>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">AI crawlers in robots.txt</h2>
                <p class="mt-1 text-xs text-neutral-500">
                    Answer crawlers decide whether your pages can be cited. Training crawlers only decide
                    whether your pages train future models; blocking them is a choice.
                </p>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-neutral-500">AI search and answers</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="crawler in props.check.crawlers.answer ?? []" :key="crawler.bot" class="flex items-center justify-between">
                        <span class="text-neutral-700 dark:text-neutral-300">{{ crawler.bot }} <span class="text-xs text-neutral-400">{{ crawler.description }}</span></span>
                        <span :class="crawler.blocked ? 'text-red-500' : 'text-green-600'" class="text-xs font-semibold">
                            {{ crawler.blocked ? 'Blocked' : 'Allowed' }}
                        </span>
                    </li>
                </ul>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-neutral-500">Model training</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="crawler in props.check.crawlers.training ?? []" :key="crawler.bot" class="flex items-center justify-between">
                        <span class="text-neutral-700 dark:text-neutral-300">{{ crawler.bot }} <span class="text-xs text-neutral-400">{{ crawler.description }}</span></span>
                        <span :class="crawler.blocked ? 'text-amber-500' : 'text-green-600'" class="text-xs font-semibold">
                            {{ crawler.blocked ? 'Blocked' : 'Allowed' }}
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="props.check.llmsTxt" class="mt-6 rounded-xl border border-neutral-200 p-5 dark:border-neutral-800">
                <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">llms.txt</h2>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-neutral-900 p-4 text-xs text-neutral-300"><code>{{ props.check.llmsTxt }}</code></pre>
            </section>
        </div>
    </PanelLayout>
</template>
