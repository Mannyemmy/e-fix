<?php

namespace App\Http\Controllers;

use App\Models\UserActivityLog;
use App\Services\IpGeolocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class ActivityLogController extends Controller
{
    protected $geo;

    public function __construct(IpGeolocationService $geo)
    {
        $this->geo = $geo;
    }

    public function index(Request $request)
    {
        $pageTitle = __('messages.activity_log');
        $assets    = ['datatable'];
        $auth_user = authSession();

        $stats = [
            'signups_today' => UserActivityLog::event(UserActivityLog::EVENT_REGISTER)
                ->whereDate('created_at', today())->count(),
            'failed_logins_today' => UserActivityLog::event(UserActivityLog::EVENT_LOGIN_FAILED)
                ->whereDate('created_at', today())->count(),
            'distinct_ips_today' => UserActivityLog::whereDate('created_at', today())
                ->distinct('ip_address')->count('ip_address'),
            'suspicious_ips' => \App\Models\IpGeolocation::where(function ($q) {
                $q->where('is_hosting', true)->orWhere('is_proxy', true);
            })->count(),
        ];

        return view('activity-log.index', compact('pageTitle', 'assets', 'auth_user', 'stats'));
    }

    public function index_data(DataTables $datatable, Request $request)
    {
        $query = UserActivityLog::with(['user', 'geolocation'])->orderBy('id', 'desc');

        $filter = $request->filter;

        if (isset($filter)) {
            if (! empty($filter['column_event'])) {
                $query->where('event', $filter['column_event']);
            }
            if (! empty($filter['column_source'])) {
                $query->where('source', $filter['column_source']);
            }
            if (! empty($filter['column_suspicious'])) {
                // Only rows whose address resolved to a datacentre or proxy.
                $query->whereIn('ip_address', function ($sub) {
                    $sub->select('ip_address')->from('ip_geolocations')
                        ->where(function ($q) {
                            $q->where('is_hosting', true)->orWhere('is_proxy', true);
                        });
                });
            }
        }

        if ($request->search && isset($request->search['value']) && $request->search['value'] != '') {
            $search = $request->search['value'];
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'LIKE', '%'.$search.'%')
                  ->orWhere('email', 'LIKE', '%'.$search.'%')
                  ->orWhere('user_agent', 'LIKE', '%'.$search.'%')
                  ->orWhereHas('user', function ($sq) use ($search) {
                      $sq->where('display_name', 'LIKE', '%'.$search.'%')
                         ->orWhere('email', 'LIKE', '%'.$search.'%');
                  });
            });
        }

        // Resolve a few unresolved addresses per page load. Keeps the free
        // ip-api rate limit comfortable while the table fills itself in.
        $this->resolveVisible($query);

        return $datatable->eloquent($query)
            ->editColumn('event', function ($row) {
                $map = [
                    UserActivityLog::EVENT_REGISTER     => 'badge-primary',
                    UserActivityLog::EVENT_LOGIN        => 'badge-success',
                    UserActivityLog::EVENT_LOGIN_FAILED => 'badge-danger',
                ];
                $class = $map[$row->event] ?? 'badge-secondary';

                return '<span class="badge '.$class.'">'.e($row->event_label).'</span>';
            })
            ->addColumn('account', function ($row) {
                if ($row->user) {
                    $name = $row->user->display_name ?: trim($row->user->first_name.' '.$row->user->last_name);
                    $out  = '<a href="'.route('user.detail.activity', $row->user->id).'">'.e($name ?: '#'.$row->user->id).'</a>';
                    $out .= '<br><small class="text-muted">'.e($row->user->email).'</small>';
                    if ($row->user->trashed()) {
                        $out .= ' <span class="badge badge-warning">deleted</span>';
                    }

                    return $out;
                }

                return $row->email
                    ? '<span class="text-muted">'.e($row->email).'</span><br><small class="text-danger">no account</small>'
                    : '<span class="text-muted">&mdash;</span>';
            })
            ->addColumn('ip', function ($row) {
                if (! $row->ip_address) {
                    return '<span class="text-muted">&mdash;</span>';
                }
                $out = '<code>'.e($row->ip_address).'</code>';
                if ($row->geolocation && $row->geolocation->is_suspicious) {
                    $label = $row->geolocation->is_hosting ? 'datacentre' : 'proxy';
                    $out  .= ' <span class="badge badge-danger">'.$label.'</span>';
                }

                return $out;
            })
            ->addColumn('location', function ($row) {
                if (! $row->geolocation) {
                    return '<small class="text-muted">not resolved yet</small>';
                }
                if ($row->geolocation->lookup_status !== \App\Models\IpGeolocation::STATUS_SUCCESS) {
                    return '<small class="text-muted">&mdash;</small>';
                }

                $out = e($row->geolocation->location_label ?: '—');
                if ($row->geolocation->isp) {
                    $out .= '<br><small class="text-muted">'.e($row->geolocation->isp).'</small>';
                }

                return $out;
            })
            ->addColumn('device', function ($row) {
                if (! $row->user_agent) {
                    return '<span class="text-muted">&mdash;</span>';
                }
                $src = $row->source ? '<span class="badge badge-light">'.e($row->source).'</span> ' : '';

                return $src.'<small title="'.e($row->user_agent).'">'.e(Str::limit($row->user_agent, 45)).'</small>';
            })
            ->editColumn('created_at', function ($row) {
                return '<span title="'.$row->created_at->toDateTimeString().'">'
                    .$row->created_at->format('d M Y, h:i A').'</span>';
            })
            ->rawColumns(['event', 'account', 'ip', 'location', 'device', 'created_at'])
            ->toJson();
    }

    /**
     * Look up geolocation for the addresses on the page currently being rendered.
     * Cloned so the datatable's own pagination is untouched.
     */
    protected function resolveVisible($query, $limit = 15)
    {
        try {
            $ips = (clone $query)->limit(50)->pluck('ip_address')->filter()->unique()->all();
            $this->geo->resolveMany($ips, $limit);
        } catch (\Throwable $th) {
            // Never let a geolocation hiccup take the log page down.
            \Illuminate\Support\Facades\Log::warning('[ActivityLog] batch resolve failed', [
                'error' => $th->getMessage(),
            ]);
        }
    }

    /**
     * Activity for a single account, rendered on the user detail pages.
     */
    public function userActivity(Request $request, $id)
    {
        $pageTitle = __('messages.activity_log');
        $assets    = ['datatable'];
        $auth_user = authSession();

        $user = \App\Models\User::withTrashed()->findOrFail($id);

        $logs = UserActivityLog::with('geolocation')
            ->where('user_id', $id)
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        try {
            $this->geo->resolveMany($logs->pluck('ip_address')->filter()->unique()->all(), 15);
            $logs->load('geolocation');
        } catch (\Throwable $th) {
            // non-fatal
        }

        return view('activity-log.user', compact('pageTitle', 'assets', 'auth_user', 'user', 'logs'));
    }
}
