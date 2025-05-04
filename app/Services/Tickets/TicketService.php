<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Http\Requests\Ticket\MessageRequest;
use App\Http\Requests\Ticket\TicketRequest;
use App\Models\Ticket\Ticket;

class TicketService
{
    public function storeOrUpdate(TicketRequest $request, Ticket $ticket): Ticket
    {
        // $ticket->user_id = auth()->user();
        // $ticket->subject = $request->subject;
        // $ticket->content = $request->content;
        // $ticket->status  = Status::OPENED;
        // $ticket->save();
        // $ticket->setStatus(Status::OPENED, auth()->user());

        if ($ticket->exists) {
            $ticket->updateTicket(
                $request['subject'],
                $request['content']
            );

            return $ticket;
        }

        return Ticket::store(auth()->user(), $request['subject'], $request['content']);

    }

    public function addMessage(MessageRequest $request, int $userId, int $ticketId, bool $sentByAmin): void
    {
        $ticket = $this->getTicket($ticketId);
        $ticket->addMessage($userId, $request['message'], $sentByAmin);
    }

    public function approve(int $userId, int $id): void
    {
        $ticket = $this->getTicket($id);
        $ticket->approve($userId);
    }

    public function close(int $userId, int $id): void
    {
        $ticket = $this->getTicket($id);
        $ticket->close($userId);
    }

    public function reopen(int $userId, int $id): void
    {
        $ticket = $this->getTicket($id);
        $ticket->reopen($userId);
    }

    public function removeByOwner(int $id): void
    {
        $ticket = $this->getTicket($id);
        if (! $ticket->isOpened()) {
            throw new \DomainException('Unable to remove active ticket');
        }
        $ticket->delete();
    }

    public function removeByAdmin(int $id): void
    {
        $ticket = $this->getTicket($id);
        $ticket->delete();
    }

    // HELPERS sub-methods ====================================

    private function getTicket($id): Ticket
    {
        return Ticket::findOrFail($id);
    }
}
