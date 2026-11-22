<?php

namespace App\Http\Middleware;

use App\Modules\Analytics\Models\AlertEvent;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $context = WorkspaceContext::current();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()
                    ?->only(['id', 'name', 'email']),
                'workspace' => function () use ($context): ?array {
                    $workspace = $context?->workspace();

                    if ($workspace === null) {
                        return null;
                    }

                    return [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'role' => $context->role()?->value,
                    ];
                },
            ],
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
            ],
            'csrf_token' => fn () => csrf_token(),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'panel' => function () use ($context): array {
                $workspace = $context?->workspace();

                if ($workspace === null) {
                    return ['brands' => [], 'alerts' => []];
                }

                return [
                    'brands' => $workspace->brands()->withoutGlobalScopes()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->all(),
                    'alerts' => AlertEvent::query()
                        ->latest('id')
                        ->limit(5)
                        ->get(['id', 'rule', 'title', 'created_at'])
                        ->all(),
                ];
            },
        ];
    }
}
