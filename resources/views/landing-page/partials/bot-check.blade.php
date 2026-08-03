{{--
    Bot checks for the public signup forms.

    Honeypot: positioned off-screen rather than type="hidden", because naive
    form-filling bots skip hidden inputs but happily fill visible-in-the-DOM ones.
    The field name must stay in sync with VerifyTurnstile::HONEYPOT_FIELD.

    Turnstile only renders once TURNSTILE_SITE_KEY is set, so this partial is
    safe to ship before the Cloudflare keys exist.
--}}
<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;">
    <label for="company_website">Company website</label>
    <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
</div>

@if (!empty(config('services.turnstile.site_key')))
    <div class="cf-turnstile mb-4" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
