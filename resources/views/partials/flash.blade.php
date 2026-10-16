@foreach (['success' => 'check-circle', 'warning' => 'alert', 'info' => 'info'] as $type => $icon)
    @if (session($type))
        <div class="alert alert-{{ $type }}" role="status" @if($type === 'success') data-autodismiss @endif>
            <x-icon :name="$icon" /><div>{{ session($type) }}</div>
            <button type="button" class="close" aria-label="Dismiss"><x-icon name="x" size="sm"/></button>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <x-icon name="x-circle" />
        <div><strong>Please review the following:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        <button type="button" class="close" aria-label="Dismiss"><x-icon name="x" size="sm"/></button>
    </div>
@endif
