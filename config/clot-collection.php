<?php

use App\Modules\Collection\Collectors\FakeCollector;

/**
 * Answer engine registry.
 *
 * Each engine maps to one collector class. Keys entered by the operator live
 * in .env (or encrypted settings written by the installer/admin panel) and are
 * read by the collector classes themselves — never stored here.
 *
 * The FakeCollector is the default for every engine so a fresh install (and CI)
 * runs end-to-end with zero provider cost. The admin panel switches sources to
 * real collectors; no code change required.
 */
return [
    'engines' => [
        'chatgpt' => [
            'label' => 'ChatGPT',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'gemini' => [
            'label' => 'Gemini',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'google-ai-overviews' => [
            'label' => 'Google AI Overviews',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'google-ai-mode' => [
            'label' => 'Google AI Mode',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'claude' => [
            'label' => 'Claude',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'perplexity' => [
            'label' => 'Perplexity',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'grok' => [
            'label' => 'Grok',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'class' => FakeCollector::class,
            'config' => [],
        ],
        'le-chat' => [
            'label' => 'Le Chat (Mistral)',
            'class' => FakeCollector::class,
            'config' => [],
        ],
    ],
];
