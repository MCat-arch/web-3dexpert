@php
    /*
     * Links are signed and expire: the page is stateless (code + contact, then
     * results), so a plain URL would be the only thing standing between a
     * crawled link and a customer's files. 30 minutes covers reading the page
     * and downloading what is on it.
     */
    $downloadUrl = URL::temporarySignedRoute(
        'tracker.attachments.show',
        now()->addMinutes(30),
        ['attachment' => $file->getKey()],
    );
@endphp

<a class="tracker-file" href="{{ $downloadUrl }}" rel="noopener noreferrer">
    <span class="tracker-file-icon {{ $file->isImage() ? 'is-image' : 'is-doc' }}">
        <x-ui-icon :name="$file->isImage() ? 'image' : 'file-text'" :size="20" />
    </span>

    <span class="tracker-file-body">
        <strong class="tracker-file-name">{{ $file->displayName() }}</strong>
        <span class="tracker-file-meta">{{ $file->typeLabel() }} &middot; {{ $file->sizeLabel() }}</span>
    </span>

    <span class="tracker-file-cta">{{ __('tracker.download') }}</span>
</a>
