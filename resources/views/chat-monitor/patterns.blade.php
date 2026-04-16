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
                            <h5 class="font-weight-bold">{{ $pageTitle ?? 'Blocked Chat Patterns' }}</h5>
                            <div class="d-flex gap-2">
                                <a href="{{ route('chat-monitor.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Chat Monitor
                                </a>
                                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#patternModal" id="add-pattern-btn">
                                    <i class="fa fa-plus-circle"></i> Add Pattern
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="font-weight-bold"><i class="fas fa-info-circle text-info"></i> How Message Blocking Works</h6>
            <p class="mb-1">Messages are checked against these patterns before being sent. If a match is found, the message is blocked and the user is notified.</p>
            <ul class="mb-0">
                <li><strong>Keyword:</strong> Simple text match (case-insensitive). Example: "give me your number"</li>
                <li><strong>Phone Number:</strong> Blocks messages containing phone patterns. Built-in detection is always active.</li>
                <li><strong>Regex:</strong> Advanced pattern matching for complex rules. Example: <code>\b\d{3}[-.]?\d{3}[-.]?\d{4}\b</code></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-3">
                <div class="input-group" style="max-width: 300px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control dt-search" placeholder="Search patterns..." aria-label="Search">
                </div>
            </div>
            <div class="table-responsive">
                <table id="datatable" class="table table-striped border">
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Pattern Modal -->
    <div class="modal fade" id="patternModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pattern-modal-title">Add Blocked Pattern</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pattern-id" value="">
                    <div class="form-group">
                        <label>Pattern Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="pattern-type">
                            <option value="keyword">Keyword</option>
                            <option value="phone_number">Phone Number Pattern</option>
                            <option value="regex">Regex Pattern</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pattern <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pattern-value" placeholder="e.g., give me your number">
                        <small class="text-muted" id="pattern-help">Enter a keyword or phrase to block</small>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" class="form-control" id="pattern-description" placeholder="Why this pattern is blocked">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-pattern">Save Pattern</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            window.renderedDataTable = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                responsive: true,
                dom: '<"row align-items-center"><"table-responsive my-3" rt><"row align-items-center" <"col-md-6" l><"col-md-6" p>><"clear">',
                ajax: {
                    "type": "GET",
                    "url": '{{ route("chat-monitor.patterns_data") }}',
                    "data": function(d) {
                        d.search = {
                            value: $('.dt-search').val()
                        };
                    },
                },
                columns: [
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        title: '#',
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'pattern',
                        name: 'pattern',
                        title: "Pattern"
                    },
                    {
                        data: 'pattern_type',
                        name: 'pattern_type',
                        title: "Type"
                    },
                    {
                        data: 'description',
                        name: 'description',
                        title: "Description"
                    },
                    {
                        data: 'is_active',
                        name: 'is_active',
                        title: "Active",
                        orderable: false,
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

        // Pattern type change help text
        $('#pattern-type').on('change', function() {
            var type = $(this).val();
            if (type === 'keyword') {
                $('#pattern-help').text('Enter a keyword or phrase to block');
            } else if (type === 'phone_number') {
                $('#pattern-help').text('Enter a phone number pattern to block');
            } else {
                $('#pattern-help').text('Enter a regex pattern (without delimiters)');
            }
        });

        // Add new pattern
        $('#add-pattern-btn').on('click', function() {
            $('#pattern-modal-title').text('Add Blocked Pattern');
            $('#pattern-id').val('');
            $('#pattern-value').val('');
            $('#pattern-type').val('keyword');
            $('#pattern-description').val('');
        });

        // Edit pattern
        $(document).on('click', '.edit-pattern', function() {
            $('#pattern-modal-title').text('Edit Blocked Pattern');
            $('#pattern-id').val($(this).data('id'));
            $('#pattern-value').val($(this).data('pattern'));
            $('#pattern-type').val($(this).data('type'));
            $('#pattern-description').val($(this).data('description'));
            $('#patternModal').modal('show');
        });

        // Save pattern
        $('#save-pattern').on('click', function() {
            var data = {
                _token: '{{ csrf_token() }}',
                id: $('#pattern-id').val(),
                pattern: $('#pattern-value').val(),
                pattern_type: $('#pattern-type').val(),
                description: $('#pattern-description').val(),
            };

            if (!data.pattern) {
                alert('Pattern is required');
                return;
            }

            $.ajax({
                url: '{{ route("chat-monitor.pattern.store") }}',
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.status) {
                        $('#patternModal').modal('hide');
                        window.renderedDataTable.draw();
                    }
                },
                error: function(xhr) {
                    alert('Error saving pattern: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            });
        });

        // Delete pattern
        $(document).on('click', '.delete-pattern', function() {
            if (confirm('Are you sure you want to delete this pattern?')) {
                var id = $(this).data('id');
                $.ajax({
                    url: '/chat-monitor/pattern/delete/' + id,
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.status) {
                            window.renderedDataTable.draw();
                        }
                    }
                });
            }
        });

        // Toggle pattern status
        $(document).on('change', '.change_status[data-type="blocked_pattern_status"]', function() {
            var id = $(this).data('id');
            $.ajax({
                url: '{{ route("chat-monitor.pattern.toggle") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id: id
                },
                success: function() {
                    // Status toggled
                }
            });
        });
    </script>
</x-master-layout>
