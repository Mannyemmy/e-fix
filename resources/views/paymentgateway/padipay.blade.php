{{ Form::model($payment_data, ['method' => 'POST','route' => ['paymentsettingsUpdates'],'enctype'=>'multipart/form-data','data-toggle'=>'validator']) }}

{{ Form::hidden('id', null, array('placeholder' => 'id','class' => 'form-control')) }}
{{ Form::hidden('type', $tabpage, array('placeholder' => 'id','class' => 'form-control')) }}
<div class="row">
    <div class="form-group col-md-12">
        <label for="enable_padipay">{{__('messages.payment_on',['gateway'=>__('messages.padipay')])}}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" name="status" id="enable_padipay" {{!empty($payment_data->status) ? 'checked' : '' }}>
            <label class="custom-control-label" for="enable_padipay"></label>
        </div>
        <small class="form-text text-muted">Enable PadiPay for bank transfer payments</small>
    </div>
</div>
<div class="row" id='enable_padipay_payment'>
    <div class="form-group col-md-12">
        <label class="form-control-label">{{__('messages.payment_option',['gateway'=>__('messages.padipay')])}}</label><br/>
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
        <small class="form-text text-muted"></small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('title',trans('messages.gateway_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('title',old('title'),['id'=>'title','placeholder' => trans('messages.title'),'class' =>'form-control']) }}
        <small class="help-block with-errors text-danger"></small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('external_api_url', 'External API URL <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('external_api_url',old('external_api_url'),['id'=>'external_api_url','placeholder' => 'https://us-central1-card-app-829ee.cloudfunctions.net/','class' =>'form-control']) }}
        <small class="help-block with-errors text-danger"></small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('external_api_key', 'External API Key <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('external_api_key',old('external_api_key'),['id'=>'external_api_key','placeholder' => 'm_7Xq9Lp2Vd8Nz4Kc1Ty6Rb3Hw0Fs','class' =>'form-control']) }}
        <small class="help-block with-errors text-danger"></small>
    </div>
</div>
{{ Form::submit(__('messages.save'), ['class'=>"btn btn-md btn-primary float-md-right"]) }}
{{ Form::close() }}
<script>
var enable_padipay = $("input[name='status']").prop('checked');
checkPaymentTabOption(enable_padipay);

var get_value = $('input[name="is_test"]:checked').data("type");
if(get_value){
    getConfig(get_value);
}

$('#enable_padipay').change(function(){
    value = $(this).prop('checked') == true ? true : false;
    checkPaymentTabOption(value);
});
$('.is_test').change(function(){
    type = $(this).data("type");
    getConfig(type);
});

function checkPaymentTabOption(value){
    if(value == true){
        $('#enable_padipay_payment').removeClass('d-none');
        $('#title').prop('required', true);
        $('#external_api_url').prop('required', true);
        $('#external_api_key').prop('required', true);
    }else{
        $('#enable_padipay_payment').addClass('d-none');
        $('#title').prop('required', false);
        $('#external_api_url').prop('required', false);
        $('#external_api_key').prop('required', false);
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
                $('#external_api_url').val(obj.external_api_url || '');
                $('#external_api_key').val(obj.external_api_key || '');
                $('#title').val(response.data.title || '');
            }
        },
        error: function(error) {
         console.log(error);
        }
    });
}
</script>