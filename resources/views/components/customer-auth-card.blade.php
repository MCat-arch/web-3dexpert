@props(['title', 'subtitle' => null, 'action', 'method' => 'POST'])

{{-- Shared frame for the customer auth forms --}}
<div class="auth-wrapper">
    <section class="auth-card">
        <div class="auth-back">
            <a href="{{ $lroute('home') }}" class="auth-back-link">&larr; {{ __('customer.back_home') }}</a>
        </div>

        <div class="auth-header">
            <div style="display: flex; justify-content: center; margin-bottom: 20px;">
                <a href="{{ $lroute('home') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="3D Expert"
                         style="height: 40px; width: auto; object-fit: contain;"
                         onerror="this.parentElement.innerHTML='<span style=\'font-size:1.1rem;font-weight:800;color:var(--ink-900);letter-spacing:-0.02em;\'>3D<span style=\'color:var(--gold-hover);\'>Expert</span></span>'">
                </a>
            </div>
            <h1 class="auth-title">{{ $title }}</h1>
            @if ($subtitle)
                <p class="auth-desc">{{ $subtitle }}</p>
            @endif
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul style="list-style-position: inside; margin: 0; padding: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="{{ $method }}" action="{{ $action }}">
            @csrf
            {{ $slot }}
        </form>

        @if (isset($footer))
            <div style="margin-top: 24px; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
                {{ $footer }}
            </div>
        @endif
    </section>
</div>