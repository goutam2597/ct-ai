<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $label['name'] }} — AI visibility report · {{ $brand['name'] }}</title>
    <style>
        body { font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; background: #f4f6f9; margin: 0; color: #1f2937; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 40px 24px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; margin-bottom: 20px; }
        h1 { font-size: 22px; margin: 0; }
        h2 { font-size: 15px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
        .meta { color: #6b7280; font-size: 13px; }
        .cards { display: flex; gap: 12px; flex-wrap: wrap; }
        .card-s { flex: 1; min-width: 140px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
        .big { font-size: 26px; font-weight: 700; }
        .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 8px 6px; }
        td { padding: 8px 6px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .warn { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin: 14px 0; }
        .footer { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ $label['name'] }} — AI visibility report</h1>
            <p class="meta">{{ $brand['name'] }} · last {{ $periodDays }} days · read-only share</p>

            @if($metrics['lowConfidence'])
                <div class="warn">Low confidence: fewer than 30 answers in this period. Rates shown but not used for trends.</div>
            @endif

            <div class="cards">
                <div class="card-s">
                    <div class="label">Mention rate</div>
                    <div class="big">{{ $metrics['mentionRate']['point'] === null ? '—' : round($metrics['mentionRate']['point'] * 100).'%' }}</div>
                    <div class="meta">range {{ $metrics['mentionRate']['low'] === null ? '—' : round($metrics['mentionRate']['low'] * 100).'%' }}–{{ $metrics['mentionRate']['high'] === null ? '—' : round($metrics['mentionRate']['high'] * 100).'%' }}</div>
                </div>
                <div class="card-s">
                    <div class="label">Share of voice</div>
                    <div class="big">{{ $metrics['shareOfVoice'] === null ? '—' : $metrics['shareOfVoice'].'%' }}</div>
                </div>
                <div class="card-s">
                    <div class="label">Citation rate</div>
                    <div class="big">{{ $metrics['citationRate']['point'] === null ? '—' : round($metrics['citationRate']['point'] * 100).'%' }}</div>
                    <div class="meta">range {{ $metrics['citationRate']['low'] === null ? '—' : round($metrics['citationRate']['low'] * 100).'%' }}–{{ $metrics['citationRate']['high'] === null ? '—' : round($metrics['citationRate']['high'] * 100).'%' }}</div>
                </div>
                <div class="card-s">
                    <div class="label">Answers</div>
                    <div class="big">{{ $metrics['answersCollected'] }}</div>
                </div>
            </div>
        </div>

        @if(count($engines))
            <div class="card">
                <h2>By engine</h2>
                <table>
                    <tr><th>Engine</th><th>Rate</th><th>Range</th><th>Answers</th></tr>
                    @foreach($engines as $engine)
                        <tr>
                            <td>{{ $engine['engine'] }}</td>
                            <td>{{ $engine['rate'] === null ? '—' : round($engine['rate'] * 100).'%' }}</td>
                            <td>{{ $engine['low'] === null ? '—' : round($engine['low'] * 100).'%' }}–{{ $engine['high'] === null ? '—' : round($engine['high'] * 100).'%' }}</td>
                            <td>{{ $engine['answers'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if(count($competitors))
            <div class="card">
                <h2>Competitors</h2>
                <table>
                    <tr><th>Brand</th><th>Named in</th></tr>
                    @foreach($competitors as $competitor)
                        <tr><td>{{ $competitor['name'] }}</td><td>{{ $competitor['mentionRate'] }}%</td></tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if(count($sources))
            <div class="card">
                <h2>Sources the engines cite</h2>
                <table>
                    <tr><th>Domain</th><th>Owner</th><th>Citations</th></tr>
                    @foreach($sources as $source)
                        <tr><td>{{ $source['domain'] }}</td><td>{{ $source['ownerType'] }}</td><td>{{ $source['citations'] }}</td></tr>
                    @endforeach
                </table>
            </div>
        @endif

        <p class="footer">
            Every rate carries its 95% Wilson interval — a change is real only when the ranges separate.
            {{ $label['whiteLabel'] ? 'Prepared by '.$label['name'].' via ' : 'Generated by ' }}{{ config('app.name') }}.
        </p>
    </div>
</body>
</html>
