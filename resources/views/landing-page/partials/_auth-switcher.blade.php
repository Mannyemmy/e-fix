@php
    $authLabel = request()->routeIs('user.login') ? 'Customer Login'
        : (request()->routeIs('auth.login') ? 'Admin Login'
        : (request()->routeIs('user.register') ? 'Register as Customer'
        : 'Register as Artisan'));
@endphp
<p class="small text-uppercase fw-semibold mb-3" style="color: var(--efix-gray-700, #334155); letter-spacing: .05em;">{{ $authLabel }}</p>
