@props(['label' => null, 'name' => null, 'required' => false, 'help' => null, 'class' => ''])
<div class="field {{ $class }}">
    @if($label)<label for="{{ $attributes->get('for', $name) }}">{{ $label }}@if($required)<span class="req">*</span>@endif</label>@endif
    {{ $slot }}
    @if($help)<div class="help">{{ $help }}</div>@endif
    @if($name)@error($name)<div class="error">{{ $message }}</div>@enderror @endif
</div>
