{{ Form::model($userdashboardsetting, ['method' => 'POST','route' => ['saveUserDashboardTypeSetting'],'enctype'=>'multipart/form-data','data-toggle'=>'validator']) }}

{{ Form::hidden('id', null, array('placeholder' => 'id','class' => 'form-control')) }}
{{ Form::hidden('page', $page, array('placeholder' => 'id','class' => 'form-control')) }}
<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            {{ Form::label('dashboard_type',__('messages.dashboard_type').' <span class="text-danger">*</span>',['class'=>'form-control-label'],false) }}
            {{ Form::select('dashboard_type',['dashboard' => __('messages.dashboard') ,'dashboard_1' => __('messages.dashboard_1') , 'dashboard_2' => __('messages.dashboard_2'), 'dashboard_3' => __('messages.dashboard_3'), 'dashboard_4' => __('messages.dashboard_4') ], $userdashboardsetting->value ?? null,[ 'class' =>'form-control select2js','required']) }}
        </div>
    </div>
     <div class="col-lg-12"> 
        <div class="form-group">
            <div class="col-md-offset-3 col-sm-12 ">
                {{ Form::submit(__('messages.save'), ['class'=>"btn btn-md btn-primary float-md-right"]) }}
            </div>
        </div>
     </div>
</div>
{{ Form::close() }}
