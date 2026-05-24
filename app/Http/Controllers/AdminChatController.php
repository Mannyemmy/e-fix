<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class AdminChatController extends Controller
{
    const MESSAGES_COLLECTION = 'messages';
    const USER_COLLECTION = 'users';
    const CONTACT_COLLECTION = 'contact';

    /**
     * Get the admin chat UID from settings.
     */
    protected function getAdminChatUid()
    {
        $setting = Setting::where('type', 'OTHER_SETTING')->first();
        $other = $setting ? json_decode($setting->value) : null;
        return $other->admin_chat_uid ?? env('ADMIN_CHAT_UID', '');
    }

    /**
     * Get the admin Firebase user document from Firestore.
     */
    protected function getAdminFirestoreUser()
    {
        $adminUid = $this->getAdminChatUid();
        if (empty($adminUid)) return null;

        try {
            $doc = firestoreApiRequest('GET', '/documents/' . self::USER_COLLECTION . '/' . $adminUid);
            return isset($doc['fields']) ? firestoreDocToArray($doc) : null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch admin Firestore user: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Show conversations list (users who have chatted with admin).
     */
    public function index()
    {
        $adminUid = $this->getAdminChatUid();
        $pageTitle = 'Live Chat';
        $assets = ['datatable'];
        $auth_user = authSession();

        if (empty($adminUid)) {
            return view('admin-chat.index', compact('pageTitle', 'assets', 'auth_user'))
                ->with('error', 'Admin chat UID is not configured. Please set ADMIN_CHAT_UID in settings or .env file.');
        }

        return view('admin-chat.index', compact('pageTitle', 'assets', 'auth_user', 'adminUid'));
    }

    /**
     * DataTable data for conversations.
     */
    public function conversationsData(DataTables $datatable, Request $request)
    {
        $adminUid = $this->getAdminChatUid();
        if (empty($adminUid)) {
            return response()->json(['data' => []]);
        }

        try {
            // Fetch contacts from users/{adminUid}/contact/
            $contactsDoc = firestoreApiRequest('GET', '/documents/' . self::USER_COLLECTION . '/' . $adminUid . '/' . self::CONTACT_COLLECTION . '?pageSize=100');

            $contacts = [];
            if (isset($contactsDoc['documents'])) {
                foreach ($contactsDoc['documents'] as $doc) {
                    $contactData = firestoreDocToArray($doc);
                    $contactUid = $contactData['uid'] ?? $contactData['_document_id'];

                    // Fetch user info
                    $userInfo = null;
                    try {
                        $userDoc = firestoreApiRequest('GET', '/documents/' . self::USER_COLLECTION . '/' . $contactUid);
                        $userInfo = isset($userDoc['fields']) ? firestoreDocToArray($userDoc) : null;
                    } catch (\Exception $e) {
                        Log::warning('Could not fetch user ' . $contactUid . ': ' . $e->getMessage());
                    }

                    $contacts[] = [
                        'contact_uid' => $contactUid,
                        'display_name' => $userInfo['display_name'] ?? $userInfo['first_name'] ?? 'Unknown',
                        'email' => $userInfo['email'] ?? '',
                        'profile_image' => $userInfo['profile_image'] ?? '',
                        'last_message_time' => $contactData['lastMessageTime'] ?? null,
                        'is_online' => $contactData['isOnline'] ?? 0,
                    ];
                }
            }

            // Sort by last message time descending
            usort($contacts, function ($a, $b) {
                return ($b['last_message_time'] ?? 0) - ($a['last_message_time'] ?? 0);
            });

            return $datatable->collection(collect($contacts))
                ->addColumn('user_info', function ($row) {
                    $avatar = '';
                    if (!empty($row['profile_image'])) {
                        $avatar = '<img src="' . e($row['profile_image']) . '" class="rounded-circle" width="40" height="40" alt="Avatar">';
                    } else {
                        $initial = strtoupper(substr($row['display_name'], 0, 1));
                        $avatar = '<div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center text-white font-weight-bold" style="width:40px;height:40px;">' . $initial . '</div>';
                    }
                    $onlineDot = ($row['is_online'] == 1) ? '<span class="badge badge-success ml-1" style="width:8px;height:8px;border-radius:50%;display:inline-block;"></span>' : '';
                    return '<div class="d-flex align-items-center gap-2">'
                        . $avatar
                        . '<div><span>' . e($row['display_name']) . '</span> ' . $onlineDot
                        . '<br><small class="text-muted">' . e($row['email']) . '</small></div>'
                        . '</div>';
                })
                ->editColumn('last_message_time', function ($row) {
                    if (empty($row['last_message_time'])) return '-';
                    return date('M d, Y h:i A', $row['last_message_time'] / 1000);
                })
                ->editColumn('is_online', function ($row) {
                    return $row['is_online'] == 1
                        ? '<span class="badge badge-success">Online</span>'
                        : '<span class="badge badge-secondary">Offline</span>';
                })
                ->addColumn('action', function ($row) use ($adminUid) {
                    $chatUrl = route('admin-chat.conversation', ['userId' => $row['contact_uid']]);
                    return '<a href="' . $chatUrl . '" class="btn btn-sm btn-primary"><i class="fas fa-comment"></i> Chat</a>';
                })
                ->addIndexColumn()
                ->rawColumns(['user_info', 'is_online', 'action'])
                ->toJson();
        } catch (\Exception $e) {
            Log::error('AdminChat conversationsData error: ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Show a single conversation between admin and a user.
     */
    public function conversation($userId)
    {
        $adminUid = $this->getAdminChatUid();
        $pageTitle = 'Conversation';
        $auth_user = authSession();

        if (empty($adminUid)) {
            return redirect()->route('admin-chat.index')->with('error', 'Admin chat UID is not configured.');
        }

        // Fetch user info from Firestore
        $userData = null;
        try {
            $userDoc = firestoreApiRequest('GET', '/documents/' . self::USER_COLLECTION . '/' . $userId);
            $userData = isset($userDoc['fields']) ? firestoreDocToArray($userDoc) : null;
        } catch (\Exception $e) {
            Log::warning('Could not fetch user ' . $userId . ': ' . $e->getMessage());
        }

        if (!$userData) {
            return redirect()->route('admin-chat.index')->with('error', 'User not found in Firestore.');
        }

        return view('admin-chat.conversation', compact('pageTitle', 'auth_user', 'adminUid', 'userId', 'userData'));
    }

    /**
     * Get messages for a conversation (JSON, used for polling).
     */
    public function getMessages($userId, Request $request)
    {
        $adminUid = $this->getAdminChatUid();
        if (empty($adminUid)) {
            return response()->json(['messages' => [], 'error' => 'Admin chat UID not configured']);
        }

        try {
            // Messages are stored in messages/{adminUid}/{userId} (admin's perspective)
            $collectionPath = self::MESSAGES_COLLECTION . '/' . $adminUid . '/' . $userId;
            $url = '/documents/' . $collectionPath . '?pageSize=200';

            $result = firestoreApiRequest('GET', $url);

            $messages = [];
            if (isset($result['documents'])) {
                $docs = $result['documents'];
                foreach ($docs as $doc) {
                    $msg = firestoreDocToArray($doc);
                    $msg['is_me'] = ($msg['senderId'] ?? '') === $adminUid;
                    $messages[] = $msg;
                }
                usort($messages, function ($a, $b) {
                    return ($a['createdAt'] ?? 0) - ($b['createdAt'] ?? 0);
                });
            }

            return response()->json([
                'messages' => $messages,
                'admin_uid' => $adminUid,
                'count' => count($messages),
            ]);
        } catch (\Exception $e) {
            Log::error('getMessages error: ' . $e->getMessage());
            return response()->json(['messages' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Send a message from admin to a user.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|string',
            'message' => 'required|string|max:5000',
        ]);

        $adminUid = $this->getAdminChatUid();
        if (empty($adminUid)) {
            return response()->json(['status' => false, 'message' => 'Admin chat UID not configured']);
        }

        $receiverId = $request->receiver_id;
        $messageText = $request->message;
        $now = round(microtime(true) * 1000);
        $nowTimestamp = date('Y-m-d\TH:i:s.v\Z');

        // Build message data
        $messageData = [
            'senderId' => $adminUid,
            'receiverId' => $receiverId,
            'message' => $messageText,
            'messageType' => 'TEXT',
            'isMessageRead' => false,
            'createdAt' => $now,
            'createdAtTime' => $nowTimestamp,
            'updatedAtTime' => $nowTimestamp,
            'attachmentfiles' => [],
        ];

        try {
            // Write to messages/{adminUid}/{receiverId} (admin's copy)
            $adminCollection = self::MESSAGES_COLLECTION . '/' . $adminUid . '/' . $receiverId;
            $firestoreDoc = buildFirestoreDocument($messageData);

            // Add a server timestamp placeholder
            $firestoreDoc['fields']['createdAtTime'] = ['timestampValue' => $nowTimestamp];
            $firestoreDoc['fields']['updatedAtTime'] = ['timestampValue' => $nowTimestamp];

            // Create the document with auto-generated ID
            $adminResult = firestoreApiRequest('POST', '/documents/' . $adminCollection, $firestoreDoc);

            $docId = null;
            if (isset($adminResult['name'])) {
                $parts = explode('/', $adminResult['name']);
                $docId = end($parts);
                $messageData['uid'] = $docId;

                // Update the document with uid field
                $updateData = buildFirestoreDocument(['uid' => $docId]);
                firestoreApiRequest('PATCH', '/documents/' . $adminCollection . '/' . $docId . '?updateMask.fieldPaths=uid', $updateData);
            }

            // Write to messages/{receiverId}/{adminUid} (receiver's copy)
            $receiverCollection = self::MESSAGES_COLLECTION . '/' . $receiverId . '/' . $adminUid;
            $receiverDoc = buildFirestoreDocument($messageData);
            $receiverDoc['fields']['createdAtTime'] = ['timestampValue' => $nowTimestamp];
            $receiverDoc['fields']['updatedAtTime'] = ['timestampValue' => $nowTimestamp];

            $receiverResult = firestoreApiRequest('POST', '/documents/' . $receiverCollection, $receiverDoc);

            $receiverDocId = null;
            if (isset($receiverResult['name'])) {
                $parts = explode('/', $receiverResult['name']);
                $receiverDocId = end($parts);

                $updateData = buildFirestoreDocument(['uid' => $receiverDocId]);
                firestoreApiRequest('PATCH', '/documents/' . $receiverCollection . '/' . $receiverDocId . '?updateMask.fieldPaths=uid', $updateData);
            }

            // Add/receiver to admin's contacts
            $this->ensureContactExists($adminUid, $receiverId, $now);
            $this->ensureContactExists($receiverId, $adminUid, $now);

            // Notify the user via FCM
            $this->sendChatNotification($receiverId, $messageText);

            // Log the message to the local DB for chat monitoring
            try {
                $userModel = User::where('uid', $receiverId)->first();
                $adminUser = auth()->user();

                \App\Models\ChatMessage::create([
                    'firestore_doc_id' => $docId,
                    'sender_id' => $adminUser ? $adminUser->id : 0,
                    'receiver_id' => $userModel ? $userModel->id : 0,
                    'message' => $messageText,
                    'message_type' => 'TEXT',
                ]);
            } catch (\Exception $logErr) {
                Log::warning('Failed to log chat message locally: ' . $logErr->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Message sent successfully',
                'doc_id' => $docId,
            ]);
        } catch (\Exception $e) {
            Log::error('sendMessage error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
        }
    }

    /**
     * Ensure a contact document exists in user's contact subcollection.
     */
    protected function ensureContactExists($userId, $contactId, $lastMessageTime)
    {
        try {
            $contactData = [
                'uid' => $contactId,
                'lastMessageTime' => $lastMessageTime,
                'isOnline' => 0,
            ];

            $contactDocPath = self::USER_COLLECTION . '/' . $userId . '/' . self::CONTACT_COLLECTION . '/' . $contactId;

            // Use PATCH to create or update - the doc ID matches the contact's UID
            $newDoc = buildFirestoreDocument($contactData);
            firestoreApiRequest('PATCH', '/documents/' . $contactDocPath, $newDoc);
        } catch (\Exception $e) {
            Log::warning('ensureContactExists error: ' . $e->getMessage());
        }
    }

    /**
     * Send FCM push notification to the user when admin replies.
     */
    protected function sendChatNotification($receiverUid, $messageText)
    {
        try {
            // Find the user's Laravel ID for the FCM topic
            $user = User::where('uid', $receiverUid)->first();
            $adminUser = auth()->user();

            if (!$user) {
                Log::warning('Cannot send FCM: User not found for uid ' . $receiverUid);
                return;
            }

            $senderName = $adminUser ? ($adminUser->display_name ?? $adminUser->first_name . ' ' . $adminUser->last_name) : 'Admin';

            $fields = [
                'to' => '/topics/user_' . $user->id,
                'collapse_key' => 'type_a',
                'notification' => [
                    'title' => $senderName,
                    'body' => $messageText,
                ],
                'data' => [
                    'is_chat' => 'true',
                    'sender_id' => $receiverUid,
                    'receiver_id' => $this->getAdminChatUid(),
                ],
            ];

            sendFcmRequest($fields, 'user_' . $user->id);
        } catch (\Exception $e) {
            Log::error('sendChatNotification error: ' . $e->getMessage());
        }
    }

    /**
     * Setup/configure the admin chat user in Firestore.
     */
    public function setupForm()
    {
        $pageTitle = 'Live Chat Setup';
        $assets = [];
        $auth_user = authSession();
        $adminUid = $this->getAdminChatUid();

        return view('admin-chat.setup', compact('pageTitle', 'assets', 'auth_user', 'adminUid'));
    }

    /**
     * Save the admin chat configuration.
     */
    public function saveSetup(Request $request)
    {
        $request->validate([
            'admin_chat_uid' => 'required|string',
        ]);

        $setting = Setting::where('type', 'OTHER_SETTING')->first();
        $value = $setting ? json_decode($setting->value, true) : [];

        $value['admin_chat_uid'] = $request->admin_chat_uid;

        if ($setting) {
            $setting->value = json_encode($value);
            $setting->save();
        } else {
            Setting::create([
                'type' => 'OTHER_SETTING',
                'key' => 'other_setting',
                'value' => json_encode($value),
            ]);
        }

        // Also try to create/update the admin user document in Firestore
        $this->syncAdminFirestoreUser($request->admin_chat_uid);

        return redirect()->route('admin-chat.index')->with('success', 'Live Chat configured successfully.');
    }

    /**
     * Sync admin user data to Firestore.
     */
    protected function syncAdminFirestoreUser($adminUid)
    {
        $adminUser = auth()->user();
        if (!$adminUser) return;

        try {
            $userData = [
                'uid' => $adminUid,
                'email' => $adminUser->email,
                'first_name' => $adminUser->first_name,
                'last_name' => $adminUser->last_name,
                'display_name' => $adminUser->display_name ?? ($adminUser->first_name . ' ' . $adminUser->last_name),
                'profile_image' => $adminUser->profile_image ?? '',
                'id' => $adminUser->id,
                'user_type' => 'admin',
                'isOnline' => 0,
            ];

            $doc = buildFirestoreDocument($userData);
            firestoreApiRequest('PATCH', '/documents/' . self::USER_COLLECTION . '/' . $adminUid . '?updateMask.fieldPaths=uid&updateMask.fieldPaths=email&updateMask.fieldPaths=first_name&updateMask.fieldPaths=last_name&updateMask.fieldPaths=display_name&updateMask.fieldPaths=profile_image&updateMask.fieldPaths=id&updateMask.fieldPaths=user_type&updateMask.fieldPaths=isOnline', $doc);
        } catch (\Exception $e) {
            Log::error('Failed to sync admin user to Firestore: ' . $e->getMessage());
        }
    }
}
