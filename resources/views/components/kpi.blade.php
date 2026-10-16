@props(['label', 'value', 'meta' => null, 'tone' => 'navy', 'href' => null, 'icon' => null, 'alert' => false])
<div {{ $attributes->merge(['class' => 'kpi tone-'.$tone.($alert ? ' kpi-alert' : '')]) }}>
    <div class="label">@if($icon)<x-icon :name="$icon" size="sm" />@endif{{ $label }}</div>
    <div class="value">{{ $value }}</div>
    @if($meta)<div class="meta">{!! $meta !!}</div>@endif
    @if($href)<a class="stretched" href="{{ $href }}" aria-label="{{ $label }}"></a>@endif
</div>
