@if((int) $withdrawRequest->status === \App\Models\WithdrawRequest::STATUS_PENDING)
<div class="d-flex align-items-center gap-2">
    <a href="javascript:void(0)" class="mr-2 approve-withdraw-request" data-id="{{ $withdrawRequest->id }}" title="{{ __('messages.approved') }}">
        <i class="fas fa-check-circle text-success"></i>
    </a>
    <a href="javascript:void(0)" class="reject-withdraw-request" data-id="{{ $withdrawRequest->id }}" title="{{ __('messages.rejected') }}">
        <i class="fas fa-times-circle text-danger"></i>
    </a>
</div>
@else
<span class="text-muted">-</span>
@endif
