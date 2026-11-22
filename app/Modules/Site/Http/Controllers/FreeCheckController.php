<?php

namespace App\Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Site\Services\FreeCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class FreeCheckController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Site/FreeCheck');
    }

    public function store(Request $request, FreeCheckService $service): RedirectResponse|Response
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'brand_name' => ['required', 'string', 'max:120'],
            'website' => ['required', 'string', 'max:255'],
            'offering' => ['required', 'string', 'max:120'],
        ]);

        $ipHash = hash('sha256', $request->ip().(string) config('app.key'));

        try {
            $check = $service->run(
                $validated['email'],
                $validated['brand_name'],
                $validated['website'],
                $validated['offering'],
                $ipHash,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['free_check' => $e->getMessage()]);
        }

        return Inertia::render('Site/FreeCheck', [
            'result' => [
                'brand' => $check->brand_name,
                'grade' => $check->grade,
                'mentionRate' => $check->mention_rate,
                'answersCount' => $check->answers_count,
                'namedCount' => $check->named_count,
                'answers' => $check->answers ?? [],
            ],
        ]);
    }
}
