<div class="auth-switcher mb-4">
    <div class="d-flex flex-column gap-2">
        <div class="d-flex rounded overflow-hidden border border-primary" role="group">
            <a href="{{ route('user.login') }}"
               class="btn btn-sm flex-fill py-2 {{ request()->routeIs('user.login') ? 'btn-primary text-white' : 'btn-white text-primary' }}">
                <i class="fa fa-user me-1" aria-hidden="true"></i> Customer Login
            </a>
            <a href="{{ route('auth.login') }}"
               class="btn btn-sm flex-fill py-2 border-start border-primary {{ request()->routeIs('auth.login') ? 'btn-primary text-white' : 'btn-white text-primary' }}">
                <i class="fa fa-wrench me-1" aria-hidden="true"></i> Artisan Login
            </a>
        </div>
        <div class="d-flex rounded overflow-hidden border border-primary" role="group">
            <a href="{{ route('user.register') }}"
               class="btn btn-sm flex-fill py-2 {{ request()->routeIs('user.register') ? 'btn-primary text-white' : 'btn-white text-primary' }}">
                <i class="fa fa-user-plus me-1" aria-hidden="true"></i> Register as Customer
            </a>
            <a href="{{ route('auth.register') }}"
               class="btn btn-sm flex-fill py-2 border-start border-primary {{ request()->routeIs('auth.register') ? 'btn-primary text-white' : 'btn-white text-primary' }}">
                <i class="fa fa-wrench me-1" aria-hidden="true"></i> Register as Artisan
            </a>
        </div>
    </div>
</div>
