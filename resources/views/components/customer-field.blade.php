@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'autocomplete' => null, 'hint' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-neutral-300">
        {{ $label }}@if ($required)<span class="text-amber-400" aria-hidden="true">*</span>@endif
    </label>

    <input id="{{ $name }}"
           name="{{ $name }}"
           type="{{ $type }}"
           value="{{ old($name, $value) }}"
           @if ($required) required @endif
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
           class="mt-1.5 block w-full rounded-lg border border-white/15 bg-neutral-900 px-3 py-2.5
                  text-sm text-white placeholder-neutral-500 transition
                  focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">

    @if ($hint)
        <p class="mt-1.5 text-xs text-neutral-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
    @enderror
</div>
