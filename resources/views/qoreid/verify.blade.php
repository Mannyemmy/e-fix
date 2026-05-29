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
        #diag {
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
        <!-- The QoreID button is injected here by JS once the SDK script loads. -->
        <div id="qoreid-mount"></div>
        <!-- Visible diagnostic pane: helps figure out why the QoreID
             button never appeared without needing devtools. The host
             Flutter screen also forwards every diag line to logcat. -->
        <div id="diag">eFix QoreID diagnostics — initialising…</div>
    </div>

    <script>
        // ── Diagnostics ─────────────────────────────────────────────
        // Every meaningful step writes to console (visible to the host
        // Flutter logcat via runJavaScript) AND to the on-page #diag
        // pane (visible to the user without devtools). Read top-to-bottom
        // to know exactly where it stopped.
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

        // QoreID SDK callbacks attached to window so the SDK can invoke
        // them by name via the on* attributes.
        window.efixQoreIdSubmitted = function (data) {
            showInfo('Submitted. Waiting for verification result…');
            postToHost('submitted', data || null);
        };
        window.efixQoreIdError = function (err) {
            showError('Verification failed. Please try again.');
            postToHost('error', err || null);
        };
        window.efixQoreIdClosed = function () {
            postToHost('closed', null);
        };
        window.efixQoreIdVerified = function (data) {
            showInfo('Verified! You can return to eFix.');
            postToHost('verified', data || null);
        };

        // Inject the SDK script and mount the button when loaded.
        (function loadSdk() {
            var url = @json($sdkUrl);
            diag('injecting SDK script: ' + url);
            var s = document.createElement('script');
            s.src = url;
            s.async = true;
            s.onload = function () {
                diag('SDK script onload fired', 'ok');
                diag('window.customElements present: ' + !!window.customElements);
                mountButton();
            };
            s.onerror = function () {
                diag('SDK script onerror — failed to load ' + url, 'err');
                showError('Could not load verification SDK. Please check your connection.');
            };
            document.head.appendChild(s);
        })();

        function mountButton() {
            var clientId = @json($clientId);
            var productCode = @json($productCode);
            var ref = @json($customerReference);
            var firstName = @json($firstName);
            var lastName = @json($lastName);
            var email = @json($email);
            var phone = @json($phone);

            diag('mountButton() called', 'info');

            if (!clientId) {
                diag('mountButton: clientId is empty → blocking', 'err');
                showError('eFix QoreID is not configured. Contact support.');
                return;
            }
            if (!ref) {
                diag('mountButton: customerReference is empty → blocking', 'err');
                showError('Missing session reference.');
                return;
            }

            // Sanity-check that the SDK registered its custom element.
            // If not, the script tag returned 200 but didn't actually
            // define <qoreid-button>; usually a JS-runtime mismatch or
            // a wrong SDK URL.
            try {
                var defined = window.customElements && window.customElements.get && window.customElements.get('qoreid-button');
                diag('customElements.get(qoreid-button) ⇒ ' + (defined ? 'defined' : 'NOT defined'),
                     defined ? 'ok' : 'warn');
            } catch (e) {
                diag('customElements check threw: ' + e.message, 'warn');
            }

            var btn = document.createElement('qoreid-button');
            btn.setAttribute('id', 'QoreIDButton');
            btn.setAttribute('clientId', clientId);
            btn.setAttribute('productCode', productCode);
            btn.setAttribute('flowId', '0');
            btn.setAttribute('customerReference', ref);
            btn.setAttribute('applicantData', JSON.stringify({
                firstname: firstName,
                lastname: lastName,
                email: email,
                phone: phone,
            }));
            btn.setAttribute('onQoreIDSdkSubmitted', 'window.efixQoreIdSubmitted');
            btn.setAttribute('onQoreIDSdkError', 'window.efixQoreIdError');
            btn.setAttribute('onQoreIDSdkClosed', 'window.efixQoreIdClosed');
            btn.setAttribute('onQoreIDSdkVerified', 'window.efixQoreIdVerified');
            document.getElementById('qoreid-mount').appendChild(btn);
            diag('appended <qoreid-button> to #qoreid-mount', 'ok');

            // After a moment, check whether the button actually has any
            // rendered content. If not, the SDK silently bailed.
            setTimeout(function () {
                try {
                    var mounted = document.getElementById('QoreIDButton');
                    if (!mounted) {
                        diag('200ms later: #QoreIDButton not in DOM', 'err');
                        return;
                    }
                    var rect = mounted.getBoundingClientRect();
                    diag('200ms later: button size ' + Math.round(rect.width) + 'x' + Math.round(rect.height),
                         (rect.width > 0 && rect.height > 0) ? 'ok' : 'warn');
                    if (rect.width === 0 || rect.height === 0) {
                        diag('button has zero size — SDK likely did not render. Check QoreID dashboard: is this clientId valid for the env (test/live) you registered?', 'err');
                    }
                } catch (e) {
                    diag('post-mount check threw: ' + e.message, 'warn');
                }
            }, 200);
        }
    </script>
</body>
</html>
