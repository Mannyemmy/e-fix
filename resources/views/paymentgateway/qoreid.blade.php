{{ Form::model($payment_data, ['method' => 'POST','route' => ['paymentsettingsUpdates'],'enctype'=>'multipart/form-data','data-toggle'=>'validator']) }}

{{ Form::hidden('id', null, array('placeholder' => 'id','class' => 'form-control')) }}
{{ Form::hidden('type', $tabpage, array('placeholder' => 'id','class' => 'form-control')) }}
<div class="row">
    <div class="form-group col-md-12">
        <label for="enable_qoreid">{{__('messages.payment_on',['gateway'=>__('messages.qoreid')])}}</label>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" name="status" id="enable_qoreid" {{!empty($payment_data->status) ? 'checked' : '' }}>
            <label class="custom-control-label" for="enable_qoreid"></label>
        </div>
        <small class="form-text text-muted">Required for face + ID liveness checks before bank account creation.</small>
    </div>
</div>
<div class="row" id='enable_qoreid_payment'>
    <div class="form-group col-md-12">
        <label class="form-control-label">{{__('messages.payment_option',['gateway'=>__('messages.qoreid')])}}</label><br/>
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
        <small class="form-text text-muted">Test and live credentials are stored separately so you can flip modes without re-typing.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('title',trans('messages.gateway_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('title',old('title'),['id'=>'title','placeholder' => 'QoreID','class' =>'form-control']) }}
        <small class="help-block with-errors text-danger"></small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('client_id', 'Client ID <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        {{ Form::text('client_id',old('client_id'),['id'=>'client_id','placeholder' => 'e.g. 10OM313A3F1RPL9FPEC6','class' =>'form-control']) }}
        <small class="form-text text-muted">Public client id — embedded in the verification page served to mobile clients. Find it in QoreID dashboard → Settings → API Keys.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('secret_key', 'Secret Key',['class'=>'form-control-label'], false ) }}
        <span id="secret_key_badge" class="badge badge-secondary" style="display:none;margin-left:6px;">Saved</span>
        {{ Form::password('secret_key',['id'=>'secret_key','placeholder' => 'Leave blank to keep the saved value','class' =>'form-control']) }}
        <small class="form-text text-muted">Never exposed to clients. Used for server-to-server REST calls (if enabled later). Leave blank to keep the existing value.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('webhook_secret', 'Webhook Secret <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
        <span id="webhook_secret_badge" class="badge badge-secondary" style="display:none;margin-left:6px;">Saved</span>
        {{ Form::password('webhook_secret',['id'=>'webhook_secret','placeholder' => 'Leave blank to keep the saved value','class' =>'form-control']) }}
        <small class="form-text text-muted">Used to verify <code>X-QoreID-Signature</code> on inbound webhooks at <code>/api/qoreid/webhook</code>. Leave blank to keep the existing value.</small>
    </div>
    <div class="form-group col-md-12">
        {{ Form::label('sdk_url', 'SDK URL',['class'=>'form-control-label'], false ) }}
        {{ Form::text('sdk_url',old('sdk_url'),['id'=>'sdk_url','placeholder' => 'https://dashboard.qoreid.com/qoreid-sdk/qoreid.js','class' =>'form-control']) }}
        <small class="form-text text-muted">Defaults to QoreID's hosted SDK. Change only if QoreID asks you to.</small>
    </div>
    <div class="form-group col-md-12">
        <div class="alert alert-info">
            <strong>Register the webhook in QoreID dashboard:</strong>
            <code>{{ url('/api/qoreid/webhook') }}</code> using the secret above.
        </div>
    </div>
</div>
{{ Form::submit(__('messages.save'), ['class'=>"btn btn-md btn-primary float-md-right"]) }}
{{ Form::close() }}
<script>
var enable_qoreid = $("input[name='status']").prop('checked');
checkPaymentTabOption(enable_qoreid);

var get_value = $('input[name="is_test"]:checked').data("type");
if(get_value){
    getConfig(get_value);
}

$('#enable_qoreid').change(function(){
    value = $(this).prop('checked') == true ? true : false;
    checkPaymentTabOption(value);
});
$('.is_test').change(function(){
    type = $(this).data("type");
    getConfig(type);
});

function checkPaymentTabOption(value){
    if(value == true){
        $('#enable_qoreid_payment').removeClass('d-none');
        $('#title').prop('required', true);
        $('#client_id').prop('required', true);
        $('#webhook_secret').prop('required', true);
    }else{
        $('#enable_qoreid_payment').addClass('d-none');
        $('#title').prop('required', false);
        $('#client_id').prop('required', false);
        $('#webhook_secret').prop('required', false);
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
                $('#client_id').val(obj.client_id || '');
                $('#sdk_url').val(obj.sdk_url || '');
                $('#title').val(response.data.title || '');
                // Secret + webhook secret are intentionally NOT echoed
                // back. Instead show a "Saved" badge so the admin knows a
                // value is stored. We do NOT clear what the admin has
                // already typed — blanking on mode-switch was the
                // recurring "my changes don't save" bug because clicking
                // the Test/Live radio would silently wipe an unsaved
                // edit. Now the typed value persists across mode toggles
                // and only the badge reflects what's stored.
                // The server scrubs the real secret values before
                // sending and just tells us "is something saved?". This
                // avoids leaking the saved secret over the wire / into
                // browser dev tools.
                if (obj.secret_key_set) {
                    $('#secret_key_badge').show();
                } else {
                    $('#secret_key_badge').hide();
                }
                if (obj.webhook_secret_set) {
                    $('#webhook_secret_badge').show();
                } else {
                    $('#webhook_secret_badge').hide();
                }
            }
        },
        error: function(error) {
         console.log(error);
        }
    });
}
</script>
