@php
    $authLabel = request()->routeIs('user.login') ? 'Customer Login'
        : (request()->routeIs('auth.login') ? 'Artisan Login'
        : (request()->routeIs('user.register') ? 'Register as Customer'
        : 'Register as Artisan'));
@endphp
<p class="text-muted small text-uppercase fw-semibold mb-3 tracking-wide">{{ $authLabel }}</p>
