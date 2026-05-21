
<?php
    $auth_user = authSession();
?>
{{ Form::open(['route' => ['referral.destroy', $row->id], 'method' => 'delete','data--submit'=>'referral'.$row->id]) }}
<div class="d-flex justify-content-end align-items-center">
    @if($auth_user->can('referral delete'))
    <a class="mr-3" href="{{ route('referral.destroy', $row->id) }}"
        title="{{ __('messages.delete_form_title',['form' => __('messages.referral') ]) }}"
        data--submit="referral{{$row->id}}"
        data--confirmation='true'
        data--ajax="true"
        data-datatable="reload"
        data-title="{{ __('messages.delete_form_title',['form'=>  __('messages.referral') ]) }}"
        data-message='{{ __("messages.delete_msg") }}'>
        <i class="far fa-trash-alt text-danger"></i>
    </a>
    @endif
</div>
{{ Form::close() }}
