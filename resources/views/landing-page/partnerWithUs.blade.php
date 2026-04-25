@extends('landing-page.layouts.default')

@section('after_head')
<style>
    .partner-hero {
        background: linear-gradient(135deg, #0f766e 0%, #1d4ed8 100%);
        color: #fff;
        padding: 72px 0 56px;
        text-align: center;
    }
    .partner-hero h1 {
        font-size: clamp(1.9rem, 4vw, 2.8rem);
        font-weight: 800;
        margin-bottom: 12px;
    }
    .partner-hero p {
        font-size: 1.05rem;
        opacity: .92;
        margin: 0;
    }
    .partner-wrap {
        padding: 48px 0 80px;
    }
    .partner-card {
        border: 1px solid #e7ecf1;
        border-radius: 16px;
        padding: 28px;
        background: #fff;
        box-shadow: 0 4px 24px rgba(9, 30, 66, .06);
        height: 100%;
    }
    .partner-card h3 {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 18px;
        color: #1f2937;
    }
    .savings-list {
        display: grid;
        gap: 12px;
    }
    .savings-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 12px;
        padding: 12px 14px;
        background: #f8fafc;
        border: 1px solid #e8edf3;
        font-weight: 600;
        color: #334155;
    }
    .savings-item .percent {
        color: #0f766e;
        font-weight: 800;
    }
    .section-title {
        text-align: center;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 28px;
        font-size: 1.35rem;
    }
    @media (max-width: 767px) {
        .partner-card {
            margin-bottom: 16px;
        }
    }
</style>
@endsection

@section('content')
<div class="partner-hero">
    <div class="container">
        <h1>Partner with us</h1>
        <p>Start saving today and Grow your wealth with us!</p>
    </div>
</div>

<div class="container partner-wrap">
    <h2 class="section-title">Savings Categories</h2>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="partner-card">
                <h3>Flexible</h3>
                <div class="savings-list">
                    <div class="savings-item">
                        <span>3 Months</span>
                        <span class="percent">5%</span>
                    </div>
                    <div class="savings-item">
                        <span>6 Months</span>
                        <span class="percent">15%</span>
                    </div>
                    <div class="savings-item">
                        <span>12 Months</span>
                        <span class="percent">50%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="partner-card">
                <h3>Fixed</h3>
                <div class="savings-list">
                    <div class="savings-item">
                        <span>3 Months</span>
                        <span class="percent">15%</span>
                    </div>
                    <div class="savings-item">
                        <span>6 Months</span>
                        <span class="percent">40%</span>
                    </div>
                    <div class="savings-item">
                        <span>12 Months</span>
                        <span class="percent">100%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
