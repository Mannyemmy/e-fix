<x-master-layout>
<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
</head>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-3">
                            <h5 class="font-weight-bold">{{ $pageTitle ?? 'Chat Monitoring' }}</h5>
                            <div class="d-flex gap-2">
                                <a href="{{ route('chat-monitor.patterns') }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-shield-alt"></i> Blocked Patterns
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-3" id="chat-stats">
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-primary" id="stat-total">-</h3>
                        <p class="mb-0">Total Messages</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-warning" id="stat-flagged">-</h3>
                        <p class="mb-0">Flagged Messages</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-danger" id="stat-blocked">-</h3>
                        <p class="mb-0">Blocked Messages</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-info" id="stat-patterns">-</h3>
                        <p class="mb-0">Active Patterns</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row justify-content-between">
                <div>
                    <div class="col-md-12">
                        <form action="{{ route('chat-monitor.bulk-action') }}" id="quick-action-form" class="form-disabled d-flex gap-3 align-items-center">
                            @csrf
                            <select name="action_type" class="form-control select2" id="quick-action-type" style="width:100%" disabled>
                                <option value="">{{ __('messages.no_action') }}</option>
                                <option value="flag">Flag Selected</option>
                                <option value="unflag">Unflag Selected</option>
                                <option value="delete">{{ __('messages.delete') }}</option>
                            </select>
                            <button id="quick-action-apply" class="btn btn-primary" data-ajax="true"
                                data--submit="{{ route('chat-monitor.bulk-action') }}"
                                data-datatable="reload" data-confirmation='true'
                                data-title="Chat Messages"
                                title="Chat Messages"
                                data-message='Do you want to perform this action?' disabled>{{ __('messages.apply') }}</button>
                        </form>
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <div class="datatable-filter ml-auto">
                        <select name="column_status" id="column_status" class="select2 form-control" data-filter="select" style="width: 100%">
                            <option value="">All Messages</option>
                            <option value="flagged" {{ ($filter['status'] ?? '') == 'flagged' ? 'selected' : '' }}>Flagged</option>
                            <option value="blocked" {{ ($filter['status'] ?? '') == 'blocked' ? 'selected' : '' }}>Blocked</option>
                        </select>
                    </div>
                    <div class="input-group ml-2">
                        <span class="input-group-text" id="addon-wrapping"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control dt-search" placeholder="Search messages, users..." aria-label="Search" aria-describedby="addon-wrapping" aria-controls="dataTableBuilder">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="datatable" class="table table-striped border">
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Flag Modal -->
    <div class="modal fade" id="flagModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Flag Message</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Reason for flagging</label>
                        <textarea class="form-control" id="flag-reason" rows="3" placeholder="Enter reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirm-flag">Flag Message</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            // Load stats
            $.get('{{ route("chat-monitor.stats") }}', function(data) {
                $('#stat-total').text(data.total_messages);
                $('#stat-flagged').text(data.flagged_messages);
                $('#stat-blocked').text(data.blocked_messages);
                $('#stat-patterns').text(data.active_patterns);
            });

            window.renderedDataTable = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                responsive: true,
                dom: '<"row align-items-center"><"table-responsive my-3" rt><"row align-items-center" <"col-md-6" l><"col-md-6" p>><"clear">',
                ajax: {
                    "type": "GET",
                    "url": '{{ route("chat-monitor.index_data") }}',
                    "data": function(d) {
                        d.search = {
                            value: $('.dt-search').val()
                        };
                        d.filter = {
                            column_status: $('#column_status').val()
                        }
                    },
                },
                columns: [
                    {
                        name: 'check',
                        data: 'check',
                        title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" data-type="chat" onclick="selectAllTable(this)">',
                        exportable: false,
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'sender_id',
                        name: 'sender_id',
                        title: "Sender"
                    },
                    {
                        data: 'receiver_id',
                        name: 'receiver_id',
                        title: "Receiver"
                    },
                    {
                        data: 'message',
                        name: 'message',
                        title: "Message"
                    },
                    {
                        data: 'status_badge',
                        name: 'status_badge',
                        title: "Status",
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        title: "Date"
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        title: "Action"
                    }
                ]
            });
        });

        // Search
        $('.dt-search').on('keyup', function() {
            window.renderedDataTable.draw();
        });

        // Filter
        $('#column_status').on('change', function() {
            window.renderedDataTable.draw();
        });

        // Quick action type change
        function resetQuickAction() {
            const actionValue = $('#quick-action-type').val();
            if (actionValue != '') {
                $('#quick-action-apply').removeAttr('disabled');
            } else {
                $('#quick-action-apply').attr('disabled', true);
            }
        }

        $('#quick-action-type').change(function() {
            resetQuickAction();
        });

        // Flag message
        var flagMessageId = null;
        $(document).on('click', '.flag-message', function() {
            flagMessageId = $(this).data('id');
            $('#flagModal').modal('show');
        });

        $('#confirm-flag').on('click', function() {
            if (flagMessageId) {
                $.ajax({
                    url: '/chat-monitor/flag/' + flagMessageId,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        flag_reason: $('#flag-reason').val()
                    },
                    success: function(response) {
                        $('#flagModal').modal('hide');
                        $('#flag-reason').val('');
                        window.renderedDataTable.draw();
                        // Refresh stats
                        $.get('{{ route("chat-monitor.stats") }}', function(data) {
                            $('#stat-flagged').text(data.flagged_messages);
                        });
                    }
                });
            }
        });

        $(document).on('click', '[data-ajax="true"]', function(e) {
            e.preventDefault();
            const button = $(this);
            const confirmation = button.data('confirmation');

            if (confirmation === 'true') {
                const message = button.data('message');
                if (confirm(message)) {
                    const submitUrl = button.data('submit');
                    const form = button.closest('form');
                    form.attr('action', submitUrl);
                    form.submit();
                }
            } else {
                const submitUrl = button.data('submit');
                const form = button.closest('form');
                form.attr('action', submitUrl);
                form.submit();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</x-master-layout>
