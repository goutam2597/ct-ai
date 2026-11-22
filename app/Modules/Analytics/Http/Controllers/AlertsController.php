<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Models\AlertChannel;
use App\Modules\Analytics\Models\AlertEvent;
use App\Modules\Tenancy\Http\Controllers\Controller;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AlertsController extends Controller
{
    public function index(): Response
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null) {
            abort(403);
        }

        Gate::authorize('view', $workspace);

        $events = AlertEvent::query()->latest('id')->limit(100)->get()
            ->map(fn (AlertEvent $event) => [
                'id' => $event->id,
                'rule' => $event->rule,
                'title' => $event->title,
                'body' => $event->body,
                'createdAt' => $event->created_at?->toISOString(),
            ]);

        $channels = AlertChannel::query()->orderBy('id')->get()
            ->map(fn (AlertChannel $channel) => [
                'id' => $channel->id,
                'type' => $channel->type,
                'target' => $channel->target,
                'isActive' => $channel->is_active,
            ]);

        return Inertia::render('Alerts/Index', ['events' => $events, 'channels' => $channels]);
    }

    public function addChannel(Request $request): RedirectResponse
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null) {
            abort(403);
        }

        Gate::authorize('manageSettings', $workspace);

        $validated = $request->validate([
            'type' => ['required', 'in:webhook,slack'],
            'target' => ['required', 'url', 'max:255'],
        ]);

        $secret = $validated['type'] === 'webhook' ? bin2hex(random_bytes(16)) : null;

        AlertChannel::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'type' => $validated['type'], 'target' => $validated['target']],
            ['secret' => $secret, 'is_active' => true],
        );

        return back()->with('success', 'Alert channel connected.');
    }

    public function removeChannel(AlertChannel $channel): RedirectResponse
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null || $channel->workspace_id !== $workspace->id) {
            abort(403);
        }

        Gate::authorize('manageSettings', $workspace);

        $channel->delete();

        return back()->with('success', 'Channel removed.');
    }
}
