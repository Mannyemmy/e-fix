@extends('landing-page.layouts.headerremove')

@section('content')
<div class="container-fluid px-lg-0 py-lg-0">
    <div class="row min-vh-100 g-lg-0">

        {{-- Left panel: illustration (hidden on mobile) --}}
        <div class="col-xl-7 col-lg-6 d-none d-lg-flex mh-100">
            <div class="py-5 d-flex flex-column justify-content-center align-items-center h-100 w-100">
                @php
                    $loginregisterimage = Spatie\MediaLibrary\MediaCollections\Models\Media::where('collection_name', 'login_register_image')->first();
                    try { $loginSectionData = App\Models\LandingPage::where('section_name','login_register')->first(); } catch(\Throwable $e) { $loginSectionData = null; }
                @endphp
                <div class="text-center px-5">
                    @if($loginSectionData)
                        <h3 class="text-capitalize mb-3">{{ $loginSectionData->title ?? '' }}</h3>
                        <p class="text-muted mb-4">{{ $loginSectionData->description ?? '' }}</p>
                    @else
                        <h3 class="mb-3">Welcome Back to E-Fix</h3>
                        <p class="text-muted mb-4">Sign in to manage your services and bookings.</p>
                    @endif
                    @if($loginregisterimage)
                        <img src="{{ url('storage/' . $loginregisterimage->id . '/' . $loginregisterimage->file_name) }}" alt="login" class="img-fluid w-75">
                    @else
                        <img src="{{ asset('landing-images/general/login.webp') }}" class="img-fluid w-75" alt="login"/>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right panel: form --}}
        <div class="col-xl-5 col-lg-6 mh-100">
            <div class="py-5 px-3 bg-light d-flex flex-column justify-content-center min-vh-100">
                <div class="row justify-content-center">
                    <div class="col-xl-9 col-lg-11 col-md-8 col-sm-10">
                        <div class="authontication-forms">

                            {{-- Logo + heading --}}
                            <div class="text-center mb-4">
                                <a href="{{ route('frontend.index') }}">
                                    <img src="{{ getSingleMedia(imageSession('get'),'logo',null) }}" class="img-fluid mb-3" style="max-height:56px" alt="logo">
                                </a>
                                <h4 class="text-capitalize">{{ __('auth.sign_in') }}</h4>
                                <p class="text-muted small">{{ __('auth.dont_have_account') }} <a href="{{ route('auth.register') }}">{{ __('auth.signup') }}</a></p>
                            </div>

                            {{-- Alerts --}}
                            <x-auth-session-status class="mb-3" :status="session('status')" />
                            <x-auth-validation-errors class="mb-3" :errors="$errors" />

                            <form method="POST" action="{{ route('login') }}" data-toggle="validator">
                                {{ csrf_field() }}

                                <div class="form-group mb-4">
                                    <label class="form-label text-secondary small fw-semibold">{{ __('auth.email') }} <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input id="email" name="email" value="{{ old('email', request('email')) }}" class="form-control" type="email" placeholder="{{ __('auth.enter_name', ['name' => __('auth.email')]) }}" aria-describedby="emailAddonAuth" required autofocus>
                                        <span class="input-group-text" id="emailAddonAuth">
                                            <i class="fa fa-envelope" aria-hidden="true"></i>
                                        </span>
                                    </div>
                                    <small class="help-block with-errors text-danger"></small>
                                </div>

                                <div class="form-group mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label text-secondary small fw-semibold mb-0">{{ __('auth.login_password') }} <span class="text-danger">*</span></label>
                                        <a href="{{ route('auth.recover-password') }}" class="small">{{ __('auth.forgot_password') }}</a>
                                    </div>
                                    <div class="input-group mt-1">
                                        <input class="form-control" type="password" id="authPassword" name="password" placeholder="{{ __('auth.enter_name', ['name' => __('auth.login_password')]) }}" aria-describedby="toggleAuthPassword" required autocomplete="current-password">
                                        <span class="input-group-text" id="toggleAuthPassword" style="cursor:pointer" onclick="toggleAuthPass()">
                                            <i class="fa fa-eye-slash" aria-hidden="true" id="authPasswordIcon"></i>
                                        </span>
                                    </div>
                                    <small class="help-block with-errors text-danger"></small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mt-2">{{ __('auth.login') }}</button>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('bottom_script')
<script>
function toggleAuthPass() {
    const input = document.getElementById('authPassword');
    const icon = document.getElementById('authPasswordIcon');
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    icon.className = type === 'password' ? 'fa fa-eye-slash' : 'fa fa-eye';
}
</script>
@endsection
