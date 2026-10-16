@props(['tone' => 'gray', 'dot' => false])
<span {{ $attributes->merge(['class' => 'badge tone-'.$tone]) }}>@if($dot)<span class="bdot"></span>@endif{{ $slot }}</span>
