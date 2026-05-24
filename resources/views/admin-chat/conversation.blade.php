<x-master-layout>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <a href="{{ route('admin-chat.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                </a>
                                <div>
                                    @if(!empty($userData['profile_image']))
                                        <img src="{{ $userData['profile_image'] }}" class="rounded-circle" width="40" height="40" alt="">
                                    @else
                                        <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center text-white font-weight-bold" style="width:40px;height:40px;">
                                            {{ strtoupper(substr($userData['display_name'] ?? $userData['first_name'] ?? 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <h5 class="font-weight-bold mb-0">{{ $userData['display_name'] ?? $userData['first_name'] ?? 'User' }} {{ $userData['last_name'] ?? '' }}</h5>
                                    <small class="text-muted">{{ $userData['email'] ?? '' }}</small>
                                </div>
                            </div>
                            <span id="online-status" class="badge badge-secondary">Checking...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body" style="height: 500px; overflow-y: auto; display: flex; flex-direction: column;" id="chat-messages">
                        <div id="messages-container" style="flex: 1;">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <p>Loading messages...</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <form id="message-form" class="d-flex gap-2">
                            @csrf
                            <input type="hidden" name="receiver_id" value="{{ $userId }}">
                            <input type="text" name="message" class="form-control" placeholder="Type your message..." required autocomplete="off">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const adminUid = '{{ $adminUid }}';
        const userId = '{{ $userId }}';

        function loadMessages() {
            fetch('{{ route("admin-chat.messages", ["userId" => $userId]) }}')
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('messages-container');
                    if (data.messages && data.messages.length > 0) {
                        let html = '';
                        data.messages.forEach(msg => {
                            const isMe = msg.is_me;
                            const time = msg.createdAt ? new Date(msg.createdAt).toLocaleString() : '';
                            const align = isMe ? 'text-right' : 'text-left';
                            const bg = isMe ? 'bg-primary text-white' : 'bg-light';
                            const ml = isMe ? 'ml-5' : 'mr-5';

                            html += `
                                <div class="mb-3 ${align}">
                                    <div class="d-inline-block p-3 rounded ${bg} ${ml}" style="max-width: 75%; word-wrap: break-word;">
                                        <p class="mb-0">${escapeHtml(msg.message || '')}</p>
                                        <small class="${isMe ? 'text-white-50' : 'text-muted'}">${time}</small>
                                    </div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <p>No messages yet. Send a message to start the conversation.</p>
                            </div>
                        `;
                    }

                    // Scroll to bottom
                    const chatContainer = document.getElementById('chat-messages');
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                })
                .catch(err => console.error('Error loading messages:', err));
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Send message
        document.getElementById('message-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const sendBtn = form.querySelector('button[type="submit"]');
            const input = form.querySelector('input[name="message"]');

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            fetch('{{ route("admin-chat.send") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status) {
                    input.value = '';
                    loadMessages();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                alert('Failed to send message');
                console.error(err);
            })
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
            });
        });

        // Poll for new messages every 3 seconds
        loadMessages();
        setInterval(loadMessages, 3000);

        // Check online status
        function checkOnlineStatus() {
            // Firestore REST doesn't support real-time streaming easily
            // We'll just show a static indicator
            document.getElementById('online-status').textContent = '';
            document.getElementById('online-status').className = 'badge badge-secondary';
        }
        checkOnlineStatus();
    </script>
</x-master-layout>
