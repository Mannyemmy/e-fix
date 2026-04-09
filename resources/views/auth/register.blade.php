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
                        <h3 class="mb-3">Join E-Fix as a Provider or Handyman</h3>
                        <p class="text-muted mb-4">Grow your business by connecting with customers who need your skills.</p>
                    @endif
                    @if($loginregisterimage)
                        <img src="{{ url('storage/' . $loginregisterimage->id . '/' . $loginregisterimage->file_name) }}" alt="register" class="img-fluid w-75">
                    @else
                        <img src="{{ asset('landing-images/general/login.webp') }}" class="img-fluid w-75" alt="register"/>
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
                                <h4 class="text-capitalize">{{ __('auth.get_start') }}</h4>
                                <p class="text-muted small">{{ __('auth.already_have_account') }} <a href="{{ route('auth.login') }}">{{ __('auth.sign_in') }}</a></p>
                            </div>

                            {{-- Alerts --}}
                            <x-auth-session-status class="mb-3" :status="session('status')" />
                            <x-auth-validation-errors class="mb-3" :errors="$errors" />

                            <form method="POST" action="{{ route('register') }}" data-toggle="validator">
                                {{ csrf_field() }}

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="username" class="form-label text-secondary small fw-semibold">{{ __('auth.username') }} <span class="text-danger">*</span></label>
                                        <input class="form-control" id="username" name="username" value="{{ old('username') }}" required placeholder="{{ __('auth.enter_name', ['name' => __('auth.username')]) }}">
                                        <small class="help-block with-errors text-danger"></small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="first_name" class="form-label text-secondary small fw-semibold">{{ __('auth.first_name') }} <span class="text-danger">*</span></label>
                                        <input class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" required placeholder="{{ __('auth.enter_name', ['name' => __('auth.first_name')]) }}">
                                        <small class="help-block with-errors text-danger"></small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="last_name" class="form-label text-secondary small fw-semibold">{{ __('auth.last_name') }} <span class="text-danger">*</span></label>
                                        <input class="form-control" id="last_name" name="last_name" value="{{ old('last_name') }}" required placeholder="{{ __('auth.enter_name', ['name' => __('auth.last_name')]) }}">
                                        <small class="help-block with-errors text-danger"></small>
                                    </div>
                                    <div class="col-12">
                                        <label for="email" class="form-label text-secondary small fw-semibold">{{ __('auth.email') }} <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="{{ __('auth.enter_name', ['name' => __('auth.email')]) }}" pattern="[^@]+@[^@]+\.[a-zA-Z]{2,}" aria-describedby="emailAddonAuthReg">
                                            <span class="input-group-text" id="emailAddonAuthReg">
                                                <i class="fa fa-envelope" aria-hidden="true"></i>
                                            </span>
                                        </div>
                                        <small class="help-block with-errors text-danger"></small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="password" class="form-label text-secondary small fw-semibold">{{ __('auth.login_password') }} <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input class="form-control" type="password" id="password" name="password" required autocomplete="new-password" placeholder="{{ __('auth.enter_name', ['name' => __('auth.login_password')]) }}" aria-describedby="toggleRegPassword">
                                            <span class="input-group-text" id="toggleRegPassword" style="cursor:pointer" onclick="toggleRegPass('password','toggleRegPasswordIcon')">
                                                <i class="fa fa-eye-slash" aria-hidden="true" id="toggleRegPasswordIcon"></i>
                                            </span>
                                        </div>
                                        <small class="help-block with-errors text-danger"></small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="password_confirmation" class="form-label text-secondary small fw-semibold">{{ __('auth.confirm_password') }} <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input class="form-control" onkeyup="checkPasswordMatch()" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="{{ __('auth.enter_name', ['name' => __('auth.confirm_password')]) }}" aria-describedby="toggleRegConfirmPassword">
                                            <span class="input-group-text" id="toggleRegConfirmPassword" style="cursor:pointer" onclick="toggleRegPass('password_confirmation','toggleRegConfirmPasswordIcon')">
                                                <i class="fa fa-eye-slash" aria-hidden="true" id="toggleRegConfirmPasswordIcon"></i>
                                            </span>
                                        </div>
                                        <small class="help-block with-errors text-danger" id="confirm_passsword"></small>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="user_type" class="form-label text-secondary small fw-semibold">{{ __('messages.user_type') }} <span class="text-danger">*</span></label>
                                        <select name="usertype" class="form-select" id="status">
                                            <option value="provider">{{ __('messages.provider') }}</option>
                                            <option value="handyman">{{ __('messages.handyman') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="designation" class="form-label text-secondary small fw-semibold">{{ __('messages.designation') }}</label>
                                        <input type="text" id="designation" name="designation" class="form-control" placeholder="{{ __('placeholder.designation') }}">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check d-flex align-items-center gap-2">
                                            <input type="checkbox" class="form-check-input mt-0" id="customCheck1" required>
                                            <label class="form-check-label small" for="customCheck1">
                                                {{ __('auth.agree') }}
                                                <a href="{{ url('/') }}/#/term-conditions">{{ __('auth.term_service') }}</a>
                                                &amp;
                                                <a href="{{ url('/') }}/#/privacy-policy">{{ __('auth.privacy_policy') }}</a>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mt-4" id="submit-btn">
                                    {{ __('auth.create_account') }}
                                </button>

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
function toggleRegPass(fieldId, iconId) {
    const input = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    icon.className = type === 'password' ? 'fa fa-eye-slash' : 'fa fa-eye';
}
function checkPasswordMatch() {
    var password = document.getElementById("password").value;
    var confirmPassword = document.getElementById("password_confirmation").value;
    var errorElement = document.getElementById("confirm_passsword");
    var submitBtn = document.getElementById("submit-btn");
    if (password !== confirmPassword) {
        errorElement.innerHTML = "{{ __('auth.password_mismatch_error') }}";
        submitBtn.disabled = true;
    } else {
        errorElement.innerHTML = "";
        submitBtn.disabled = false;
    }
}
</script>
@endsection
