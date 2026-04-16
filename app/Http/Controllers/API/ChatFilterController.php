<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlockedChatPattern;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatFilterController extends Controller
{
    /**
     * Check if a message is allowed (called by mobile apps before sending).
     */
    public function checkMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'sender_id' => 'required',
            'receiver_id' => 'required',
        ]);

        $message = $request->message;
        $blockedPatterns = BlockedChatPattern::active()->get();
        $isBlocked = false;
        $blockedReason = null;

        // Check against all active patterns
        foreach ($blockedPatterns as $pattern) {
            if ($pattern->matchesMessage($message)) {
                $isBlocked = true;
                $blockedReason = $pattern->description ?? 'Message contains restricted content: ' . $pattern->pattern;
                break;
            }
        }

        // Check for phone number patterns (built-in)
        if (!$isBlocked && $this->containsPhoneNumber($message)) {
            $isBlocked = true;
            $blockedReason = 'Sharing phone numbers is not allowed';
        }

        // Log the message to the database for admin monitoring
        ChatMessage::create([
            'sender_id' => $request->sender_id,
            'receiver_id' => $request->receiver_id,
            'message' => $message,
            'message_type' => $request->message_type ?? 'TEXT',
            'is_blocked' => $isBlocked,
            'blocked_reason' => $blockedReason,
        ]);

        return response()->json([
            'is_blocked' => $isBlocked,
            'message' => $isBlocked ? $blockedReason : 'Message allowed',
        ]);
    }

    /**
     * Log a message (called by mobile apps after sending, for monitoring).
     */
    public function logMessage(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string',
            'sender_id' => 'required',
            'receiver_id' => 'required',
        ]);

        ChatMessage::create([
            'sender_id' => $request->sender_id,
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'message_type' => $request->message_type ?? 'TEXT',
            'firestore_doc_id' => $request->firestore_doc_id,
        ]);

        return response()->json(['status' => true, 'message' => 'Message logged']);
    }

    /**
     * Get blocked patterns (for client-side pre-check).
     */
    public function getBlockedPatterns()
    {
        $patterns = BlockedChatPattern::active()
            ->select('pattern', 'pattern_type', 'is_regex')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $patterns,
        ]);
    }

    /**
     * Check if message contains phone number patterns.
     */
    private function containsPhoneNumber(string $message): bool
    {
        // Remove common obfuscation characters
        $cleaned = preg_replace('/[\s\-\.\(\)\/\\\\]/', '', $message);

        // Check for sequences of 7+ digits (phone numbers)
        if (preg_match('/\d{7,}/', $cleaned)) {
            return true;
        }

        // Check for common patterns like "call me", "my number", "whatsapp", etc.
        $contactPatterns = [
            '/\b(call\s*me|my\s*number|phone\s*number|contact\s*number|mobile\s*number)\b/i',
            '/\b(whatsapp|viber|telegram|signal)\b/i',
            '/\b(give\s*me\s*your\s*(number|phone|contact|mobile))\b/i',
            '/\b(send\s*me\s*your\s*(number|phone|contact|mobile))\b/i',
            '/\b(text\s*me\s*(at|on))\b/i',
            '/\b(reach\s*me\s*(at|on))\b/i',
            '/\+\d{1,3}\s*\d/i',
        ];

        foreach ($contactPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }
}
