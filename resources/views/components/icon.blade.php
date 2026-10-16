@props(['name', 'size' => null])
<svg {{ $attributes->merge(['class' => 'icon'.($size ? ' '.$size : '')]) }} viewBox="0 0 24 24" aria-hidden="true">{!! \App\Support\Icon::PATHS[$name] ?? \App\Support\Icon::PATHS['info'] !!}</svg>
