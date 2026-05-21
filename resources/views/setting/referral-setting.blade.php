{{ Form::model($referralsetting, ['method' => 'POST','route' => ['saveReferralSetting'],'enctype'=>'multipart/form-data','data-toggle'=>'validator']) }}

{{ Form::hidden('id', null, ['placeholder' => 'id', 'class' => 'form-control']) }}
{{ Form::hidden('page', $page, ['placeholder' => 'page', 'class' => 'form-control']) }}

<div class="row">
    <div class="col-lg-12">
        <div class="form-group d-flex justify-content-between">
            <label for="referral_status" class="mb-0">{{ __('messages.enable_referral_system') }}</label>
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" name="referral_status" id="referral_status" {{ !empty($referralsetting->value) ? (json_decode($referralsetting->value)->referral_status ?? false ? 'checked' : '') : '' }}>
                <label class="custom-control-label" for="referral_status"></label>
            </div>
        </div>
    </div>
</div>

<div class="row" id="referral_details_section">
    <div class="col-md-6">
        <div class="form-group">
            {{ Form::label('referral_reward_amount', __('messages.referral_reward_amount') . ' <span class="text-danger">*</span>', ['class' => 'form-control-label'], false) }}
            {{ Form::number('referral_reward_amount', !empty($referralsetting->value) ? (json_decode($referralsetting->value)->referral_reward_amount ?? 10) : 10, ['class' => 'form-control', 'id' => 'referral_reward_amount', 'placeholder' => __('messages.referral_reward_amount'), 'step' => '0.01', 'required']) }}
            <small class="help-block with-errors text-danger"></small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {{ Form::label('referral_currency_code', __('messages.referral_currency_code') . ' <span class="text-danger">*</span>', ['class' => 'form-control-label'], false) }}
            {{ Form::text('referral_currency_code', !empty($referralsetting->value) ? (json_decode($referralsetting->value)->referral_currency_code ?? 'USD') : 'USD', ['class' => 'form-control', 'id' => 'referral_currency_code', 'placeholder' => __('messages.referral_currency_code'), 'required']) }}
            <small class="help-block with-errors text-danger"></small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <div class="col-md-offset-3 col-sm-12">
                {{ Form::submit(__('messages.save'), ['class' => 'btn btn-md btn-primary float-md-right']) }}
            </div>
        </div>
    </div>
</div>
{{ Form::close() }}

<script>
    var referralStatus = $("input[name='referral_status']").prop('checked');
    toggleReferralDetails(referralStatus);

    $('#referral_status').change(function () {
        var value = $(this).prop('checked');
        toggleReferralDetails(value);
    });

    function toggleReferralDetails(value) {
        if (value == true) {
            $('#referral_details_section').removeClass('d-none');
            $("#referral_reward_amount").prop("required", true);
        } else {
            $('#referral_details_section').addClass('d-none');
            $("#referral_reward_amount").prop("required", false);
        }
    }
</script>
