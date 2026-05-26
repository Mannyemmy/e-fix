<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReferralCode;
use App\Models\ReferredUser;
use App\Models\ReferralEarning;
use App\Models\Wallet;
use App\Models\WalletHistory;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ReferralController extends Controller
{
    private function getReferralCodeByEmail($email)
    {
        return ReferralCode::whereHas('user', function ($q) use ($email) {
            $q->where('email', $email);
        })->first();
    }

    private function getReferralUserId($user): int
    {
        $referral = $this->getReferralCodeByEmail($user->email);
        return $referral ? $referral->user_id : $user->id;
    }

    public function generateCode(Request $request)
    {
        $user = auth()->user();

        $existing = $this->getReferralCodeByEmail($user->email);
        if ($existing) {
            return comman_custom_response([
                'status' => true,
                'data' => $existing,
            ]);
        }

        $base = $user->first_name ? str_replace('-', '', Str::slug($user->first_name)) : 'user';
        $code = strtoupper($base . Str::random(4));

        while (ReferralCode::where('code', $code)->exists()) {
            $code = strtoupper($base . Str::random(4));
        }

        $referral = ReferralCode::create([
            'user_id' => $user->id,
            'code' => $code,
        ]);

        return comman_custom_response([
            'status' => true,
            'data' => $referral,
        ]);
    }

    public function getMyReferralCode(Request $request)
    {
        $user = auth()->user();
        $referral = $this->getReferralCodeByEmail($user->email);

        if (!$referral) {
            $base = $user->first_name ? str_replace('-', '', Str::slug($user->first_name)) : 'user';
            $code = strtoupper($base . Str::random(4));

            while (ReferralCode::where('code', $code)->exists()) {
                $code = strtoupper($base . Str::random(4));
            }

            $referral = ReferralCode::create([
                'user_id' => $user->id,
                'code' => $code,
            ]);
        }

        return comman_custom_response([
            'status' => true,
            'data' => $referral,
        ]);
    }

    public function getReferralHistory(Request $request)
    {
        $user = auth()->user();
        $referralUserId = $this->getReferralUserId($user);

        $query = ReferredUser::with('referredUser')
            ->where('referrer_id', $referralUserId)
            ->orderBy('created_at', 'desc');

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page === 'all') {
                $per_page = $query->count();
            }
        }

        $items = $query->paginate($per_page);

        $data = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'referred_user_name' => $item->referredUser ? $item->referredUser->display_name : 'Unknown',
                'referred_user_email' => $item->referredUser ? $item->referredUser->email : '',
                'status' => $item->status,
                'reward_amount' => (float)$item->reward_amount,
                'created_at' => $item->created_at->toDateTimeString(),
                'credited_at' => $item->credited_at ? $item->credited_at->toDateTimeString() : null,
            ];
        });

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
            'data' => $data,
        ];

        return comman_custom_response($response);
    }

    public function getReferralStats(Request $request)
    {
        $user = auth()->user();
        $referral = $this->getReferralCodeByEmail($user->email);

        if (!$referral) {
            return comman_custom_response([
                'status' => true,
                'data' => [
                    'total_referred' => 0,
                    'total_earned' => 0,
                    'pending_rewards' => 0,
                ],
            ]);
        }

        $totalEarned = ReferralEarning::where('referrer_id', $referral->user_id)->sum('earned_amount');

        return comman_custom_response([
            'status' => true,
            'data' => [
                'total_referred' => (int)$referral->total_referred,
                'total_earned' => (float)$totalEarned,
                'pending_rewards' => 0,
                'code' => $referral->code,
            ],
        ]);
    }

    public function applyReferralCode(Request $request)
    {
        $request->validate([
            'referral_code' => 'required|string|max:20',
        ]);

        $user = auth()->user();
        $code = $request->referral_code;

        $referral = ReferralCode::where('code', $code)->first();

        if (!$referral) {
            return comman_message_response('Invalid referral code.', 400);
        }

        $ownCode = $this->getReferralCodeByEmail($user->email);
        if ($ownCode && $ownCode->code === $code) {
            return comman_message_response('You cannot use your own referral code.', 400);
        }

        $alreadyReferred = ReferredUser::where('referrer_id', $referral->user_id)
            ->where('referred_user_id', $user->id)
            ->exists();

        if ($alreadyReferred) {
            return comman_message_response('You have already been referred by this user.', 400);
        }

        ReferredUser::create([
            'referrer_id' => $referral->user_id,
            'referred_user_id' => $user->id,
            'referral_code' => $code,
        ]);

        $referral->increment('total_referred');

        return comman_custom_response([
            'status' => true,
            'message' => 'Referral code applied successfully!',
        ]);
    }
}
