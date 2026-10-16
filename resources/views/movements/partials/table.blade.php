@php
    $showPerson = $showPerson ?? true;
    $actions = $actions ?? false;
    $u = auth()->user();
    $canMed = $u->hasPermission('medical.view');
@endphp
@if($movements->isEmpty())
    <x-empty icon="move" title="No movements" text="{{ $emptyText ?? 'Nothing to show for this view.' }}"/>
@else
<div class="table-wrap"><table class="table">
    <thead><tr>@if($showPerson)<th>Member</th>@endif<th>Category / authority</th><th>Departure</th><th>Return</th><th>Status</th>@if($actions)<th class="right">Action</th>@endif</tr></thead>
    <tbody>
    @foreach($movements as $m)
        @php $overdue = $m->isOverdue(); $restricted = $m->application?->type?->is_sensitive && !$canMed; @endphp
        <tr @if($u->hasPermission('movements.view')) data-href="{{ route('movements.show', $m) }}" @endif>
            @if($showPerson)<td><x-person :p="$m->personnel" :link="false"/></td>@endif
            <td><x-category-badge :category="$m->category"/><div class="xs muted mt-1">{{ $restricted ? 'Restricted' : $m->sourceLabel() }} @if($m->sourceReference())· <span class="mono">{{ $m->sourceReference() }}</span>@endif</div></td>
            <td class="small nowrap tabular">
                <div>Plan: {{ $m->expected_departure_at->format('d M H:i') }}</div>
                <div class="{{ $m->actual_departure_at ? 'strong' : 'muted' }}">Actual: {{ $m->actual_departure_at?->format('d M H:i') ?? '—' }}</div>
            </td>
            <td class="small nowrap tabular">
                <div>Plan: {{ $m->expected_return_at->format('d M H:i') }}</div>
                <div class="{{ $m->actual_return_at ? 'strong' : 'muted' }}">Actual: {{ $m->actual_return_at?->format('d M H:i') ?? '—' }}</div>
            </td>
            <td>
                @if($overdue)<x-badge tone="red" dot>Overdue {{ $m->expected_return_at->diffForHumans(null, true) }}</x-badge>
                @else<x-badge :tone="$m->status->tone()">{{ $m->status->shortLabel() }}</x-badge>@endif
                @if($m->status->value === 'scheduled' && $m->expected_departure_at->isPast())<div class="xs" style="color:var(--amber)">Departure time passed</div>@endif
                @if($m->wasReturnedLate())<div class="xs" style="color:var(--orange)">Returned late</div>@endif
            </td>
            @if($actions)
            <td class="right nowrap">
                @if($u->hasPermission('movements.record'))
                    @if($m->status->value === 'scheduled')
                        <button class="btn btn-primary btn-sm" data-dialog-open="depart-dialog" data-action="{{ route('movements.depart', $m) }}" data-fill-who="{{ $m->personnel->rank_name }}"><x-icon name="depart" size="sm"/> Depart</button>
                    @elseif($m->status->value === 'departed')
                        <button class="btn btn-success btn-sm" data-dialog-open="return-dialog" data-action="{{ route('movements.return', $m) }}" data-fill-who="{{ $m->personnel->rank_name }}"><x-icon name="arrive" size="sm"/> Return</button>
                    @endif
                @endif
            </td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table></div>
{{ $movements->links() }}
@endif
