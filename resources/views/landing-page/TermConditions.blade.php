@extends('landing-page.layouts.default')

@section('after_head')
<style>
    /* ── Policy page styles ─────────────────────────────────── */
    .policy-hero {
        background: linear-gradient(135deg, var(--bs-primary, #4f6ef7) 0%, #6a82fb 100%);
        padding: 72px 0 56px;
        text-align: center;
        color: #fff;
    }
    .policy-hero .badge-label {
        display: inline-block;
        background: rgba(255,255,255,.18);
        color: #fff;
        font-size: .8rem;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: 4px 14px;
        border-radius: 20px;
        margin-bottom: 16px;
    }
    .policy-hero h1 {
        font-size: clamp(1.75rem, 4vw, 2.6rem);
        font-weight: 700;
        margin-bottom: 12px;
    }
    .policy-hero p {
        opacity: .85;
        font-size: 1rem;
        margin-bottom: 0;
    }
    .policy-layout {
        display: grid;
        grid-template-columns: 240px 1fr;
        gap: 32px;
        align-items: start;
        padding: 56px 0 80px;
    }
    @media (max-width: 768px) {
        .policy-layout { grid-template-columns: 1fr; }
        .policy-sidebar { display: none; }
    }
    .policy-sidebar {
        position: sticky;
        top: 24px;
    }
    .policy-sidebar .sidebar-card {
        background: #f8f9fb;
        border-radius: 14px;
        padding: 20px;
        border: 1px solid #e8eaf0;
    }
    .policy-sidebar .sidebar-card h6 {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #9095a5;
        margin-bottom: 14px;
    }
    .policy-sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        font-size: .93rem;
        font-weight: 500;
        color: #4a4f65;
        transition: background .15s, color .15s;
        text-decoration: none;
    }
    .policy-sidebar .nav-link i { font-size: .95rem; width: 18px; text-align: center; }
    .policy-sidebar .nav-link:hover { background: #eef0fb; color: var(--bs-primary, #4f6ef7); }
    .policy-sidebar .nav-link.active {
        background: var(--bs-primary, #4f6ef7);
        color: #fff;
        font-weight: 600;
    }
    .policy-sidebar .nav-link + .nav-link { margin-top: 4px; }
    .policy-main-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e8eaf0;
        padding: 40px 44px;
        box-shadow: 0 2px 20px rgba(0,0,0,.04);
    }
    @media (max-width: 600px) {
        .policy-main-card { padding: 24px 20px; }
    }
    .policy-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: .82rem;
        color: #9095a5;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }
    .policy-meta span { display: flex; align-items: center; gap: 5px; }
    .policy-divider {
        height: 3px;
        width: 48px;
        background: var(--bs-primary, #4f6ef7);
        border-radius: 2px;
        margin: 0 0 28px;
    }
    .policy-body { font-size: .97rem; line-height: 1.8; color: #3a3f55; }
    .policy-body h1, .policy-body h2, .policy-body h3, .policy-body h4 {
        font-weight: 700;
        color: #1a1f35;
        margin-top: 2em;
        margin-bottom: .6em;
    }
    .policy-body p { margin-bottom: 1.1em; }
    .policy-body ul, .policy-body ol { padding-left: 1.4em; margin-bottom: 1.1em; }
    .policy-body li { margin-bottom: .5em; }
    .policy-body a { color: var(--bs-primary, #4f6ef7); }
    .policy-empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #9095a5;
    }
    .policy-empty-state i { font-size: 3rem; margin-bottom: 16px; display: block; }
</style>
@endsection

@section('content')

{{-- Hero --}}
<div class="policy-hero">
    <div class="badge-label">Legal</div>
    <h1>Terms &amp; Conditions</h1>
    <p>The rules and guidelines that govern your use of our platform.</p>
</div>

<div class="container">
    <div class="policy-layout">

        {{-- Sidebar --}}
        <aside class="policy-sidebar">
            <div class="sidebar-card">
                <h6>Legal Documents</h6>
                <a class="nav-link" href="{{ route('user.privacy_policy') }}">
                    <i class="fas fa-shield-alt"></i> Privacy Policy
                </a>
                <a class="nav-link active" href="{{ route('user.term_conditions') }}">
                    <i class="fas fa-file-contract"></i> Terms & Conditions
                </a>
                <a class="nav-link" href="{{ route('user.refund_policy') }}">
                    <i class="fas fa-exchange-alt"></i> Refund Policy
                </a>
            </div>
        </aside>

        {{-- Content --}}
        <div>
            <div class="policy-main-card">
                <div class="policy-meta">
                    <span><i class="fas fa-file-contract"></i> Terms &amp; Conditions</span>
                    <span>·</span>
                    <span><i class="far fa-calendar"></i> Last updated: {{ date('F j, Y') }}</span>
                </div>
                <div class="policy-divider"></div>

                @if(!empty($term_condition->value))
                    <div class="policy-body">
                        {!! $term_condition->value !!}
                    </div>
                @else
                    <div class="policy-empty-state">
                        <i class="fas fa-file-contract"></i>
                        <p>Our Terms &amp; Conditions are being updated. Please check back shortly.</p>
                        <p>For questions, contact us at <a href="mailto:support@elitefixconnect.com">support@elitefixconnect.com</a>.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

@endsection
