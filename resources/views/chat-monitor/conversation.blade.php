<x-master-layout>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-3">
                            <h5 class="font-weight-bold">{{ $pageTitle }}</h5>
                            <a href="{{ route('chat-monitor.index') }}" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to All Messages
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sender Info -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            @if($sender->profile_image)
                                <img src="{{ asset($sender->profile_image) }}" class="rounded-circle" width="60" height="60" alt="Sender">
                            @else
                                <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                    <span class="text-white font-weight-bold">{{ strtoupper(substr($sender->first_name ?? $sender->display_name, 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>
                        <h6>{{ $sender->display_name ?? $sender->first_name . ' ' . $sender->last_name }}</h6>
                        <small class="text-muted">{{ $sender->email }}</small><br>
                        <span class="badge badge-info mt-1">{{ ucfirst($sender->user_type) }}</span>
                        @if($sender->contact_number)
                            <br><small class="mt-1">{{ $sender->contact_number }}</small>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;" id="chat-container">
                        @forelse($messages as $msg)
                            <div class="mb-3 p-2 rounded {{ $msg->sender_id == $sender->id ? 'bg-light ml-5' : 'bg-white mr-5 border' }} {{ $msg->is_blocked ? 'border-danger' : '' }} {{ $msg->is_flagged ? 'border-warning' : '' }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <strong class="{{ $msg->sender_id == $sender->id ? 'text-primary' : 'text-success' }}">
                                        {{ $msg->sender_id == $sender->id ? ($sender->display_name ?? $sender->first_name) : ($receiver->display_name ?? $receiver->first_name) }}
                                    </strong>
                                    <div class="d-flex align-items-center gap-1">
                                        @if($msg->is_flagged)
                                            <span class="badge badge-warning badge-sm">Flagged</span>
                                        @endif
                                        @if($msg->is_blocked)
                                            <span class="badge badge-danger badge-sm">Blocked</span>
                                        @endif
                                        <a href="javascript:void(0)" class="flag-msg ml-1" data-id="{{ $msg->id }}">
                                            <i class="fas fa-flag fa-sm {{ $msg->is_flagged ? 'text-warning' : 'text-muted' }}"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="mt-1">
                                    @if($msg->message_type !== 'TEXT')
                                        <span class="badge badge-info badge-sm">{{ $msg->message_type }}</span>
                                    @endif
                                    <p class="mb-0">{{ $msg->message }}</p>
                                </div>
                                <small class="text-muted">{{ $msg->created_at->format('M d, Y h:i A') }}</small>
                                @if($msg->blocked_reason)
                                    <br><small class="text-danger"><i class="fas fa-ban"></i> {{ $msg->blocked_reason }}</small>
                                @endif
                                @if($msg->flag_reason)
                                    <br><small class="text-warning"><i class="fas fa-flag"></i> {{ $msg->flag_reason }}</small>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <p>No messages found in this conversation.</p>
                            </div>
                        @endforelse
                    </div>
                    @if($messages->hasPages())
                        <div class="card-footer">
                            {{ $messages->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Receiver Info -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            @if($receiver->profile_image)
                                <img src="{{ asset($receiver->profile_image) }}" class="rounded-circle" width="60" height="60" alt="Receiver">
                            @else
                                <div class="rounded-circle bg-success d-inline-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                    <span class="text-white font-weight-bold">{{ strtoupper(substr($receiver->first_name ?? $receiver->display_name, 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>
                        <h6>{{ $receiver->display_name ?? $receiver->first_name . ' ' . $receiver->last_name }}</h6>
                        <small class="text-muted">{{ $receiver->email }}</small><br>
                        <span class="badge badge-info mt-1">{{ ucfirst($receiver->user_type) }}</span>
                        @if($receiver->contact_number)
                            <br><small class="mt-1">{{ $receiver->contact_number }}</small>
                        @endif
                    </div>
                </div>

                <!-- Conversation Stats -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="font-weight-bold">Conversation Stats</h6>
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between py-1">
                                <span>Total Messages</span>
                                <strong>{{ $messages->total() }}</strong>
                            </li>
                            <li class="d-flex justify-content-between py-1">
                                <span>Flagged</span>
                                <strong class="text-warning">{{ $messages->where('is_flagged', true)->count() }}</strong>
                            </li>
                            <li class="d-flex justify-content-between py-1">
                                <span>Blocked</span>
                                <strong class="text-danger">{{ $messages->where('is_blocked', true)->count() }}</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Scroll to bottom of chat
        document.addEventListener('DOMContentLoaded', function() {
            var chatContainer = document.getElementById('chat-container');
            chatContainer.scrollTop = chatContainer.scrollHeight;
        });

        // Flag individual message in conversation view
        $(document).on('click', '.flag-msg', function() {
            var id = $(this).data('id');
            var reason = prompt('Enter reason for flagging (optional):');
            if (reason !== null) {
                $.ajax({
                    url: '/chat-monitor/flag/' + id,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        flag_reason: reason
                    },
                    success: function() {
                        location.reload();
                    }
                });
            }
        });
    </script>
</x-master-layout>
