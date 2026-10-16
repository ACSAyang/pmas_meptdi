<div class="card">
    <div class="card-header"><h3><x-icon name="user"/> My current status</h3><span class="hint">as of {{ now()->format('d M H:i') }}</span></div>
    <div class="card-body">
        @if($myStatus ?? null)
            <div class="row" style="gap:14px">
                <div class="avatar lg">{{ $user->personnel->initials }}</div>
                <div>
                    <div class="strong" style="font-size:16px">{{ $user->personnel->display_name }}</div>
                    <div class="small muted">{{ $user->personnel->service_number }} · {{ $myStatus->unit?->name }}</div>
                    <div class="mt-1"><x-category-badge :category="$myStatus->category"/></div>
                </div>
            </div>
            <div class="small muted mt-2">{{ $myStatus->detail }}</div>
        @else
            <div class="muted small">No active assignment found for your record.</div>
        @endif
        @if($myMovement ?? null)
            <div class="alert alert-info mt-3 mb-0"><x-icon name="move"/>
                <div><strong>{{ $myMovement->status->label() }}</strong><br>
                <span class="small">{{ $myMovement->sourceLabel() }}: {{ $myMovement->expected_departure_at->format('d M Y H:i') }} → {{ $myMovement->expected_return_at->format('d M Y H:i') }}.
                @if($myMovement->status->value === 'scheduled') Report to the Duty Office Watch upon departure. @else Report to the Duty Office Watch upon return. @endif</span></div>
            </div>
        @endif
    </div>
</div>
