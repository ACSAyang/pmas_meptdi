@extends('layouts.app')
@section('title', 'Approval Queue')
@section('crumb', 'Applications')
@section('content')
<div class="page-head"><div><h2>Approval queue</h2><div class="sub">Applications waiting at a stage assigned to your role. Your own applications never appear here.</div></div></div>
<div class="card">
    @if($applications->isEmpty())
        <x-empty icon="check-circle" title="Nothing awaiting your action" text="New submissions routed to your stage will appear here."/>
    @else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reference</th><th>Member</th><th>Type</th><th>Period</th><th>Stage</th><th>Documents</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
        @foreach($applications as $a)
            @php $missing = $a->unverifiedRequiredDocuments(); @endphp
            <tr data-href="{{ route('applications.show', $a) }}">
                <td><span class="ref">{{ $a->reference_number }}</span></td>
                <td><x-person :p="$a->personnel" :link="false"/></td>
                <td>{{ $a->type->name }}</td>
                <td class="small nowrap">{{ $a->start_at->format('d M H:i') }} → {{ $a->end_at->format('d M H:i') }}<div class="xs muted">{{ $a->durationLabel() }}@if($a->start_at->diffInDays(now()) < 2 && $a->start_at->isFuture()) · <span style="color:var(--orange)">starts soon</span>@endif</div></td>
                <td><x-badge tone="amber">{{ $a->currentStep?->name }}</x-badge>@if($a->is_late || $a->is_retrospective) <x-badge tone="orange" class="sm">Late</x-badge>@endif</td>
                <td>@if($missing)<x-badge tone="rose" class="sm">{{ count($missing) }} not verified</x-badge>@else<x-badge tone="green" class="sm">Complete</x-badge>@endif</td>
                <td class="xs muted nowrap">{{ $a->submitted_at?->diffForHumans() }}</td>
                <td><a class="btn btn-primary btn-sm" href="{{ route('applications.show', $a) }}">Review</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $applications->links() }}
    @endif
</div>
@endsection
