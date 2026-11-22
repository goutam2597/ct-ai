<x-mail::message>
# Weekly AI visibility digest — {{ $workspaceName }}

@forelse($rows as $row)
## {{ $row['name'] }}

- **Mention rate:** {{ $row['mentionRate'] === null ? '—' : round($row['mentionRate'] * 100).'%' }}
  (95% range {{ $row['low'] === null ? '—' : round($row['low'] * 100).'%' }} – {{ $row['high'] === null ? '—' : round($row['high'] * 100).'%' }})
- **Trend:** {{ $row['trend'] }}
- **Share of voice:** {{ $row['shareOfVoice'] === null ? '—' : $row['shareOfVoice'].'%' }}
- **Answers this week:** {{ $row['answers'] }}
@empty
No tracked brands with answers this week.
@endforelse

Changes are called only when the 95% ranges of the two weeks do not overlap.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
