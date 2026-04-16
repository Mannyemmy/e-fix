<div class="d-flex align-items-center gap-2">
    <a href="javascript:void(0)" class="flag-message" data-id="{{ $row->id }}" title="Flag/Unflag">
        <i class="fas fa-flag {{ $row->is_flagged ? 'text-warning' : 'text-muted' }}"></i>
    </a>
    <a href="{{ route('chat-monitor.conversation', ['senderId' => $row->sender_id, 'receiverId' => $row->receiver_id]) }}" title="View Conversation">
        <i class="fas fa-comments text-primary"></i>
    </a>
</div>
