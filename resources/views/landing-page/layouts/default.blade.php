<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ session()->has('dir') ? session()->get('dir') : 'ltr' , }}">
<head>
    @yield('before_head')
    @include('landing-page.partials._head')
    @if (!env('UI_LOCAL_MODE', false))
      @include('landing-page.partials._currencyscripts')
    @endif

    @yield('after_head')
</head>
<body class="body-bg">


    <span class="screen-darken"></span>

    @if (env('UI_LOCAL_MODE', false))
    <style>
        #loading {
            display: none !important;
        }
    </style>
    @endif

    <div id="loading">
        @include('landing-page.partials.loading')
    </div>


    <main class="main-content" id="landing-app">
        <div class="position-relative">
            @if (!env('UI_LOCAL_MODE', false))
                @include('landing-page.partials._header')
            @endif
        </div>
        @yield('content')
    </main>

    @if (!env('UI_LOCAL_MODE', false))
        @include('landing-page.partials._footer')
        @include('landing-page.partials.cookie')
        @include('landing-page.partials.back-to-top')
    @endif



  @yield('before_script')
    @if (!env('UI_LOCAL_MODE', false))
        @include('landing-page.partials._scripts')
    @endif
    {{-- Swiper JS (local) --}}
    <script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}"></script>
    {{-- Broken image fallback — replaces missing images with a styled placeholder + logs broken URLs --}}
    <script>
    (function () {
        'use strict';
        var PLACEHOLDER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23f1f5f9'/%3E%3Ccircle cx='200' cy='120' r='30' fill='%23cbd5e1'/%3E%3Crect x='155' y='165' width='90' height='8' rx='4' fill='%23cbd5e1'/%3E%3Crect x='165' y='183' width='70' height='6' rx='3' fill='%23e2e8f0'/%3E%3C/svg%3E";
        var _brokenLog = [];
        var _flushTimer = null;

        function logBroken(src) {
            console.warn('[BrokenImage]', src);
            _brokenLog.push(src);
            // Debounce — flush to server 2 s after last broken image detected
            clearTimeout(_flushTimer);
            _flushTimer = setTimeout(flush, 2000);
        }

        function flush() {
            if (!_brokenLog.length) return;
            var urls = _brokenLog.slice();
            _brokenLog = [];
            fetch('{{ route("log.broken-images") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ urls: urls, page: window.location.href })
            }).catch(function () {}); // silent fail — logging is best-effort
        }

        function applyFallback(img) {
            if (img.naturalWidth === 0 || img.src === window.location.href) {
                if (img.src && img.src !== PLACEHOLDER && !img.src.startsWith('data:')) {
                    logBroken(img.src);
                }
                img.src = PLACEHOLDER;
                img.style.objectFit = 'contain';
                img.style.background = '#f1f5f9';
            }
        }

        function handleAllImages() {
            document.querySelectorAll('img').forEach(function (img) {
                if (img.complete) {
                    applyFallback(img);
                } else {
                    img.addEventListener('error', function () { applyFallback(this); }, { once: true });
                }
            });
        }

        // Run on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', handleAllImages);
        } else {
            handleAllImages();
        }

        // Also watch for Vue-injected images (MutationObserver)
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'IMG') applyFallback(node);
                        node.querySelectorAll && node.querySelectorAll('img').forEach(applyFallback);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    @yield('after_script')

   
</body>
</html>
