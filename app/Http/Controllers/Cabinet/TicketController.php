<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\MessageRequest;
use App\Http\Requests\Ticket\TicketRequest;
use App\Models\Ticket\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    private $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
        // apply middleware to all controller methods excepting destroy()
        $this->middleware('can:access-to-tickets')->except('destroy');
    }

    public function index()
    {
        $tickets = Ticket::forUser(Auth::user())
            ->orderByDesc('updated_at')
            ->with('messages.user')
            ->withCount(['messagesReceivedByUser as messages_received_count', 'messagesSentByUser as messages_sent_count'])
            ->withSum('messagesReceivedByUser as unread_messages_sum', 'is_new_message')
            ->paginate(20);

        // dd($tickets);
        // return 1;
        return view('cabinet.tickets.index', compact('tickets'));
    }

    public function show(Ticket $ticket)
    {
        $this->checkAccess($ticket);
        $messages = $ticket->messages()->orderBy('created_at')->with('user')->get();

        $messagesByDate = $messages->groupBy(function ($item) {
            return $item->created_at->format('d.m.Y');
        });

        // set user's unread messages count to zero
        $ticket->readByUser();

        return view('cabinet.tickets.show.show', compact('ticket', 'messagesByDate'));
    }

    public function create()
    {
        return $this->edit(new Ticket());
    }

    public function store(TicketRequest $request)
    {
        return $this->update($request, new Ticket());
    }

    public function edit(Ticket $ticket)
    {
        if ($ticket->exists) {
            $this->checkAccess($ticket);
        }

        return view('cabinet.tickets.create_or_edit', compact('ticket'));
    }

    /**
     * User can edit ticket only if its status is `opened`, before admin approve it
     */
    public function update(TicketRequest $request, Ticket $ticket)
    {
        if ($ticket->exists) {
            $this->checkAccess($ticket);
        }
        try {
            $ticket = $this->ticketService->storeOrUpdate($request, $ticket, true);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.tickets.show', $ticket);
    }

    public function addMessage(MessageRequest $request, Ticket $ticket)
    {
        $this->checkAccess($ticket);
        try {
            $this->ticketService->addMessage($request, Auth::id(), $ticket->id, false);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.tickets.show', $ticket);
    }

    /**
     * User can delete ticket only if its status is `opened`, before admin approve it
     */
    public function destroy(Ticket $ticket)
    {
        $this->checkAccess($ticket);
        try {
            $this->ticketService->removeByOwner($ticket->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cabinet.tickets.index');
    }

    // HELPERS sub-methods ====================================

    // can not be injected in constructor because this middleware accepts two parameters
    // so the middleware can be called only from method where $ticket argument is accessable
    private function checkAccess(Ticket $ticket): void
    {
        if (! Gate::allows('manage-own-ticket', $ticket)) {
            abort(403);
        }
    }
}
