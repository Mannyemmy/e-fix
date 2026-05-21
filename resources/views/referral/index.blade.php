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
                            <h5 class="font-weight-bold">{{ $pageTitle ?? trans('messages.list') }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card bg-primary-light">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="mb-1 text-muted">{{ __('messages.total_referrals') }}</p>
                            <h4 class="mb-0 text-primary">{{ $totalReferrals }}</h4>
                        </div>
                        <div class="icon-box bg-primary rounded-circle p-3">
                            <i class="fas fa-users text-white fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card bg-warning-light">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="mb-1 text-muted">{{ __('messages.pending') }}</p>
                            <h4 class="mb-0 text-warning">{{ $pendingReferrals }}</h4>
                        </div>
                        <div class="icon-box bg-warning rounded-circle p-3">
                            <i class="fas fa-clock text-white fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card bg-success-light">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="mb-1 text-muted">{{ __('messages.completed') }}</p>
                            <h4 class="mb-0 text-success">{{ $completedReferrals }}</h4>
                        </div>
                        <div class="icon-box bg-success rounded-circle p-3">
                            <i class="fas fa-check-circle text-white fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="card bg-info-light">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="mb-1 text-muted">{{ __('messages.total_rewards') }}</p>
                            <h4 class="mb-0 text-info">{{ getPriceFormat($totalRewards) }}</h4>
                        </div>
                        <div class="icon-box bg-info rounded-circle p-3">
                            <i class="fas fa-gift text-white fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($topReferrers->count() > 0)
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">{{ __('messages.top_referrers') }}</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('messages.name') }}</th>
                            <th>{{ __('messages.email') }}</th>
                            <th>{{ __('messages.total_referred') }}</th>
                            <th>{{ __('messages.total_earned') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topReferrers as $index => $referrer)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $referrer->referrer ? $referrer->referrer->first_name . ' ' . $referrer->referrer->last_name : '-' }}</td>
                            <td>{{ $referrer->referrer->email ?? '-' }}</td>
                            <td><span class="badge badge-primary">{{ $referrer->total }}</span></td>
                            <td>{{ getPriceFormat($referrer->total_earned) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="row justify-content-between">
                <div>
                    <div class="col-md-12">
                      <form action="{{ route('referral.bulk-action') }}" id="quick-action-form" class="form-disabled d-flex gap-3 align-items-center">
                        @csrf
                      <select name="action_type" class="form-control select2" id="quick-action-type" style="width:100%" disabled>
                          <option value="">{{__('messages.no_action')}}</option>
                          <option value="change-status">{{__('messages.status')}}</option>
                          <option value="delete">{{__('messages.delete')}}</option>
                      </select>

                      <div class="select-status d-none quick-action-field" id="change-status-action" style="width:100%">
                          <select name="status" class="form-control select2" id="status" style="width:100%">
                              <option value="completed">{{__('messages.completed')}}</option>
                              <option value="pending">{{__('messages.pending')}}</option>
                          </select>
                      </div>
                      <button id="quick-action-apply" class="btn btn-primary" data-ajax="true"
                          data--submit="{{ route('referral.bulk-action') }}"
                          data-datatable="reload"
                          data-title="{{ __('messages.referral') }}"
                          data-message='{{ __("Do you want to perform this action?") }}' disabled>{{__('messages.apply')}}</button>
                    </form>
                  </div>
                </div>
                <div class="d-flex justify-content-end">
                    <div class="datatable-filter ml-auto">
                        <select name="column_status" id="column_status" class="select2 form-control" data-filter="select" style="width: 100%">
                            <option value="">{{__('messages.all')}}</option>
                            <option value="pending" {{$filter['status'] == 'pending' ? "selected" : ''}}>{{__('messages.pending')}}</option>
                            <option value="completed" {{$filter['status'] == 'completed' ? "selected" : ''}}>{{__('messages.completed')}}</option>
                        </select>
                    </div>
                    <div class="input-group ml-2">
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

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {

        window.renderedDataTable = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                responsive: true,
                dom: '<"row align-items-center"><"table-responsive my-3" rt><"row align-items-center" <"col-md-6" l><"col-md-6" p>><"clear">',
                ajax: {
                  "type"   : "GET",
                  "url"    : '{{ route("referral.index_data") }}',
                  "data"   : function( d ) {
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
                        title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" data-type="referral" onclick="selectAllTable(this)">',
                        exportable: false,
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'referrer',
                        name: 'referrer',
                        title: "{{__('messages.referrer')}}",
                        orderable: false
                    },
                    {
                        data: 'referred_user',
                        name: 'referred_user',
                        title: "{{__('messages.referred_user')}}",
                        orderable: false
                    },
                    {
                        data: 'referral_code',
                        name: 'referral_code',
                        title: "{{__('messages.referral_code')}}"
                    },
                    {
                        data: 'status',
                        name: 'status',
                        title: "{{__('messages.status')}}"
                    },
                    {
                        data: 'reward_amount',
                        name: 'reward_amount',
                        title: "{{__('messages.reward_amount')}}"
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        title: "{{__('messages.date')}}"
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        title: "{{__('messages.action')}}"
                    }
                ]

            });
      });

    function resetQuickAction () {
    const actionValue = $('#quick-action-type').val();
    if (actionValue != '') {
        $('#quick-action-apply').removeAttr('disabled');

        if (actionValue == 'change-status') {
            $('.quick-action-field').addClass('d-none');
            $('#change-status-action').removeClass('d-none');
        } else {
            $('.quick-action-field').addClass('d-none');
        }
    } else {
        $('#quick-action-apply').attr('disabled', true);
        $('.quick-action-field').addClass('d-none');
    }
  }

  $('#quick-action-type').change(function () {
    resetQuickAction()
  });

  $(document).on('update_quick_action', function() {

  })

    $(document).on('click', '[data-ajax="true"]', function (e) {
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
