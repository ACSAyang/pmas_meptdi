@props(['category'])
<x-badge :tone="$category->tone()" dot>{{ $category->shortLabel() }}</x-badge>
