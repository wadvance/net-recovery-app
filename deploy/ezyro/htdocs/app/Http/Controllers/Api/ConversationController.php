<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Conversation::with(['client', 'assignee'])->latest('last_message_at');

        if ($user->role === 'agent') {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('assigned_to', null);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeAccess($request->user(), $conversation);

        $messages = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'conversation' => $conversation->load(['client', 'assignee', 'company']),
            'messages' => $messages,
        ]);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->authorizeAccess($request->user(), $conversation);

        $conversation->update(['unread_count' => 0]);

        return response()->json($conversation);
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $this->authorizeAccess($request->user(), $conversation);

        $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $conversation->company_id,
            'client_id' => $conversation->client_id,
            'task_id' => $conversation->client?->tasks()->latest('updated_at')->first()?->id,
            'to_phone' => $conversation->phone,
            'from_phone' => config('services.zavu.sender'),
            'body' => $request->message,
            'template_name' => 'reply',
            'direction' => 'outbound',
            'status' => 'pending',
        ]);

        $result = (new WhatsAppService())->sendTextReply($conversation->phone, $request->message);

        if ($result['ok']) {
            $message->markSent($result['messageId'] ?? '', $result['response'] ?? []);
        } else {
            $message->markFailed($result['error']);
        }

        $conversation->update([
            'last_message' => $request->message,
            'last_message_at' => now(),
        ]);

        return response()->json($message);
    }

    protected function authorizeAccess($user, Conversation $conversation): void
    {
        if ($user->role === 'agent' && $conversation->assigned_to && $conversation->assigned_to !== $user->id) {
            abort(403, 'No autorizado');
        }
    }
}