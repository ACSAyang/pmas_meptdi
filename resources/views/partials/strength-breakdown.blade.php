@php
    use App\Enums\AccountabilityCategory as Cat;
    $counts = $snapshot->counts();
    $assigned = max(1, $snapshot->assigned());
    $linkParams = $linkParams ?? [];
@endphp
<div class="strength-bar" role="img" aria-label="Accountability breakdown">
    @foreach(Cat::cases() as $c)
        @if($counts[$c->value] > 0)
            <span class="fill-{{ $c->tone() }}" style="width: {{ $counts[$c->value] / $assigned * 100 }}%" title="{{ $c->label() }}: {{ $counts[$c->value] }}"></span>
        @endif
    @endforeach
</div>
<div class="legend">
    @foreach(Cat::cases() as $c)
        <div class="legend-item">
            <span class="sw fill-{{ $c->tone() }}"></span>
            @if(auth()->user()->hasPermission('accountability.view'))
                <a href="{{ route('accountability.index', $linkParams + ['category' => $c->value]) }}">{{ $c->label() }}</a>
            @else
                <span>{{ $c->label() }}</span>
            @endif
            <span class="n">{{ $counts[$c->value] }}</span>
        </div>
    @endforeach
</div>
<div class="reconcile">
    <span>Assigned <b>{{ $snapshot->assigned() }}</b></span><span class="eq">=</span>
    <span class="badge tone-green">Present <b>{{ $snapshot->present() }}</b></span><span class="eq">+</span>
    <span class="badge tone-blue">Authorized away <b>{{ $snapshot->authorizedAway() }}</b></span><span class="eq">+</span>
    <span class="badge {{ $snapshot->unresolved() ? 'tone-red' : 'tone-gray' }}">Unresolved <b>{{ $snapshot->unresolved() }}</b></span>
    <span class="xs muted" style="margin-left:auto">@if($snapshot->reconciles())<x-icon name="check" size="sm"/> Reconciled — each member counted once @else <span style="color:var(--red)">Totals do not reconcile</span>@endif</span>
</div>
