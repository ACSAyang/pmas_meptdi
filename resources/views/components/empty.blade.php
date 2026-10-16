@props(['icon' => 'inbox', 'title' => 'Nothing to show', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    <div class="icon-wrap"><x-icon :name="$icon" size="lg" /></div>
    <h4>{{ $title }}</h4>
    @if($text)<div class="small">{{ $text }}</div>@endif
    {{ $slot }}
</div>
