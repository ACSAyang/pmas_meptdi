@php use App\Enums\AccountabilityCategory as Cat; @endphp
{{-- expects: $summary (array), $byUnit (iterable of arrays), $rows (iterable of arrays with category) --}}
<div class="report-section">A. Summary</div>
<table class="report-table">
    <thead><tr><th style="text-align:left">Category</th><th>Number</th><th>% of assigned</th></tr></thead>
    <tbody>
        <tr><td><b>ASSIGNED STRENGTH</b></td><td class="num"><b>{{ $summary['assigned'] }}</b></td><td class="num">100%</td></tr>
        @foreach(Cat::cases() as $c)
            <tr><td style="padding-left:18px">{{ $c->label() }}</td><td class="num">{{ $summary['counts'][$c->value] ?? 0 }}</td><td class="num">{{ $summary['assigned'] ? round(($summary['counts'][$c->value] ?? 0) / $summary['assigned'] * 100, 1) : 0 }}%</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td>Accounted for (present + authorized away)</td><td class="num">{{ $summary['accounted'] }}</td><td class="num">{{ $summary['assigned'] ? round($summary['accounted'] / $summary['assigned'] * 100, 1) : 0 }}%</td></tr>
        <tr><td>Unresolved (overdue + unverified + unaccounted)</td><td class="num">{{ $summary['unresolved'] }}</td><td class="num">{{ $summary['assigned'] ? round($summary['unresolved'] / $summary['assigned'] * 100, 1) : 0 }}%</td></tr>
    </tfoot>
</table>
<div class="small muted mt-1">Reconciliation: {{ $summary['present'] }} present + {{ $summary['authorized_away'] }} authorized away + {{ $summary['unresolved'] }} unresolved = {{ $summary['present'] + $summary['authorized_away'] + $summary['unresolved'] }} (assigned {{ $summary['assigned'] }}). Unresolved personnel are not included in present strength.</div>

<div class="report-section">B. Breakdown by unit / section</div>
<div class="table-wrap"><table class="report-table">
    <thead><tr><th style="text-align:left">Unit / section</th><th>Assigned</th>@foreach(Cat::cases() as $c)<th>{{ $c->shortLabel() }}</th>@endforeach</tr></thead>
    <tbody>@foreach($byUnit as $u)
        <tr><td>{{ $u['name'] }}</td><td class="num"><b>{{ $u['assigned'] }}</b></td>@foreach(Cat::cases() as $c)<td class="num">{{ $u['counts'][$c->value] ?: '–' }}</td>@endforeach</tr>
    @endforeach</tbody>
    <tfoot><tr><td>TOTAL</td><td class="num">{{ $summary['assigned'] }}</td>@foreach(Cat::cases() as $c)<td class="num">{{ $summary['counts'][$c->value] ?? 0 }}</td>@endforeach</tr></tfoot>
</table></div>

@php $away = collect($rows)->filter(fn ($r) => in_array($r['category'], ['leave', 'pass', 'sick', 'training', 'official'])); $exc = collect($rows)->filter(fn ($r) => in_array($r['category'], ['overdue', 'unverified', 'unaccounted'])); @endphp
<div class="report-section">C. Authorized absences ({{ $away->count() }})</div>
@if($away->isEmpty())<div class="small muted">None.</div>@else
<table class="report-table">
    <thead><tr><th>#</th><th style="text-align:left">Rank / name</th><th>Service no.</th><th>Unit</th><th>Category</th><th style="text-align:left">Details</th></tr></thead>
    <tbody>@foreach($away->values() as $i => $r)<tr><td class="num">{{ $i + 1 }}</td><td>{{ $r['rank'] }} {{ $r['name'] }}</td><td class="num">{{ $r['service_number'] }}</td><td class="num">{{ $r['unit_code'] }}</td><td>{{ $r['category_label'] }}</td><td>{{ $r['detail'] }}</td></tr>@endforeach</tbody>
</table>@endif

<div class="report-section">D. Exceptions — overdue, unverified, unaccounted ({{ $exc->count() }})</div>
@if($exc->isEmpty())<div class="small muted">None. All assigned personnel are accounted for.</div>@else
<table class="report-table">
    <thead><tr><th>#</th><th style="text-align:left">Rank / name</th><th>Service no.</th><th>Unit</th><th>Status</th><th style="text-align:left">Basis</th></tr></thead>
    <tbody>@foreach($exc->values() as $i => $r)<tr><td class="num">{{ $i + 1 }}</td><td>{{ $r['rank'] }} {{ $r['name'] }}</td><td class="num">{{ $r['service_number'] }}</td><td class="num">{{ $r['unit_code'] }}</td><td><b>{{ $r['category_label'] }}</b></td><td>{{ $r['detail'] }}</td></tr>@endforeach</tbody>
</table>@endif
