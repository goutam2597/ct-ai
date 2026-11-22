<?php

namespace App\Modules\Collection\Http\Controllers;

use App\Models\User;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\ShareLink;
use App\Modules\Collection\Services\Report\ReportDataBuilder;
use App\Modules\Tenancy\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ReportController extends Controller
{
    /** Client-ready PDF: renders server-side via dompdf, white-label aware. */
    public function pdf(Brand $brand, ReportDataBuilder $builder): \Illuminate\Http\Response
    {
        Gate::authorize('view', $brand);

        $data = $builder->build($brand);

        $pdf = Pdf::loadView('reports.brand-pdf', $data)
            ->setPaper('a4');

        return $pdf->download('report-'.$brand->id.'-'.now()->toDateString().'.pdf');
    }

    /** Create a read-only share link (owner/admin). */
    public function createShareLink(Brand $brand): RedirectResponse
    {
        Gate::authorize('editResults', $brand);

        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        ShareLink::query()->create([
            'workspace_id' => $brand->workspace_id,
            'brand_id' => $brand->id,
            'token' => Str::random(48),
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Share link created — anyone with the link can read the report.');
    }

    /** Revoke a share link. */
    public function revokeShareLink(Brand $brand, ShareLink $shareLink): RedirectResponse
    {
        Gate::authorize('editResults', $brand);

        if ($shareLink->brand_id !== $brand->id) {
            abort(404);
        }

        $shareLink->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', 'Share link revoked.');
    }

    /** List share links for a brand. */
    public function shareLinks(Brand $brand): Response
    {
        Gate::authorize('view', $brand);

        return Inertia::render('Brands/ShareLinks', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'links' => $brand->shareLinks()->orderByDesc('id')->limit(50)->get()->map(fn (ShareLink $link) => [
                'id' => $link->id,
                'url' => url('/reports/'.$link->token),
                'revoked' => $link->isRevoked(),
                'createdAt' => $link->created_at?->toISOString(),
            ]),
        ]);
    }
}
