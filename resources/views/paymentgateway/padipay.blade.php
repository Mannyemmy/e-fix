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
        {{ Form::label('title',trans('messages.gateway_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('title',old('title'),['placeholder' => trans('messages.title'),'class' =>'form-control']) }}
        <small class="help-block with-errors text-danger"></small>
    </div>
</div>
{{ Form::submit(__('messages.save'), ['class'=>"btn btn-md btn-primary float-md-right"]) }}
{{ Form::close() }}
<script>
var enable_padipay = $("input[name='status']").prop('checked');
checkPaymentTabOption(enable_padipay);

$('#enable_padipay').change(function(){
    value = $(this).prop('checked') == true ? true : false;
    checkPaymentTabOption(value);
});
function checkPaymentTabOption(value){
    if(value == true){
        $('#enable_padipay_payment').removeClass('d-none');
        $('#title').prop('required', true);
    }else{
        $('#enable_padipay_payment').addClass('d-none');
        $('#title').prop('required', false);
    }
}
</script>