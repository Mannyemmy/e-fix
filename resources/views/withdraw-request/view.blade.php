<x-master-layout>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h5 class="font-weight-bold">{{ $pageTitle ?? trans('messages.list') }}</h5>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-end flex-wrap gap-3 mb-3">
                            <div class="datatable-filter">
                                <select name="column_status" id="column_status" class="select2 form-control" data-filter="select" style="width: 100%">
                                    <option value="">{{ __('messages.all') }}</option>
                                    <option value="0" {{ ($filter['status'] ?? '') === '0' ? 'selected' : '' }}>{{ __('messages.pending') }}</option>
                                    <option value="1" {{ ($filter['status'] ?? '') === '1' ? 'selected' : '' }}>{{ __('messages.approved') }}</option>
                                    <option value="2" {{ ($filter['status'] ?? '') === '2' ? 'selected' : '' }}>{{ __('messages.rejected') }}</option>
                                </select>
                            </div>
                            <div class="input-group ml-2" style="max-width: 260px">
                                <span class="input-group-text" id="addon-wrapping"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control dt-search" placeholder="Search..." aria-label="Search" aria-describedby="addon-wrapping" aria-controls="dataTableBuilder">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="datatable" class="table table-striped border">
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectWithdrawRequestModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('messages.rejected') }} — {{ __('messages.withdraw_request') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ __('messages.reason') }}</label>
                        <textarea class="form-control" id="reject-admin-note" rows="3" placeholder="Enter reason for rejection..."></textarea>
                        <small class="text-danger reject-note-error d-none">This field is required.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="button" class="btn btn-danger" id="confirm-reject-withdraw-request">{{ __('messages.rejected') }}</button>
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
                    "url": '{{ route("withdraw-request.index_data") }}',
                    "data": function (d) {
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
                        data: 'user_id',
                        name: 'user_id',
                        title: "{{ __('messages.customer') }}"
                    },
                    {
                        data: 'amount',
                        name: 'amount',
                        title: "{{ __('messages.amount') }}"
                    },
                    {
                        data: 'bank_detail',
                        name: 'bank_detail',
                        title: "{{ __('messages.bank_name') }}",
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'status',
                        name: 'status',
                        title: "{{ __('messages.status') }}"
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        title: "{{ __('messages.created_at') }}"
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        title: "{{ __('messages.action') }}"
                    }
                ]
            });

            $('#column_status').on('change', function () {
                window.renderedDataTable.draw();
            });

            let rejectTargetId = null;

            $(document).on('click', '.approve-withdraw-request', function () {
                const id = $(this).data('id');
                if (!confirm('Do you want to approve this withdrawal request?')) {
                    return;
                }
                $.ajax({
                    url: '{{ url("withdraw-request-approve") }}/' + id,
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        window.renderedDataTable.draw(false);
                    },
                    error: function (xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Something went wrong.');
                    }
                });
            });

            $(document).on('click', '.reject-withdraw-request', function () {
                rejectTargetId = $(this).data('id');
                $('#reject-admin-note').val('');
                $('.reject-note-error').addClass('d-none');
                $('#rejectWithdrawRequestModal').modal('show');
            });

            $('#confirm-reject-withdraw-request').on('click', function () {
                const note = $('#reject-admin-note').val().trim();
                if (!note) {
                    $('.reject-note-error').removeClass('d-none');
                    return;
                }
                if (!rejectTargetId) {
                    return;
                }
                $.ajax({
                    url: '{{ url("withdraw-request-reject") }}/' + rejectTargetId,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        admin_note: note
                    },
                    success: function (response) {
                        $('#rejectWithdrawRequestModal').modal('hide');
                        window.renderedDataTable.draw(false);
                    },
                    error: function (xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Something went wrong.');
                    }
                });
            });
        });
    </script>
</x-master-layout>
