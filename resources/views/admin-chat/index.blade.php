<x-master-layout>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-3">
                            <h5 class="font-weight-bold">{{ $pageTitle ?? 'Live Chat' }}</h5>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin-chat.setup') }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-cog"></i> Setup
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(empty($adminUid ?? ''))
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                    <h5>Live Chat Not Configured</h5>
                    <p class="text-muted">Please configure the admin chat user ID to start receiving messages from users.</p>
                    <a href="{{ route('admin-chat.setup') }}" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Configure Now
                    </a>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="datatable" class="table table-striped border">
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if(!empty($adminUid ?? ''))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.renderedDataTable = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                responsive: true,
                dom: '<"row align-items-center"><"table-responsive my-3" rt><"row align-items-center" <"col-md-6" l><"col-md-6" p>><"clear">',
                ajax: {
                    type: "GET",
                    url: '{{ route("admin-chat.conversations_data") }}',
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', title: '#', orderable: false, searchable: false },
                    { data: 'user_info', name: 'user_info', title: 'User' },
                    { data: 'last_message_time', name: 'last_message_time', title: 'Last Message' },
                    { data: 'is_online', name: 'is_online', title: 'Status', orderable: false, searchable: false },
                    { data: 'action', name: 'action', title: 'Action', orderable: false, searchable: false },
                ],
                order: [[2, 'desc']],
            });
        });
    </script>
    @endif
</x-master-layout>
