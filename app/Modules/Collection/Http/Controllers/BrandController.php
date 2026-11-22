<?php

namespace App\Modules\Collection\Http\Controllers;

use App\Modules\Analytics\Services\WilsonInterval;
use App\Modules\Collection\Jobs\RunSweepJob;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Services\Budget\AllowanceService;
use App\Modules\Collection\Services\Budget\CostGovernor;
use App\Modules\Tenancy\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function show(Brand $brand, AllowanceService $allowance, CostGovernor $governor): Response
    {
        Gate::authorize('view', $brand);

        $since = now()->subDays(30);

        $answers = Answer::query()
            ->where('brand_id', $brand->id)
            ->where('created_at', '>=', $since);

        $collected = (clone $answers)->where('status', 'collected');
        $totalCollected = (clone $collected)->count();
        $totalFailed = (clone $answers)->where('status', 'failed')->count();

        $namedYou = (clone $collected)->where('named_you', true)->count();
        $citedYou = (clone $collected)->where('cited_you', true)->count();

        $mention = WilsonInterval::compute($namedYou, $totalCollected);
        $citation = WilsonInterval::compute($citedYou, $totalCollected);

        // Share of voice: your mentions ÷ all tracked-brand mentions.
        $allMentions = AnswerMention::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->count();
        $yourMentions = AnswerMention::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->where('is_you', true)
            ->count();
        $shareOfVoice = $allMentions > 0 ? round($yourMentions / $allMentions * 100) : null;

        $engines = [];

        foreach ((array) $brand->engines as $engine) {
            $engineCollected = (clone $collected)->where('engine', $engine)->count();

            if ($engineCollected === 0) {
                $engines[] = ['engine' => $engine, 'rate' => null, 'low' => null, 'high' => null, 'answers' => 0];

                continue;
            }

            $engineNamed = (clone $collected)->where('engine', $engine)->where('named_you', true)->count();
            $range = WilsonInterval::compute($engineNamed, $engineCollected);

            $engines[] = [
                'engine' => $engine,
                'rate' => $range['point'],
                'low' => $range['low'],
                'high' => $range['high'],
                'answers' => $engineCollected,
            ];
        }

        // Daily series across all engines.
        $daily = (clone $collected)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as answers, SUM(CASE WHEN named_you = 1 THEN 1 ELSE 0 END) as named')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => (string) $row->day,
                'answers' => (int) $row->answers,
                'rate' => (int) $row->answers > 0 ? round($row->named / $row->answers * 100) : null,
            ]);

        $costThisMonth = (float) $governor->spentToday(now()->startOfMonth());

        return Inertia::render('Brands/Show', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'engines' => $brand->engines,
                'runsPerPrompt' => $brand->runs_per_prompt,
                'nextSweepAt' => $brand->next_sweep_at?->toISOString(),
            ],
            'metrics' => [
                'mentionRate' => $mention,
                'citationRate' => $citation,
                'shareOfVoice' => $shareOfVoice,
                'answersCollected' => $totalCollected,
                'answersFailed' => $totalFailed,
                'lowConfidence' => $totalCollected < 30,
            ],
            'engines' => $engines,
            'daily' => $daily,
            'allowance' => [
                'used' => $allowance->used(),
                'total' => $allowance->allowance(),
            ],
            'cost' => [
                'monthUsd' => round($costThisMonth, 2),
                'capUsd' => $governor->dailyCapUsd(),
            ],
        ]);
    }

    public function answers(Request $request, Brand $brand): Response
    {
        Gate::authorize('view', $brand);

        $filters = [
            'engine' => (string) $request->string('engine'),
            'status' => $request->input('status') === 'failed' ? 'failed' : ($request->input('status') === 'collected' ? 'collected' : ''),
            'named' => $request->input('named') === '1' ? '1' : ($request->input('named') === '0' ? '0' : ''),
        ];

        $query = Answer::query()
            ->where('brand_id', $brand->id)
            ->with('prompt:id,text')
            ->orderByDesc('id')
            ->limit(200);

        if ($filters['engine'] !== '') {
            $query->where('engine', $filters['engine']);
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['named'] !== '') {
            $query->where('named_you', (bool) $filters['named']);
        }

        $rows = $query->get()->map(fn (Answer $answer) => [
            'id' => $answer->id,
            'status' => $answer->status->value,
            'engine' => $answer->engine,
            'method' => $answer->method,
            'model' => $answer->model,
            'named' => (bool) $answer->named_you,
            'cited' => (bool) $answer->cited_you,
            'text' => $answer->answer_text,
            'failureReason' => $answer->failure_reason,
            'costUsd' => $answer->provider_cost_usd,
            'createdAt' => $answer->created_at?->toISOString(),
            'prompt' => $answer->prompt->text ?? '',
        ]);

        return Inertia::render('Brands/Answers', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'answers' => $rows,
            'filters' => $filters,
        ]);
    }

    public function runSweep(Brand $brand): RedirectResponse
    {
        Gate::authorize('editResults', $brand);

        if (Config::string('queue.default') === 'sync') {
            RunSweepJob::dispatchSync($brand->id);
        } else {
            RunSweepJob::dispatch($brand->id);
        }

        return back()->with('success', 'Sweep queued for '.$brand->name.'.');
    }
}
