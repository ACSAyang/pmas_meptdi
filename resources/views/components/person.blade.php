@props(['p', 'link' => true, 'sub' => null])
<div class="person">
    <div class="avatar sm">{{ $p->initials }}</div>
    <div style="min-width:0">
        <div class="nm">@if($link)<a href="{{ route('personnel.show', $p) }}">{{ $p->rank?->abbreviation }} {{ $p->full_name }}</a>@else{{ $p->rank?->abbreviation }} {{ $p->full_name }}@endif</div>
        <div class="sm">{{ $sub ?? $p->service_number }}</div>
    </div>
</div>
