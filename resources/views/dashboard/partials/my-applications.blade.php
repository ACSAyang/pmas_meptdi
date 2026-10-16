@forelse(($myApplications ?? collect()) as $a)
    @if($loop->first)<ul class="list">@endif
    <li>
        <div class="grow"><div class="t"><a href="{{ route('applications.show', $a) }}">{{ $a->reference_number ?? 'Draft' }}</a> · {{ $a->type->name }}</div>
            <div class="s">{{ $a->start_at->format('d M Y H:i') }} → {{ $a->end_at->format('d M Y H:i') }}@if($a->currentStep) · at {{ $a->currentStep->name }}@endif</div></div>
        <x-badge :tone="$a->status->tone()">{{ $a->status->label() }}</x-badge>
    </li>
    @if($loop->last)</ul>@endif
@empty
    <x-empty icon="file" title="No applications yet" text="Your leave, pass and other applications will appear here.">
        @if(auth()->user()->hasPermission('applications.create'))<a href="{{ route('applications.create') }}" class="btn btn-primary btn-sm mt-2"><x-icon name="plus" size="sm"/> New application</a>@endif
    </x-empty>
@endforelse
