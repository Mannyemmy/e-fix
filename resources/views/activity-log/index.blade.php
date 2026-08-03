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
                            <h5 class="font-weight-bold">{{ $pageTitle ?? __('messages.activity_log') }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-primary">{{ $stats['signups_today'] }}</h3>
                        <p class="mb-0">{{ __('messages.signups_today') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-danger">{{ $stats['failed_logins_today'] }}</h3>
                        <p class="mb-0">{{ __('messages.failed_logins_today') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-info">{{ $stats['distinct_ips_today'] }}</h3>
                        <p class="mb-0">{{ __('messages.distinct_ips_today') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-warning">{{ $stats['suspicious_ips'] }}</h3>
                        <p class="mb-0">{{ __('messages.suspicious_ips') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row justify-content-between">
                <div class="d-flex justify-content-end flex-wrap gap-2">
                    <div class="datatable-filter ml-auto">
                        <select name="column_event" id="column_event" class="select2 form-control" data-filter="select" style="width: 100%">
                            <option value="">{{ __('messages.all_events') }}</option>
                            <option value="register">{{ __('messages.signup') }}</option>
                            <option value="login">{{ __('messages.login') }}</option>
                            <option value="login_failed">{{ __('messages.failed_login') }}</option>
                        </select>
                    </div>
                    <div class="datatable-filter ml-2">
                        <select name="column_source" id="column_source" class="select2 form-control" data-filter="select" style="width: 100%">
                            <option value="">{{ __('messages.all_sources') }}</option>
                            <option value="web">Web</option>
                            <option value="app">App</option>
                        </select>
                    </div>
                    <div class="datatable-filter ml-2">
                        <select name="column_suspicious" id="column_suspicious" class="select2 form-control" data-filter="select" style="width: 100%">
                            <option value="">{{ __('messages.all_ips') }}</option>
                            <option value="1">{{ __('messages.suspicious_only') }}</option>
                        </select>
                    </div>
                    <div class="input-group ml-2">
                        <span class="input-group-text" id="addon-wrapping"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control dt-search" placeholder="{{ __('messages.search_ip_email_device') }}" aria-label="Search" aria-controls="dataTableBuilder">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="datatable" class="table table-striped border"></table>
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
                order: [[5, 'desc']],
                dom: '<"row align-items-center"><"table-responsive my-3" rt><"row align-items-center" <"col-md-6" l><"col-md-6" p>><"clear">',
                ajax: {
                    "type": "GET",
                    "url": '{{ route("activity-log.index_data") }}',
                    "data": function(d) {
                        d.search = { value: $('.dt-search').val() };
                        d.filter = {
                            column_event: $('#column_event').val(),
                            column_source: $('#column_source').val(),
                            column_suspicious: $('#column_suspicious').val()
                        }
                    },
                },
                columns: [
                    { data: 'event',      name: 'event',      title: "{{ __('messages.event') }}" },
                    { data: 'account',    name: 'account',    title: "{{ __('messages.account') }}", orderable: false },
                    { data: 'ip',         name: 'ip_address', title: "{{ __('messages.ip_address') }}" },
                    { data: 'location',   name: 'location',   title: "{{ __('messages.location') }}", orderable: false, searchable: false },
                    { data: 'device',     name: 'device',     title: "{{ __('messages.device') }}", orderable: false },
                    { data: 'created_at', name: 'created_at', title: "{{ __('messages.date') }}" }
                ]
            });

            $('#column_event, #column_source, #column_suspicious').on('change', function () {
                window.renderedDataTable.draw();
            });

            $('.dt-search').on('keyup', function () {
                window.renderedDataTable.draw();
            });
        });
    </script>
</x-master-layout>
