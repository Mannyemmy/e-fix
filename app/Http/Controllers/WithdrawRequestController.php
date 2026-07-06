<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WithdrawRequest;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Traits\NotificationTrait;

class WithdrawRequestController extends Controller
{
    use NotificationTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $filter = [
            'status' => $request->status,
        ];
        $pageTitle = __('messages.list_form_title', ['form' => __('messages.withdraw_request_list')]);
        $auth_user = authSession();
        $assets = ['datatable'];

        return view('withdraw-request.view', compact('pageTitle', 'auth_user', 'assets', 'filter'));
    }

    public function index_data(DataTables $datatable, Request $request)
    {
        $query = WithdrawRequest::with('user')->orderBy('id', 'desc');

        $filter = $request->filter;

        if (isset($filter) && isset($filter['column_status']) && $filter['column_status'] !== '') {
            $query->where('status', $filter['column_status']);
        }

        return $datatable->eloquent($query)
            ->editColumn('user_id', function ($row) {
                if ($row->user) {
                    return '<div class="text-start"><h6 class="m-0">' . e($row->user->display_name) . '</h6><span>' . e($row->user->email ?? '-') . '</span></div>';
                }
                return '-';
            })
            ->filterColumn('user_id', function ($q, $keyword) {
                $q->whereHas('user', function ($sq) use ($keyword) {
                    $sq->where('display_name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%');
                });
            })
            ->editColumn('amount', function ($row) {
                return getPriceFormat($row->amount);
            })
            ->addColumn('bank_detail', function ($row) {
                return e($row->bank_name) . '<br><small class="text-muted">' . e($row->account_number) . ' - ' . e($row->account_name) . '</small>';
            })
            ->editColumn('status', function ($row) {
                switch ((int) $row->status) {
                    case WithdrawRequest::STATUS_APPROVED:
                        return '<span class="badge badge-success">' . __('messages.approved') . '</span>';
                    case WithdrawRequest::STATUS_REJECTED:
                        return '<span class="badge badge-danger">' . __('messages.rejected') . '</span>';
                    default:
                        return '<span class="badge badge-warning">' . __('messages.pending') . '</span>';
                }
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at;
            })
            ->addColumn('action', function ($row) {
                return view('withdraw-request.action', ['withdrawRequest' => $row])->render();
            })
            ->addIndexColumn()
            ->rawColumns(['user_id', 'bank_detail', 'status', 'action'])
            ->toJson();
    }

    /**
     * Approve a pending withdrawal request. The wallet was already debited
     * when the request was created, so no wallet amount change is needed
     * here — this just marks the request as paid out (manually, outside
     * the system) by admin.
     */
    public function approve(Request $request, $id)
    {
        if (demoUserPermission()) {
            return response()->json(['status' => false, 'message' => __('messages.demo_permission_denied')]);
        }

        $withdrawRequest = WithdrawRequest::find($id);

        if (!$withdrawRequest) {
            return response()->json(['status' => false, 'message' => __('messages.msg_fail_to_delete', ['item' => __('messages.withdraw_request')])]);
        }

        if ((int) $withdrawRequest->status !== WithdrawRequest::STATUS_PENDING) {
            return response()->json(['status' => false, 'message' => __('messages.withdraw_request_already_processed')]);
        }

        $withdrawRequest->status = WithdrawRequest::STATUS_APPROVED;
        if ($request->filled('admin_note')) {
            $withdrawRequest->admin_note = $request->admin_note;
        }
        $withdrawRequest->save();

        $wallet = Wallet::where('user_id', $withdrawRequest->user_id)->first();
        if ($wallet) {
            $activity_data = [
                'activity_type' => 'withdraw_request_approved',
                'wallet' => $wallet,
                'withdraw_amount' => $withdrawRequest->amount,
                'withdraw_request_id' => $withdrawRequest->id,
            ];
            $this->sendNotification($activity_data);
        }

        return response()->json(['status' => true, 'message' => __('messages.withdraw_request_approved_msg')]);
    }

    /**
     * Reject a pending withdrawal request. Refunds the previously debited
     * amount back to the customer's wallet and records the admin's reason.
     */
    public function reject(Request $request, $id)
    {
        if (demoUserPermission()) {
            return response()->json(['status' => false, 'message' => __('messages.demo_permission_denied')]);
        }

        $request->validate([
            'admin_note' => 'required|string',
        ]);

        $withdrawRequest = WithdrawRequest::find($id);

        if (!$withdrawRequest) {
            return response()->json(['status' => false, 'message' => __('messages.msg_fail_to_delete', ['item' => __('messages.withdraw_request')])]);
        }

        if ((int) $withdrawRequest->status !== WithdrawRequest::STATUS_PENDING) {
            return response()->json(['status' => false, 'message' => __('messages.withdraw_request_already_processed')]);
        }

        DB::transaction(function () use ($withdrawRequest, $request) {
            $wallet = Wallet::where('user_id', $withdrawRequest->user_id)->first();

            if ($wallet) {
                $wallet->amount = $wallet->amount + $withdrawRequest->amount;
                $wallet->save();

                $activity_data = [
                    'activity_type' => 'withdraw_request_rejected',
                    'wallet' => $wallet,
                    'withdraw_amount' => $withdrawRequest->amount,
                    'withdraw_request_id' => $withdrawRequest->id,
                ];
                $this->sendNotification($activity_data);
            }

            $withdrawRequest->status = WithdrawRequest::STATUS_REJECTED;
            $withdrawRequest->admin_note = $request->admin_note;
            $withdrawRequest->save();
        });

        return response()->json(['status' => true, 'message' => __('messages.withdraw_request_rejected_msg')]);
    }

    /**
     * Not used — withdraw requests are created by customers via the API,
     * not from the admin panel. Present only so the full resource route
     * set registers cleanly.
     */
    public function create()
    {
        abort(404);
    }

    public function store(Request $request)
    {
        abort(404);
    }

    public function show($id)
    {
        abort(404);
    }

    public function edit($id)
    {
        abort(404);
    }

    public function update(Request $request, $id)
    {
        abort(404);
    }

    public function destroy($id)
    {
        abort(404);
    }
}
