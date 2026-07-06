<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use App\Models\UserBankDetail;
use Illuminate\Support\Facades\DB;
use App\Traits\NotificationTrait;

class WithdrawRequestController extends Controller
{
    use NotificationTrait;

    /**
     * Submit a new withdrawal request. Debits the wallet immediately and
     * saves/updates the customer's bank details for reuse next time.
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
        ]);

        $user = auth()->user();
        $amount = (float) $request->amount;

        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet || $wallet->amount < $amount) {
            return comman_custom_response([
                'status' => false,
                'message' => __('messages.insufficient_wallet_balance'),
            ]);
        }

        $withdrawRequest = DB::transaction(function () use ($request, $user, $amount, $wallet) {
            $wallet->amount = $wallet->amount - $amount;
            $wallet->save();

            UserBankDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bank_name' => $request->bank_name,
                    'account_number' => $request->account_number,
                    'account_name' => $request->account_name,
                ]
            );

            $withdrawRequest = WithdrawRequest::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name' => $request->account_name,
                'status' => WithdrawRequest::STATUS_PENDING,
            ]);

            $activity_data = [
                'activity_type' => 'withdraw_request_debit',
                'wallet' => $wallet,
                'withdraw_amount' => $amount,
                'withdraw_request_id' => $withdrawRequest->id,
            ];
            $this->sendNotification($activity_data);

            return $withdrawRequest;
        });

        $response = [
            'status' => true,
            'message' => __('messages.save_form', ['form' => __('messages.withdraw_request')]),
            'data' => $withdrawRequest,
        ];

        return comman_custom_response($response);
    }

    /**
     * The authenticated user's own withdraw requests, newest first.
     */
    public function index(Request $request)
    {
        $user_id = auth()->user()->id;

        $query = WithdrawRequest::where('user_id', $user_id)->orderBy('id', 'desc');

        $per_page = config('constant.PER_PAGE_LIMIT');

        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page === 'all') {
                $per_page = $query->count();
            }
        }

        $withdraw_requests = $query->paginate($per_page);

        $response = [
            'pagination' => [
                'total_items' => $withdraw_requests->total(),
                'per_page' => $withdraw_requests->perPage(),
                'currentPage' => $withdraw_requests->currentPage(),
                'totalPages' => $withdraw_requests->lastPage(),
                'from' => $withdraw_requests->firstItem(),
                'to' => $withdraw_requests->lastItem(),
                'next_page' => $withdraw_requests->nextPageUrl(),
                'previous_page' => $withdraw_requests->previousPageUrl(),
            ],
            'data' => $withdraw_requests->items(),
        ];

        return comman_custom_response($response);
    }

    /**
     * The authenticated user's saved bank details, for prefilling the
     * withdrawal request form. Returns null when none saved yet.
     */
    public function bankDetail(Request $request)
    {
        $user_id = auth()->user()->id;

        $bankDetail = UserBankDetail::where('user_id', $user_id)->first();

        return comman_custom_response([
            'data' => $bankDetail,
        ]);
    }
}
