{{ Form::model($payment_data, ['method' => 'POST','route' => ['paymentsettingsUpdates'],'enctype'=>'multipart/form-data','data-toggle'=>'validator']) }}

{{ Form::hidden('id', null, array('placeholder' => 'id','class' => 'form-control')) }}
{{ Form::hidden('type', $tabpage, array('placeholder' => 'id','class' => 'form-control')) }}
<div class="row">
    <div class="form-group col-md-12">
        <label for="enable_rootfi">{{__('messages.payment_on',['gateway'=>__('messages.rootfi')])}}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" name="status" id="enable_rootfi" {{!empty($payment_data->status) ? 'checked' : '' }}>
            <label class="custom-control-label" for="enable_rootfi"></label>
        </div>
        <small class="form-text text-muted">Enable RootFi for virtual accounts, transfers, and identity verification</small>
    </div>
</div>
<div class="row" id='enable_rootfi_payment'>
    <div class="form-group col-md-12">
        <label class="form-control-label">{{__('messages.payment_option',['gateway'=>__('messages.rootfi')])}}</label><br/>
        <div class="form-check-inline">
            <label class="form-check-label">
                <input type="radio" class="form-check-input is_test" value="on" name="is_test" data-type="is_test_mode" {{!empty($payment_data) && $payment_data->is_test == 1 ? 'checked' :''}}>{{__('messages.is_test_mode')}}
            </label>
        </div>
        <div class="form-check-inline">
            <label class="form-check-label">
                <input type="radio" class="form-check-input is_test" value="off" name="is_test" data-type="is_live_mode" {{!empty($payment_data) && $payment_data->is_test == 0 ? 'checked' :''}}>{{__('messages.is_live_mode')}}
            </label>
        </div>
        <small class="form-text text-muted">Credentials below are saved separately per mode.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('title',trans('messages.gateway_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('title',old('title'),['id'=>'title','placeholder' => 'RootFi','class' =>'form-control']) }}
        <small class="help-block with-errors text-danger"></small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('base_url', 'Base URL <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('base_url',old('base_url'),['id'=>'base_url','placeholder' => 'https://api.rootfi.co','class' =>'form-control']) }}
        <small class="form-text text-muted">Use <code>https://api.rootfi.co</code> for live, sandbox URL for test.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('api_key', 'API Key (x-api-key) <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('api_key',old('api_key'),['id'=>'api_key','placeholder' => 'rf_live_… or rf_test_…','class' =>'form-control']) }}
        <small class="form-text text-muted">From your RootFi dashboard → Apps & API Keys.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('webhook_secret', 'Webhook Secret',['class'=>'form-control-label'], false ) }}
        {{ Form::text('webhook_secret',old('webhook_secret'),['id'=>'webhook_secret','placeholder' => 'whsec_…','class' =>'form-control']) }}
        <small class="form-text text-muted">Used to verify <code>X-RootFi-Signature</code> on inbound webhooks at <code>/api/safehaven/webhook</code>.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('master_account_number', 'Master Sub-Account Number',['class'=>'form-control-label'], false ) }}
        {{ Form::text('master_account_number',old('master_account_number'),['id'=>'master_account_number','placeholder' => '5015843175','class' =>'form-control']) }}
        <small class="form-text text-muted">eFix's pool sub-account; debited for provider payouts.</small>
    </div>
</div>
{{ Form::submit(__('messages.save'), ['class'=>"btn btn-md btn-primary float-md-right"]) }}
{{ Form::close() }}
<script>
var enable_rootfi = $("input[name='status']").prop('checked');
checkPaymentTabOption(enable_rootfi);

var get_value = $('input[name="is_test"]:checked').data("type");
if(get_value){
    getConfig(get_value);
}

$('#enable_rootfi').change(function(){
    value = $(this).prop('checked') == true ? true : false;
    checkPaymentTabOption(value);
});
$('.is_test').change(function(){
    type = $(this).data("type");
    getConfig(type);
});

function checkPaymentTabOption(value){
    if(value == true){
        $('#enable_rootfi_payment').removeClass('d-none');
        $('#title').prop('required', true);
        $('#base_url').prop('required', true);
        $('#api_key').prop('required', true);
    }else{
        $('#enable_rootfi_payment').addClass('d-none');
        $('#title').prop('required', false);
        $('#base_url').prop('required', false);
        $('#api_key').prop('required', false);
    }
}

function getConfig(type){
    var _token   = $('meta[name="csrf-token"]').attr('content');
    var page =  "{{$tabpage}}";
    $.ajax({
        url: "/get_payment_config",
        type:"POST",
        data:{
          type:type,
          page:page,
          _token: _token
        },
        success:function(response){
            if(response && response.data){
                var obj = null;
                if(response.data.type == 'is_test_mode'){
                    obj = JSON.parse(response.data.value || '{}');
                }else{
                    obj = JSON.parse(response.data.live_value || '{}');
                }
                $('#base_url').val(obj.base_url || '');
                $('#api_key').val(obj.api_key || '');
                $('#webhook_secret').val(obj.webhook_secret || '');
                $('#master_account_number').val(obj.master_account_number || '');
                $('#title').val(response.data.title || '');
            }
        },
        error: function(error) {
         console.log(error);
        }
    });
}
</script>
