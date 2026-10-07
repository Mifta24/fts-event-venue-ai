<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentApartment;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HandoverController extends Controller
{
    use ResolvesCurrentApartment;

    public function index(Request $request): View
    {
        $apartment = $this->currentApartment($request);

        $status = $request->query('status', HandoverRequest::STATUS_OPEN);

        $handovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('apartment_id', $apartment->id))
            ->where('status', $status)
            ->with('conversation')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.handovers.index', compact('apartment', 'handovers', 'status'));
    }

    public function show(Request $request, HandoverRequest $handover): View
    {
        $apartment = $this->currentApartment($request);
        abort_if($handover->conversation->apartment_id !== $apartment->id, 404);

        $handover->load('conversation.messages');

        return view('admin.handovers.show', compact('apartment', 'handover'));
    }

    public function reply(Request $request, HandoverRequest $handover): RedirectResponse
    {
        $apartment = $this->currentApartment($request);
        abort_if($handover->conversation->apartment_id !== $apartment->id, 404);

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $handover->conversation->messages()->create([
            'role' => ConversationMessage::ROLE_STAFF,
            'content' => $data['message'],
        ]);

        return back()->with('status', 'Reply sent to the guest.');
    }

    public function resolve(Request $request, HandoverRequest $handover): RedirectResponse
    {
        $apartment = $this->currentApartment($request);
        abort_if($handover->conversation->apartment_id !== $apartment->id, 404);

        $handover->update(['status' => HandoverRequest::STATUS_RESOLVED, 'resolved_at' => now(), 'assigned_to' => $request->user()->id]);
        $handover->conversation->update(['status' => Conversation::STATUS_ACTIVE]);

        return redirect()->route('admin.handovers.index')->with('status', 'Handover resolved — the AI Concierge is back in the conversation.');
    }
}
