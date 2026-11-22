<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import BrandTabs from './BrandTabs.vue';

const page = usePage<{
    app: { name: string };
    brand: { id: number; name: string };
    opportunities: Array<{
        rule: string;
        score: number;
        title: string;
        evidence: Record<string, unknown>;
    }>;
}>();

const props = page.props;

const ruleLabel = (rule: string): string =>
    ({
        rival_wins_question: 'A rival is named more often',
        source_favours_rivals: 'A site cites rivals, not you',
        weak_engine: 'An engine trails your overall rate',
    })[rule] ?? rule;

const severityColor = (score: number): string =>
    score >= 80 ? 'text-red-600 bg-red-50 dark:bg-red-950 dark:text-red-400'
        : score >= 55 ? 'text-amber-600 bg-amber-50 dark:bg-amber-950 dark:text-amber-400'
        : 'text-blue-600 bg-blue-50 dark:bg-blue-950 dark:text-blue-400';
</script>

<template>
    <Head :title="`Opportunities — ${props.brand.name}`" />

    <PanelLayout :title="`Opportunities — ${props.brand.name}`">
        <BrandTabs :brand="props.brand" active="opportunities" />
        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-50">
                Next steps · {{ props.brand.name }}
            </h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                A ranked to-do list built from the answers. Each step shows the numbers that put it there.
            </p>

            <ul class="mt-8 space-y-4">
                <li
                    v-for="(opportunity, index) in props.opportunities"
                    :key="index"
                    class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-800"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                    :class="severityColor(opportunity.score)"
                                >
                                    {{ opportunity.score }}
                                </span>
                                <span class="text-xs uppercase tracking-wide text-neutral-400">
                                    {{ ruleLabel(opportunity.rule) }}
                                </span>
                            </div>
                            <p class="mt-2 font-medium text-neutral-900 dark:text-neutral-100">
                                {{ opportunity.title }}
                            </p>
                            <div class="mt-2 text-xs text-neutral-500">
                                <template v-if="opportunity.evidence.rival_rate_pct !== undefined">
                                    Rival {{ opportunity.evidence.rival }}: {{ opportunity.evidence.rival_rate_pct }}%
                                    · you: {{ opportunity.evidence.your_rate_pct }}% ·
                                    {{ opportunity.evidence.answers }} answers
                                </template>
                                <template v-else-if="opportunity.evidence.citations !== undefined">
                                    {{ opportunity.evidence.citations }} citations
                                </template>
                                <template v-else-if="opportunity.evidence.engine_rate !== undefined">
                                    Engine: {{ Math.round(opportunity.evidence.engine_rate * 100) }}% ·
                                    overall: {{ Math.round(opportunity.evidence.overall_rate * 100) }}%
                                </template>
                            </div>
                        </div>
                    </div>
                </li>
                <li v-if="!props.opportunities.length" class="rounded-xl border border-neutral-200 px-4 py-8 text-center text-sm text-neutral-500 dark:border-neutral-800">
                    No opportunities found — the answers look balanced right now.
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
