@php $showPerson = $showPerson ?? true; $canMed = auth()->user()->hasPermission('medical.view'); @endphp
@if($applications->isEmpty())
    <x-empty icon="file" title="No applications found"/>
@else
<div class="table-wrap"><table class="table">
    <thead><tr><th>Reference</th>@if($showPerson)<th>Member</th>@endif<th>Type</th><th>Period</th><th>Status</th><th>Stage / flags</th><th>Updated</th></tr></thead>
    <tbody>
    @foreach($applications as $a)
        <tr data-href="{{ route('applications.show', $a) }}">
            <td><span class="ref">{{ $a->reference_number ?? 'DRAFT' }}</span></td>
            @if($showPerson)<td><x-person :p="$a->personnel" :link="false"/></td>@endif
            <td>{{ $a->type->is_sensitive && !$canMed && $a->personnel_id !== auth()->user()->personnel_id ? 'Restricted' : $a->type->name }}</td>
            <td class="small nowrap tabular">{{ $a->start_at->format('d M Y H:i') }}<div class="xs muted">to {{ $a->end_at->format('d M Y H:i') }} · {{ $a->durationLabel() }}</div></td>
            <td><x-badge :tone="$a->status->tone()" dot>{{ $a->status->label() }}</x-badge></td>
            <td class="small">{{ $a->currentStep?->name ?? '—' }}
                @if($a->is_late)<x-badge tone="orange" class="sm">Late</x-badge>@endif
                @if($a->is_retrospective)<x-badge tone="orange" class="sm">Retrospective</x-badge>@endif</td>
            <td class="xs muted nowrap">{{ $a->updated_at->diffForHumans() }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
{{ $applications->links() }}
@endif
