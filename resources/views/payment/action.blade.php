<?php
$auth_user= authSession();
?>
{{-- {{ $earningData->id}} --}}
{{ Form::open(['route' => ['payment.destroy',$payment->id], 'method' => 'delete','data--submit'=>'payment'.$payment->id]) }}
<div class="d-flex justify-content-end align-items-center">
@if(auth()->user()->hasAnyRole(['admin']) && $payment->payment_status !== 'paid' && \App\Models\PaymentHistory::where('payment_id', $payment->id)->where('status', 'pending_by_admin')->exists())
    <a class="mr-3" href="{{ route('cash.approve', $payment->id) }}"
        onclick="return confirm('{{ __('messages.approve') }} {{ getPriceFormat((float) $payment->total_amount) }}?')"
        title="{{ __('messages.approve') }}">
        <i class="fas fa-check-circle text-success"></i>
    </a>
@endif
@if(auth()->user()->hasAnyRole(['admin']))
    <a class="mr-3" href="{{ route('payment.destroy', $payment->id) }}" data--submit="payment{{$payment->id}}" 
        data--confirmation='true' 
        data--ajax="true"
        data-datatable="reload"
        data-title="{{ __('messages.delete_form_title',['form'=>  __('messages.payment') ]) }}"
        title="{{ __('messages.delete_form_title',['form'=>  __('messages.payment') ]) }}"
        data-message='{{ __("messages.delete_msg") }}'>
        <i class="far fa-trash-alt text-danger"></i>
    </a>
@endif
</div>
{{ Form::close() }}