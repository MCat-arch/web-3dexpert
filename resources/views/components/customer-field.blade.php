@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'readonly' => false, 'autocomplete' => null, 'hint' => null])

<div class="form-group">
    <label for="{{ $name }}" class="form-label">
        {{ $label }}@if ($required)<span style="color: var(--gold-hover);" aria-hidden="true">*</span>@endif
    </label>

    <input id="{{ $name }}"
           name="{{ $name }}"
           type="{{ $type }}"
           value="{{ old($name, $value) }}"
           @if ($required) required @endif
           @if ($readonly) readonly aria-readonly="true" @endif
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
           class="form-input">

    @if ($hint)
        <p class="form-help">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="form-error">{{ $message }}</p>
    @enderror
</div>
