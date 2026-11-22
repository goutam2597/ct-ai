<?php

namespace App\Modules\Ops\Http\Controllers\Admin;

use App\Modules\Collection\DTO\CollectorResponse;
use App\Modules\Collection\Services\CollectorManager;
use App\Modules\Ops\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Answer engines screen v1: the registry with each engine's configured
 * source, and the one-click test call (billed like any other answer).
 * Key entry per collector lands with the provider-sprint admin forms.
 */
final class EnginesController extends Controller
{
    public function index(CollectorManager $manager): Response
    {
        $engines = [];

        foreach ($manager->engineKeys() as $key) {
            $engines[] = [
                'key' => $key,
                'label' => config("clot-collection.engines.$key.label", $key),
                'collector' => class_basename(config("clot-collection.engines.$key.class")),
            ];
        }

        return Inertia::render('Admin/Engines', ['engines' => $engines]);
    }

    public function test(string $engine, CollectorManager $manager): RedirectResponse
    {
        /** @var CollectorResponse $response */
        $response = $manager->has($engine)
            ? $manager->for($engine)->test()
            : CollectorResponse::failed('Unknown engine.');

        if ($response->isCollected()) {
            return back()->with('success', "Test OK on {$engine}: ".Str::limit((string) $response->answerText, 80));
        }

        return back()->withErrors(['test' => 'Test failed on '.$engine.': '.$response->failureReason]);
    }
}
