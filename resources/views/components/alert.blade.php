@props(['type' => 'success'])

<div role="{{ $type === 'error' ? 'alert' : 'status' }}"
     class="alert {{ $type === 'error' ? 'alert-error' : 'alert-success' }}">
    {{ $slot }}
</div>
