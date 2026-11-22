<?php

namespace App\Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\ShareLink;
use App\Modules\Collection\Services\Report\ReportDataBuilder;
use Illuminate\Http\Response;

/**
 * Public read-only report by token. No login; never indexed; revoked links
 * 404. Token is the only credential (gate: client-friendly sharing).
 */
final class SharedReportController extends Controller
{
    public function show(string $token): Response
    {
        $link = ShareLink::query()
            ->where('token', $token)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $brand = Brand::query()->withoutGlobalScopes()->findOrFail($link->brand_id);

        $data = app(ReportDataBuilder::class)->build($brand);

        return response()
            ->view('reports.share', $data)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
