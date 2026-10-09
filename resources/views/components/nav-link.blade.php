@props(['active' => false, 'mobile' => false])

<a {{ $attributes->merge([
    'class' => ($mobile ? 'mobile-nav-item ' : '') . 'nav-link' . ($active ? ' is-active' : '')
]) }}
   @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
