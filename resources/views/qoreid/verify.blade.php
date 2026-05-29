<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>eFix Identity Verification</title>
    <style>
        :root { --primary: #1e88e5; --bg: #0e0f12; --card: #1a1c20; --text: #f5f7fa; --muted: #98a0aa; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            padding: 24px 20px;
        }
        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 24px;
            margin-top: auto;
            margin-bottom: auto;
        }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { margin: 0 0 16px; color: var(--muted); font-size: 14px; line-height: 1.5; }
        .checks { margin: 16px 0 24px; padding-left: 20px; }
        .checks li { color: var(--muted); font-size: 14px; margin-bottom: 6px; }
        #status {
            margin-top: 16px;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            display: none;
        }
        #status.error { background: #401a1a; color: #ff8a80; display: block; }
        #status.info { background: #1a2c40; color: #82b1ff; display: block; }
        /*
         * Diag pane is hidden from the user by default. The logs still
         * write to console + post to the Flutter EfixQoreId channel so
         * you can read them in logcat / Laravel logs.
         *
         * To re-enable on-screen diagnostics (useful when debugging on
         * a device without adb access), append ?diag=1 to the verify
         * URL — the script section below toggles display on.
         */
        #diag { display: none; }
        #diag.show {
            display: block;
            margin-top: 16px;
            background: #101418;
            border: 1px solid #1f2a36;
            border-radius: 8px;
            padding: 10px 12px;
            font-family: ui-monospace, "SF Mono", Consolas, monospace;
            font-size: 11px;
            color: #b6c2cf;
            max-height: 40vh;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #diag .row { margin: 2px 0; }
        #diag .ok { color: #4caf50; }
        #diag .warn { color: #ffb74d; }
        #diag .err { color: #ff8a80; }
        qoreid-button {
            display: block;
            width: 100%;
            margin-top: 16px;
        }

        /* Fullscreen loader shown the moment the user taps the QoreID
         * button. The SDK takes ~500ms-1s to bring up its modal, which
         * looks like a dead tap without this feedback. We hide it again
         * as soon as any SDK callback fires, or after a 15s safety
         * timeout. */
        #tap-loader {
            position: fixed;
            inset: 0;
            background: rgba(14, 15, 18, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            z-index: 9999;
        }
        #tap-loader.show { display: flex; }
        #tap-loader .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255,255,255,0.15);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        #tap-loader p {
            color: var(--text);
            margin-top: 16px;
            font-size: 14px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <h1>Verify your identity</h1>
        <p>We need a quick face scan plus your {{ $productCode === 'liveness_nin' ? 'NIN' : 'BVN' }} to open your eFix bank account. This is a one-time check.</p>
        <ul class="checks">
            <li>Take a selfie on this device</li>
            <li>Enter your {{ $productCode === 'liveness_nin' ? 'NIN' : 'BVN' }}</li>
            <li>We compare your face to your ID photo on file</li>
        </ul>

        <div id="status"></div>
        <!--
            The QoreID SDK enhances the <qoreid-button> element that is
            already in the DOM at script-load time (it does NOT register
            as a custom element). Render it server-side with all
            attributes filled, then the SDK script (further down) picks
            it up. Creating it via JS *after* the script tag is too late
            — the SDK logs "QoreID button is missing on this page" and
            bails. Verified by reading the SDK's own console output.
        -->
        @if($clientId && $customerReference)
            <qoreid-button
                id="QoreIDButton"
                flowId="0"
                clientId="{{ $clientId }}"
                productCode="{{ $productCode }}"
                customerReference="{{ $customerReference }}"
                applicantData='{{ json_encode([
                    "firstname" => $firstName,
                    "lastname"  => $lastName,
                    "email"     => $email,
                    "phone"     => $phone,
                ]) }}'
                onQoreIDSdkSubmitted="window.efixQoreIdSubmitted"
                onQoreIDSdkError="window.efixQoreIdError"
                onQoreIDSdkClosed="window.efixQoreIdClosed"
                onQoreIDSdkVerified="window.efixQoreIdVerified"
            ></qoreid-button>
        @endif

        <!-- Visible diagnostic pane: helps figure out why the QoreID
             button never appeared without needing devtools. The host
             Flutter screen also forwards every diag line to logcat. -->
        <div id="diag">eFix QoreID diagnostics — initialising…</div>
    </div>

    <!-- Tap-feedback overlay. Shown the moment the QoreID button is
         pressed and hidden again as soon as the SDK takes over. -->
    <div id="tap-loader" aria-hidden="true">
        <div class="spinner"></div>
        <p>Opening verification…</p>
    </div>

    <script>
        // ── Diagnostics ─────────────────────────────────────────────
        // Every meaningful step writes to console (visible in the host
        // Flutter logcat via runJavaScript) and posts to the
        // EfixQoreId JS channel (also visible in logcat). The on-page
        // pane is hidden by default — opt in with ?diag=1 to see it on
        // the device when debugging without adb.
        (function () {
            try {
                var qs = (location.search || '').toLowerCase();
                if (qs.indexOf('diag=1') !== -1) {
                    var el = document.getElementById('diag');
                    if (el) el.classList.add('show');
                }
            } catch (_) {}
        })();

        function diag(line, level) {
            try {
                var el = document.getElementById('diag');
                if (el) {
                    var div = document.createElement('div');
                    div.className = 'row ' + (level || '');
                    var ts = new Date().toISOString().substr(11, 8);
                    div.textContent = '[' + ts + '] ' + line;
                    el.appendChild(div);
                    el.scrollTop = el.scrollHeight;
                }
            } catch (_) {}
            try {
                console.log('[qoreid-verify] ' + line);
                postToHost('diag', { line: line, level: level || 'info' });
            } catch (_) {}
        }

        // ── Initial state dump ──────────────────────────────────────
        diag('page loaded, user-agent: ' + navigator.userAgent);
        diag('document.URL=' + document.URL);
        diag('clientId="' + (@json($clientId) || '') + '" (length=' + ((@json($clientId) || '').length) + ')');
        diag('sdkUrl="' + (@json($sdkUrl) || '') + '"');
        diag('productCode="' + @json($productCode) + '"');
        diag('customerReference="' + @json($customerReference) + '"');
        diag('firstName="' + @json($firstName) + '"');
        diag('lastName="' + @json($lastName) + '"');
        diag('email="' + @json($email) + '"');

        // Surface any uncaught errors directly into the diag pane.
        window.addEventListener('error', function (e) {
            diag('window.error: ' + (e.message || 'unknown') + ' @ ' + (e.filename || '?') + ':' + (e.lineno || '?'), 'err');
        });
        window.addEventListener('unhandledrejection', function (e) {
            diag('unhandledrejection: ' + (e.reason && e.reason.message ? e.reason.message : String(e.reason)), 'err');
        });

        // Bridge to the host Flutter WebView. We post these events so the
        // mobile screen can react without polling /api/qoreid/status. The
        // host installs a JS channel called `EfixQoreId`.
        function postToHost(event, payload) {
            try {
                var msg = JSON.stringify({ event: event, payload: payload || null });
                if (window.EfixQoreId && typeof window.EfixQoreId.postMessage === 'function') {
                    window.EfixQoreId.postMessage(msg);
                }
                // Also try the iOS WkWebView bridge if present (so a single
                // page works for both runtimes).
                if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.EfixQoreId) {
                    window.webkit.messageHandlers.EfixQoreId.postMessage(msg);
                }
            } catch (e) { /* ignore */ }
        }

        function showError(msg) {
            var el = document.getElementById('status');
            el.className = 'error';
            el.textContent = msg;
        }
        function showInfo(msg) {
            var el = document.getElementById('status');
            el.className = 'info';
            el.textContent = msg;
        }

        // ── Tap-feedback loader ─────────────────────────────────────
        // The SDK has perceptible startup latency (~500ms-1s on slower
        // devices) between the tap and the modal appearing. We show a
        // brief loader to bridge that gap, then hide it the moment the
        // SDK's modal lands in the DOM — otherwise our overlay sits on
        // top and hides the SDK's UI.
        var _tapLoaderTimeout = null;
        var _modalWatcher = null;
        function showTapLoader() {
            var el = document.getElementById('tap-loader');
            if (el) el.classList.add('show');

            // Hide as soon as the SDK injects ANY new top-level element
            // into <body>. The QoreID SDK adds either an iframe or a
            // modal container at body level — both are tells that the
            // modal is now visible.
            if (!_modalWatcher && 'MutationObserver' in window) {
                _modalWatcher = new MutationObserver(function (mutations) {
                    for (var i = 0; i < mutations.length; i++) {
                        var added = mutations[i].addedNodes;
                        for (var j = 0; j < added.length; j++) {
                            var n = added[j];
                            if (n && n.nodeType === 1 &&
                                n.id !== 'tap-loader' &&
                                (n.tagName === 'IFRAME' ||
                                 (n.tagName === 'DIV' && n.parentNode === document.body))) {
                                diag('SDK modal element detected (' + n.tagName.toLowerCase() + ') → hiding loader', 'ok');
                                hideTapLoader();
                                return;
                            }
                        }
                    }
                });
                _modalWatcher.observe(document.body, { childList: true });
            }

            // Hard safety net: hide after 2.5s even if the watcher
            // misses. Short enough to recover if the SDK never opens.
            if (_tapLoaderTimeout) clearTimeout(_tapLoaderTimeout);
            _tapLoaderTimeout = setTimeout(function () {
                diag('tap loader safety timeout fired → hiding', 'warn');
                hideTapLoader();
            }, 2500);
            diag('tap loader shown');
        }
        function hideTapLoader() {
            var el = document.getElementById('tap-loader');
            if (el) el.classList.remove('show');
            if (_tapLoaderTimeout) {
                clearTimeout(_tapLoaderTimeout);
                _tapLoaderTimeout = null;
            }
            if (_modalWatcher) {
                _modalWatcher.disconnect();
                _modalWatcher = null;
            }
        }

        // Attach the tap listener as soon as the static button exists.
        // We listen on `click` (not `pointerdown`) so the SDK's own
        // click handler runs first. Capture phase + a passive listener
        // means we never preventDefault / stopPropagation by accident.
        (function attachTapListener() {
            var btn = document.getElementById('QoreIDButton');
            if (!btn) {
                diag('tap listener: #QoreIDButton not in DOM yet, retrying', 'warn');
                setTimeout(attachTapListener, 100);
                return;
            }
            btn.addEventListener('click', showTapLoader, { passive: true });
            diag('tap listener attached to #QoreIDButton', 'ok');
        })();

        // QoreID SDK callbacks attached to window so the SDK can invoke
        // them by name via the on* attributes.
        window.efixQoreIdSubmitted = function (data) {
            hideTapLoader();
            showInfo('Submitted. Waiting for verification result…');
            postToHost('submitted', data || null);
        };
        window.efixQoreIdError = function (err) {
            hideTapLoader();
            showError('Verification failed. Please try again.');
            postToHost('error', err || null);
        };
        window.efixQoreIdClosed = function () {
            hideTapLoader();
            postToHost('closed', null);
        };
        window.efixQoreIdVerified = function (data) {
            hideTapLoader();
            showInfo('Verified! You can return to eFix.');
            postToHost('verified', data || null);
        };

        // ── Pre-flight checks (before the SDK script is even injected) ──
        (function preflight() {
            var clientId = @json($clientId);
            var ref = @json($customerReference);
            if (!clientId) {
                diag('preflight: clientId is empty → blocking', 'err');
                showError('eFix QoreID is not configured. Contact support.');
                return;
            }
            if (!ref) {
                diag('preflight: customerReference is empty → blocking', 'err');
                showError('Missing session reference.');
                return;
            }
            var pre = document.getElementById('QoreIDButton');
            diag('preflight: <qoreid-button> in static DOM? ' + (pre ? 'yes' : 'no'),
                 pre ? 'ok' : 'err');
        })();

        // ── Inject the SDK script AFTER the static <qoreid-button> ──
        // The QoreID SDK does a single DOM scan at script-load time
        // looking for <qoreid-button> elements to enhance. So:
        //   1. The element MUST already be in the DOM (rendered above).
        //   2. The script tag MUST come after that element.
        // If you reorder these or create the button via JS post-load,
        // the SDK logs "QoreID button is missing on this page" and
        // gives up silently.
        (function loadSdk() {
            var url = @json($sdkUrl);
            diag('injecting SDK script: ' + url);
            var s = document.createElement('script');
            s.src = url;
            s.async = true;
            s.onload = function () {
                diag('SDK script onload fired', 'ok');
                // The SDK enhances the static <qoreid-button>; give it a
                // tick and then verify the button has visible size.
                setTimeout(function () {
                    var btn = document.getElementById('QoreIDButton');
                    if (!btn) {
                        diag('post-load: #QoreIDButton missing from DOM', 'err');
                        return;
                    }
                    var rect = btn.getBoundingClientRect();
                    var sized = rect.width > 0 && rect.height > 0;
                    diag('post-load: button size ' + Math.round(rect.width) + 'x' + Math.round(rect.height),
                         sized ? 'ok' : 'warn');
                    if (!sized) {
                        diag('button still zero-sized — usually means QoreID rejected the clientId (sandbox vs live mismatch, or origin not whitelisted in QoreID dashboard).', 'err');
                    }
                }, 400);
            };
            s.onerror = function () {
                diag('SDK script onerror — failed to load ' + url, 'err');
                showError('Could not load verification SDK. Please check your connection.');
            };
            document.body.appendChild(s);
        })();
    </script>
</body>
</html>
