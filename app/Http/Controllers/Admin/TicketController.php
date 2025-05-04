<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketsIndexRequest;
use App\Http\Requests\Ticket\MessageRequest;
use App\Models\Ticket\Status;
use App\Models\Ticket\Ticket;
use App\Services\Tickets\TicketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    private $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
        // $this->middleware('can:manage-tickets'); // is called twice see "barryvdh/laravel-debugbar": gates. Is applyed also in: resources\views\admin\_nav.blade.php
    }

    /**
     * Display a listing of adverts.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(TicketsIndexRequest $request)
    {
        $validated = $request->validated();

        // Start query building
        $query = Ticket::query();

        // Apply other filters
        if (! empty($value = $validated['id'] ?? null)) {
            $query->where('id', $value);
        }

        if (! empty($value = $validated['created_at'] ?? null)) {
            try {
                $date = Carbon::createFromFormat('d.m.Y', $value); // Convert date format from dd.mm.yyyy to Y-m-d
                // copy() method creates a clone of the original Carbon instance.
                // This prevents mutation of the $date instance
                $query->whereBetween('created_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ]);
            } catch (\Exception $e) {
                // Invalid date, skip filtering
                // Handle invalid date input (optional: log or ignore)
            }
        }

        if (! empty($value = $validated['updated_at'] ?? null)) {
            try {
                $date = Carbon::createFromFormat('d.m.Y', $value); // Convert date format from dd.mm.yyyy to Y-m-d
                // copy() method creates a clone of the original Carbon instance.
                // This prevents mutation of the $date instance
                $query->whereBetween('updated_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ]);
            } catch (\Exception $e) {
                // Invalid date, skip filtering
                // Handle invalid date input (optional: log or ignore)
            }
        }

        if (! empty($value = $validated['subject'] ?? null)) {
            $query->where('subject', 'like', '%' . $value . '%');
        }

        if (! empty($value = $validated['user'] ?? null)) {
            $query->where('user_id', $value);
        }

        if (! empty($value = $validated['status'] ?? null)) {
            $query->where('status', $value);
        }

        $tickets = $query
            ->orderByDesc('updated_at')
            ->with('user:id,name')
            ->with('messages.user')
            ->withCount('messagesReceivedByAdmin as messages_received_count', 'messagesSentByAdmin as messages_sent_count')
            ->withSum('messagesUnreadByAdmin as unread_messages_sum', 'is_new_message')
            ->paginate(100)
            ->withQueryString(); // Retain query string parameters

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'statuses' => Status::statusesList(),
            'ticketsCount' => $tickets->total(),
        ]);
    }

    public function show(Ticket $ticket)
    {
        $messages = $ticket->messages()->orderBy('created_at')->with('user')->get();

        $messagesByDate = $messages->groupBy(function ($item) {
            return $item->created_at->format('d.m.Y');
        });

        // set admin's unread messages count to zero
        $ticket->readByAdmin();

        return view('admin.tickets.show.show', compact('ticket', 'messagesByDate'));
    }

    public function addMessage(MessageRequest $request, Ticket $ticket)
    {
        try {
            $this->ticketService->addMessage($request, Auth::id(), $ticket->id, true);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tickets.show', $ticket);
    }

    public function approve(Ticket $ticket)
    {
        try {
            $this->ticketService->approve(Auth::id(), $ticket->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tickets.show', $ticket);
    }

    public function close(Ticket $ticket)
    {
        try {
            $this->ticketService->close(Auth::id(), $ticket->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tickets.show', $ticket);
    }

    public function reopen(Ticket $ticket)
    {
        try {
            $this->ticketService->reopen(Auth::id(), $ticket->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tickets.show', $ticket);
    }

    public function destroy(Ticket $ticket)
    {
        try {
            $this->ticketService->removeByAdmin($ticket->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tickets.index');
    }
}
