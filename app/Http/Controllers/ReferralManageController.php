<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReferredUser;
use App\Models\ReferralEarning;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;

class ReferralManageController extends Controller
{
    public function index(Request $request)
    {
        $filter = [
            'status' => $request->status,
        ];
        $pageTitle = trans('messages.list_form_title', ['form' => trans('messages.referral')]);
        $auth_user = authSession();
        $assets = ['datatable'];

        $totalReferrals = ReferredUser::count();
        $totalRewards = ReferralEarning::sum('earned_amount');

        $topReferrers = ReferralEarning::select('referrer_id', DB::raw('count(*) as total'), DB::raw('COALESCE(SUM(earned_amount), 0) as total_earned'))
            ->groupBy('referrer_id')
            ->orderBy('total_earned', 'desc')
            ->limit(5)
            ->get()
            ->load('referrer');

        return view('referral.index', compact(
            'pageTitle', 'auth_user', 'assets', 'filter',
            'totalReferrals', 'totalRewards',
            'topReferrers'
        ));
    }

    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;
        $message = 'Bulk Action Updated';

        switch ($actionType) {
            case 'change-status':
                ReferredUser::whereIn('id', $ids)->update(['status' => $request->status]);
                $message = 'Bulk Referral Status Updated';
                break;

            case 'delete':
                ReferredUser::whereIn('id', $ids)->delete();
                $message = 'Bulk Referral Deleted';
                break;

            default:
                return response()->json(['status' => false, 'message' => 'Action Invalid']);
        }

        return response()->json(['status' => true, 'message' => $message]);
    }

    public function index_data(DataTables $datatable, Request $request)
    {
        $query = ReferredUser::query()->with(['referrer', 'referredUser'])->orderBy('created_at', 'desc');

        if ($filter = $request->filter) {
            if (isset($filter['column_status']) && $filter['column_status'] !== '') {
                $query->where('status', $filter['column_status']);
            }
        }

        return $datatable->eloquent($query)
            ->addColumn('check', function ($row) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-'.$row->id.'" name="datatable_ids[]" value="'.$row->id.'" data-type="referral" onclick="dataTableRowCheck('.$row->id.',this)">';
            })
            ->editColumn('referrer', function ($row) {
                if ($row->referrer) {
                    $name = $row->referrer->first_name . ' ' . $row->referrer->last_name;
                    if (auth()->user()->can('user view')) {
                        return '<a class="btn-link btn-link-hover" href="'.route('user.show', $row->referrer->id).'">'.$name.'</a>';
                    }
                    return $name;
                }
                return '-';
            })
            ->editColumn('referred_user', function ($row) {
                if ($row->referredUser) {
                    $name = $row->referredUser->first_name . ' ' . $row->referredUser->last_name;
                    if (auth()->user()->can('user view')) {
                        return '<a class="btn-link btn-link-hover" href="'.route('user.show', $row->referredUser->id).'">'.$name.'</a>';
                    }
                    return $name;
                }
                return '-';
            })
            ->editColumn('status', function ($row) {
                $badge = $row->status == 'completed' ? 'badge-success' : 'badge-warning';
                return '<span class="badge '.$badge.'">'.ucfirst($row->status).'</span>';
            })
            ->editColumn('reward_amount', function ($row) {
                return '<span class="badge badge-info">' . __('messages.recurring') . '</span>';
            })
            ->editColumn('referral_code', function ($row) {
                return $row->referral_code ?? '-';
            })
            ->editColumn('created_at', function ($row) {
                $sitesetup = \App\Models\Setting::where('type', 'site-setup')->where('key', 'site-setup')->first();
                if ($sitesetup) {
                    $datetime = json_decode($sitesetup->value);
                    return date("$datetime->date_format / $datetime->time_format", strtotime($row->created_at));
                }
                return $row->created_at->format('Y-m-d H:i');
            })
            ->addColumn('action', function ($row) {
                return view('referral.action', compact('row'))->render();
            })
            ->addIndexColumn()
            ->rawColumns(['check', 'referrer', 'referred_user', 'status', 'reward_amount', 'action'])
            ->toJson();
    }

    public function destroy($id)
    {
        if (demoUserPermission()) {
            return redirect()->back()->withErrors(trans('messages.demo_permission_denied'));
        }

        $referredUser = ReferredUser::find($id);
        $msg = __('messages.msg_fail_to_delete', ['item' => __('messages.referral')]);

        if ($referredUser != '') {
            $referredUser->delete();
            $msg = __('messages.msg_deleted', ['name' => __('messages.referral')]);
        }

        return comman_custom_response(['message' => $msg, 'status' => true]);
    }
}
