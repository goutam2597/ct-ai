<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Models\User;
use App\Modules\Tenancy\Models\Workspace;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $workspaces = $user->memberships()
            ->with('workspace:id,name,slug,owner_id')
            ->orderBy('id')
            ->get()
            ->map(fn ($membership) => [
                'id' => $membership->workspace_id,
                'name' => $membership->workspace?->name,
                'slug' => $membership->workspace?->slug,
                'role' => $membership->role->value,
                'isCurrent' => $membership->workspace_id === WorkspaceContext::currentId(),
            ]);

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
            'hasCurrent' => WorkspaceContext::currentId() !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $workspace = Workspace::createWithOwner(Auth::id() ?? 0, $validated['name']);

        return redirect()
            ->route('workspaces.index')
            ->with('success', 'Workspace created.');
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('view', $workspace);

        /** @var User $user */
        $user = Auth::user();

        Session::put('active_workspace.'.$user->id, $workspace->id);

        app(WorkspaceContext::class)->resolveFor($user);

        return redirect()->route('workspaces.index');
    }
}
