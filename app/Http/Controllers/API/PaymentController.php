<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\Wallet;
use App\Models\User;
use App\Models\PaymentHistory;
use App\Models\PaymentGateway;
use App\Http\Resources\API\PaymentResource;
use App\Http\Resources\API\PaymentHistoryResource;
use App\Http\Resources\API\GetCashPaymentHistoryResource;
use App\Traits\NotificationTrait;
use App\Http\Resources\API\PaymentGatewayResource;
use App\Models\Setting;
use App\Services\PaystackReconciler;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use NotificationTrait;


    public function savePayment(Request $request)
    {
        // Paystack charges happen on Paystack's side, independent of this
        // request reaching us — so unlike the other gateways below, we
        // don't just trust the client's word. We re-verify the reference
        // with Paystack itself before recording anything, and route
        // through the same reconciler the webhook uses so a duplicate
        // report (app + webhook both firing) can't double-credit anyone.
        if ($request->payment_type === 'paystack' && !empty($request->txn_id)) {
            return $this->savePaystackPayment($request);
        }

        $data = $request->all();
        $data['datetime'] = isset($request->datetime) ? date('Y-m-d H:i:s',strtotime($request->datetime)) : date('Y-m-d H:i:s');
        $result = Payment::create($data);
        $booking = Booking::find($request->booking_id);
        if(!empty($result) && $result->payment_status == 'advanced_paid'){
            $booking->advance_paid_amount  = $request->advance_payment_amount;
            $booking->status  = 'pending';
        }
        $booking->payment_id = $result->id;
        $booking->update();
        $status_code = 200;
        if($request->payment_type == 'wallet'){
            $wallet = Wallet::where('user_id',$booking->customer_id)->first();
            if($wallet !== null){
                $wallet_amount = $wallet->amount;
                if($wallet_amount >= $request->total_amount){
                    $wallet->amount = $wallet->amount - $request->total_amount;
                    $wallet->update();
                    $activity_data = [
                        'activity_type' => 'paid_for_booking',
                        'wallet' => $wallet,
                        'booking_id'=>$request->booking_id,
                        'booking_amount'=>$request->total_amount,
                    ];
                    $this->sendNotification($activity_data);

                }else{
                    $message = __('messages.wallent_balance_error');
                }
            }
        }
        $message = __('messages.payment_completed');
        $activity_data = [
            'activity_type' => 'payment_message_status',
            'payment_status'=>  str_replace("_"," ",ucfirst($data['payment_status'])),
            'booking_id' => $booking->id,
            'booking' => $booking,
        ];
        $this->sendNotification($activity_data);

        if($result->payment_status == 'failed')
        {
            $status_code = 400;
        }
        return comman_message_response($message,$status_code);
    }

    private function savePaystackPayment(Request $request)
    {
        $booking = Booking::find($request->booking_id);
        if (!$booking) {
            return comman_message_response('Booking not found.', 400);
        }

        $existing = Payment::where('txn_id', $request->txn_id)->where('payment_type', 'paystack')->first();
        if ($existing) {
            // Webhook already recorded this one — report success without double-crediting.
            return comman_message_response(__('messages.payment_completed'), 200);
        }

        $verified = PaystackReconciler::verify($request->txn_id);
        if (!$verified) {
            Log::error("savePaystackPayment: could not verify {$request->txn_id} for booking {$booking->id}");
            return comman_message_response('Could not verify this payment with Paystack.', 400);
        }

        $expectedKobo = (int) round(((float) $request->total_amount) * 100);
        $actualKobo = (int) ($verified['amount'] ?? 0);
        if (abs($expectedKobo - $actualKobo) > 100) {
            Log::error("savePaystackPayment: amount mismatch for booking {$booking->id}, expected {$expectedKobo} kobo got {$actualKobo} kobo");
            return comman_message_response('Amount does not match the verified Paystack transaction.', 400);
        }

        (new PaystackReconciler())->reconcile($booking, $verified);

        return comman_message_response(__('messages.payment_completed'), 200);
    }
    public function paymentList(Request $request)
    {
        $payment = Payment::myPayment()->with('booking');
        if($request->has('booking_id') && !empty($request->booking_id)){
            $payment->where('booking_id',$request->booking_id);
        }
        if($request->has('payment_type') && !empty($request->payment_type)){

            if($request->payment_type == 'cash'){
                $payment->where('payment_type',$request->payment_type);
            }
        }
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = PaymentResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);
    }

    public function transferPayment(Request $request){
        $sitesetup = Setting::where('type','site-setup')->where('key', 'site-setup')->first();
        $admin = json_decode($sitesetup->value);
        $data = $request->all();
        $auth_user = authSession();
        $user_id = $auth_user->id;

        date_default_timezone_set( $admin->time_zone ?? 'UTC');
        $data['datetime'] = date('Y-m-d H:i:s');

        if($data['action'] == config('constant.PAYMENT_HISTORY_ACTION.HANDYMAN_SEND_PROVIDER')){
            $data['text'] = __('messages.payment_transfer',
            ['from' => get_user_name($data['sender_id']),'to' => get_user_name($data['receiver_id']),'amount' => getPriceFormat((float)$data['total_amount']) ]);
        }
        if($data['action'] == config('constant.PAYMENT_HISTORY_ACTION.PROVIDER_APPROVED_CASH')){
            $data['text'] = __('messages.cash_approved',['amount' => getPriceFormat((float)$data['total_amount']),'name' => get_user_name($data['receiver_id']) ]);
        }
        if($data['action'] == config('constant.PAYMENT_HISTORY_ACTION.PROVIDER_SEND_ADMIN')){
            $data['text'] =  __('messages.payment_transfer',['from' => get_user_name($data['sender_id']),'to' => get_user_name(admin_id()),
            'amount' => getPriceFormat((float)$data['total_amount']) ]);
        }
        $result = \App\Models\PaymentHistory::create($data);

        if($data['action'] == 'provider_approved_cash' && $data['status'] == 'approved_by_provider' ){
            $get_parent_history =  \App\Models\PaymentHistory::where('id',$request->p_id)->first();
            $get_parent_history->status = 'approved_by_provider';
            $get_parent_history->update();

            $get_main_record =  \App\Models\PaymentHistory::where('id',$request->parent_id)->first();
            $get_main_record->status = 'approved_by_provider';
            $get_main_record->update();
        }
        if($data['action'] == 'provider_send_admin' && $data['status'] == 'pending_by_admin'){
            $get_parent_history =  \App\Models\PaymentHistory::where('id',$request->p_id)->first();
            $get_parent_history->status = 'pending_by_admin';
            $get_parent_history->update();
        }
        if($data['action'] == 'handyman_send_provider' && $data['status'] == 'pending_by_provider'){
            $get_parent_history =  \App\Models\PaymentHistory::where('id',$request->p_id)->first();
            $get_parent_history->status = 'send_to_provider';
            $get_parent_history->update();
        }
        $message = trans('messages.transfer');
        if($request->is('api/*')) {
            return comman_message_response($message);
		}
    }

    public function paymentHistory(Request $request){
        $booking_id = $request->booking_id;
        $payment = PaymentHistory::where('booking_id',$booking_id);

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = PaymentHistoryResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);

    }

    public function getCashPaymentHistory(Request $request){
        $payment_id = $request->payment_id;
        $payment = PaymentHistory::where('payment_id',$payment_id)->with('booking');

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = GetCashPaymentHistoryResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);

    }


    public function paymentDetail(Request $request){
        $auth_user = authSession();
        $user_id = $auth_user->id;

        $get_all_payments = PaymentHistory::where('receiver_id',$user_id);
        if(!empty($request->status)){
            $get_all_payments = $get_all_payments->where('status',$request->status);
        }

        if(!empty($request->from) && !empty($request->to)){
            $get_all_payments = $get_all_payments->whereDate('datetime', '>=', $request->from)->whereDate('datetime', '<=',  $request->to);
        }
        if (auth()->user()->hasAnyRole(['handyman'])) {
            $get_all_payments = $get_all_payments->where('action' ,'handyman_approved_cash')->where('receiver_id',$user_id);
        }

        if (auth()->user()->hasAnyRole(['provider'])) {
            $get_all_payments = $get_all_payments->where('action' ,'handyman_send_provider')->where('receiver_id',$user_id);
        }


        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $get_all_payments->count();
            }
        }

        $get_all_payments = $get_all_payments->orderBy('id','desc')->paginate($per_page);


        $items = PaymentHistoryResource::collection($get_all_payments);

        $response = [
            'today_cash' => today_cash_total($user_id,$request->to,$request->from),
            'total_cash' => total_cash($user_id),
            'cash_detail' => $items
        ];

        return comman_custom_response($response);
    }

    public function getCashPayment(Request $request)
    {
        $payment = Payment::where('payment_type', 'cash');

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $payment->count();
            }
        }

        $payment = $payment->orderBy('id','desc')->paginate($per_page);
        $items = PaymentResource::collection($payment);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);
    }
    public function paymentGateways(Request $request){
        $payment = PaymentGateway::where('status',1)->where('type', '!=', 'razorPayX')->where('type', '!=', 'qoreid')->get();
        $payment = PaymentGatewayResource::collection($payment);

        return comman_custom_response($payment);
    }
}
