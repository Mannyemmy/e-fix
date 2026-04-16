<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\BlockedChatPattern;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ChatMonitorController extends Controller
{
    /**
     * Chat messages list view.
     */
    public function index(Request $request)
    {
        $filter = [
            'status' => $request->status,
        ];
        $pageTitle = 'Chat Monitoring';
        $assets = ['datatable'];
        $auth_user = authSession();
        $list_status = $request->status ?? 'all';

        return view('chat-monitor.index', compact('pageTitle', 'assets', 'auth_user', 'filter', 'list_status'));
    }

    /**
     * DataTable data for chat messages.
     */
    public function index_data(DataTables $datatable, Request $request)
    {
        $query = ChatMessage::with(['sender', 'receiver'])->orderBy('id', 'desc');

        $filter = $request->filter;

        if (isset($filter)) {
            if (isset($filter['column_status']) && $filter['column_status'] !== '') {
                if ($filter['column_status'] === 'flagged') {
                    $query->where('is_flagged', true);
                } elseif ($filter['column_status'] === 'blocked') {
                    $query->where('is_blocked', true);
                }
            }
        }

        if ($request->search && isset($request->search['value']) && $request->search['value'] != '') {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('message', 'LIKE', '%' . $searchValue . '%')
                  ->orWhereHas('sender', function ($sq) use ($searchValue) {
                      $sq->where('display_name', 'LIKE', '%' . $searchValue . '%')
                         ->orWhere('email', 'LIKE', '%' . $searchValue . '%');
                  })
                  ->orWhereHas('receiver', function ($sq) use ($searchValue) {
                      $sq->where('display_name', 'LIKE', '%' . $searchValue . '%')
                         ->orWhere('email', 'LIKE', '%' . $searchValue . '%');
                  });
            });
        }

        return $datatable->eloquent($query)
            ->addColumn('check', function ($row) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $row->id . '" name="datatable_ids[]" value="' . $row->id . '" data-type="chat" onclick="dataTableRowCheck(' . $row->id . ',this)">';
            })
            ->editColumn('sender_id', function ($row) {
                if ($row->sender) {
                    return '<div class="d-flex align-items-center">'
                        . '<span>' . e($row->sender->display_name ?? $row->sender->first_name . ' ' . $row->sender->last_name) . '</span>'
                        . '<br><small class="text-muted">' . e($row->sender->email) . '</small>'
                        . '</div>';
                }
                return 'Unknown User';
            })
            ->editColumn('receiver_id', function ($row) {
                if ($row->receiver) {
                    return '<div class="d-flex align-items-center">'
                        . '<span>' . e($row->receiver->display_name ?? $row->receiver->first_name . ' ' . $row->receiver->last_name) . '</span>'
                        . '<br><small class="text-muted">' . e($row->receiver->email) . '</small>'
                        . '</div>';
                }
                return 'Unknown User';
            })
            ->editColumn('message', function ($row) {
                $msg = e(\Illuminate\Support\Str::limit($row->message, 80));
                if ($row->message_type !== 'TEXT') {
                    $msg = '<span class="badge badge-info">' . e($row->message_type) . '</span> ' . $msg;
                }
                return $msg;
            })
            ->addColumn('status_badge', function ($row) {
                $badges = '';
                if ($row->is_flagged) {
                    $badges .= '<span class="badge badge-warning">Flagged</span> ';
                }
                if ($row->is_blocked) {
                    $badges .= '<span class="badge badge-danger">Blocked</span> ';
                }
                if (empty($badges)) {
                    $badges = '<span class="badge badge-active">Normal</span>';
                }
                return $badges;
            })
            ->editColumn('created_at', function ($row) {
                $sitesetup = Setting::where('type', 'site-setup')->where('key', 'site-setup')->first();
                $datetime = $sitesetup ? json_decode($sitesetup->value) : null;

                $formattedDate = optional($datetime)->date_format && optional($datetime)->time_format
                    ? date(optional($datetime)->date_format, strtotime($row->created_at)) . ' ' . date(optional($datetime)->time_format, strtotime($row->created_at))
                    : $row->created_at->format('Y-m-d H:i:s');
                return $formattedDate;
            })
            ->addColumn('action', function ($row) {
                return view('chat-monitor.action', compact('row'))->render();
            })
            ->addIndexColumn()
            ->rawColumns(['check', 'sender_id', 'receiver_id', 'message', 'status_badge', 'action'])
            ->toJson();
    }

    /**
     * View a conversation between two users.
     */
    public function conversation(Request $request, $senderId, $receiverId)
    {
        $sender = User::findOrFail($senderId);
        $receiver = User::findOrFail($receiverId);

        $messages = ChatMessage::where(function ($q) use ($senderId, $receiverId) {
            $q->where('sender_id', $senderId)->where('receiver_id', $receiverId);
        })->orWhere(function ($q) use ($senderId, $receiverId) {
            $q->where('sender_id', $receiverId)->where('receiver_id', $senderId);
        })->orderBy('created_at', 'asc')->paginate(100);

        $pageTitle = 'Conversation: ' . ($sender->display_name ?? $sender->first_name) . ' & ' . ($receiver->display_name ?? $receiver->first_name);

        return view('chat-monitor.conversation', compact('pageTitle', 'messages', 'sender', 'receiver'));
    }

    /**
     * Flag a message.
     */
    public function flagMessage(Request $request, $id)
    {
        $message = ChatMessage::findOrFail($id);
        $message->is_flagged = !$message->is_flagged;
        $message->flag_reason = $request->flag_reason;
        $message->flagged_by = auth()->id();
        $message->save();

        return response()->json([
            'status' => true,
            'message' => $message->is_flagged ? 'Message flagged successfully' : 'Message unflagged successfully',
        ]);
    }

    /**
     * Bulk actions on messages.
     */
    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;
        $message = 'Bulk Action Updated';

        switch ($actionType) {
            case 'flag':
                ChatMessage::whereIn('id', $ids)->update([
                    'is_flagged' => true,
                    'flagged_by' => auth()->id(),
                    'flag_reason' => 'Bulk flagged by admin',
                ]);
                $message = 'Messages flagged successfully';
                break;

            case 'unflag':
                ChatMessage::whereIn('id', $ids)->update([
                    'is_flagged' => false,
                    'flag_reason' => null,
                    'flagged_by' => null,
                ]);
                $message = 'Messages unflagged successfully';
                break;

            case 'delete':
                ChatMessage::whereIn('id', $ids)->delete();
                $message = 'Messages deleted successfully';
                break;

            default:
                return response()->json(['status' => false, 'message' => 'Action Invalid']);
        }

        return response()->json(['status' => true, 'message' => $message]);
    }

    // ==========================================
    // BLOCKED PATTERNS MANAGEMENT
    // ==========================================

    /**
     * Blocked patterns list.
     */
    public function patterns(Request $request)
    {
        $pageTitle = 'Blocked Chat Patterns';
        $assets = ['datatable'];
        $auth_user = authSession();
        $patterns = BlockedChatPattern::orderBy('id', 'desc')->get();

        return view('chat-monitor.patterns', compact('pageTitle', 'assets', 'auth_user', 'patterns'));
    }

    /**
     * DataTable data for blocked patterns.
     */
    public function patterns_data(DataTables $datatable, Request $request)
    {
        $query = BlockedChatPattern::orderBy('id', 'desc');

        return $datatable->eloquent($query)
            ->editColumn('pattern', function ($row) {
                return '<code>' . e($row->pattern) . '</code>';
            })
            ->editColumn('pattern_type', function ($row) {
                $colors = [
                    'keyword' => 'primary',
                    'phone_number' => 'warning',
                    'regex' => 'info',
                ];
                $color = $colors[$row->pattern_type] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . e($row->pattern_type) . '</span>';
            })
            ->editColumn('is_active', function ($row) {
                $checked = $row->is_active ? 'checked' : '';
                return '<div class="custom-control custom-switch custom-switch-text custom-switch-color custom-control-inline">
                    <div class="custom-switch-inner">
                        <input type="checkbox" class="custom-control-input change_status" data-type="blocked_pattern_status" ' . $checked . ' value="' . $row->id . '" id="pattern-' . $row->id . '" data-id="' . $row->id . '">
                        <label class="custom-control-label" for="pattern-' . $row->id . '" data-on-label="" data-off-label=""></label>
                    </div>
                </div>';
            })
            ->addColumn('action', function ($row) {
                return '<a href="javascript:void(0)" class="mr-2 edit-pattern" data-id="' . $row->id . '" data-pattern="' . e($row->pattern) . '" data-type="' . e($row->pattern_type) . '" data-description="' . e($row->description) . '" data-is-regex="' . ($row->is_regex ? '1' : '0') . '"><i class="fas fa-edit text-primary"></i></a>'
                    . '<a href="javascript:void(0)" class="delete-pattern" data-id="' . $row->id . '"><i class="fas fa-trash text-danger"></i></a>';
            })
            ->addIndexColumn()
            ->rawColumns(['pattern', 'pattern_type', 'is_active', 'action'])
            ->toJson();
    }

    /**
     * Store or update a blocked pattern.
     */
    public function storePattern(Request $request)
    {
        $request->validate([
            'pattern' => 'required|string|max:500',
            'pattern_type' => 'required|in:keyword,phone_number,regex',
        ]);

        $data = $request->only(['pattern', 'pattern_type', 'description', 'is_regex']);
        $data['is_regex'] = $request->pattern_type === 'regex';
        $data['is_active'] = true;

        if ($request->id) {
            $pattern = BlockedChatPattern::findOrFail($request->id);
            $pattern->update($data);
            $message = 'Pattern updated successfully';
        } else {
            BlockedChatPattern::create($data);
            $message = 'Pattern created successfully';
        }

        return response()->json(['status' => true, 'message' => $message]);
    }

    /**
     * Delete a blocked pattern.
     */
    public function destroyPattern($id)
    {
        BlockedChatPattern::findOrFail($id)->delete();

        return response()->json(['status' => true, 'message' => 'Pattern deleted successfully']);
    }

    /**
     * Toggle pattern active status.
     */
    public function togglePatternStatus(Request $request)
    {
        $pattern = BlockedChatPattern::findOrFail($request->id);
        $pattern->is_active = !$pattern->is_active;
        $pattern->save();

        return response()->json(['status' => true, 'message' => 'Pattern status updated']);
    }

    /**
     * Dashboard stats for chat monitoring.
     */
    public function stats()
    {
        $totalMessages = ChatMessage::count();
        $flaggedMessages = ChatMessage::where('is_flagged', true)->count();
        $blockedMessages = ChatMessage::where('is_blocked', true)->count();
        $activePatterns = BlockedChatPattern::where('is_active', true)->count();

        return response()->json([
            'total_messages' => $totalMessages,
            'flagged_messages' => $flaggedMessages,
            'blocked_messages' => $blockedMessages,
            'active_patterns' => $activePatterns,
        ]);
    }
}
