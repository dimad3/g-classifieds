<?php

declare(strict_types=1);

namespace App\Models\Ticket;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * In IT support, a ticket is the central tool for managing and resolving issues efficiently. An IT ticket is like a digital note or message created by a user seeking assistance with an IT-related issue
 */
class Ticket extends Model
{
    protected $table = 'ticket_tickets';

    protected $guarded = ['id'];

    // Manage tickets === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public static function store(User $user, string $subject, string $content): self
    {
        return DB::transaction(function () use ($user, $subject, $content) {
            /** @var Ticket $ticket */
            $ticket = self::create([
                'user_id' => $user->id,
                'subject' => $subject,
                'content' => $content,
                'status' => Status::OPENED,
            ]);
            $ticket->setStatus(Status::OPENED, $user->id);

            return $ticket;
        });
    }

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'ticket_id', 'id');
    }

    public function messagesReceivedByAdmin(): HasMany
    {
        return $this->messages()->where('sent_by_admin', false);
    }

    public function messagesSentByAdmin(): HasMany
    {
        return $this->messages()->where('sent_by_admin', true);
    }

    public function messagesUnreadByAdmin(): HasMany
    {
        return $this->messagesReceivedByAdmin()->where('is_new_message', true);
    }

    public function messagesReceivedByUser(): HasMany
    {
        return $this->messages()->receivedByUser();
    }

    public function messagesSentByUser(): HasMany
    {
        return $this->messages()->sentByUser();
    }

    public function messagesUnreadByUser(): HasMany
    {
        return $this->messagesReceivedByUser()->where('is_new_message', true);
    }

    public function statuses()
    {
        return $this->hasMany(Status::class, 'ticket_id', 'id');
    }

    public function updateTicket(string $subject, string $content): void
    {
        if ($this->isClosed()) {
            throw new \DomainException('Ticket is closed, so editing is impossible.');
        }
        $this->update([
            'subject' => $subject,
            'content' => $content,
        ]);
    }

    public function addMessage(int $userId, string $message, bool $sentByAmin): void
    {
        if ($this->isClosed()) {
            throw new \DomainException('Ticket is closed for messages.');
        }
        $this->messages()->create([
            'user_id' => $userId,
            'is_new_message' => 1,
            'message' => $message,
            'sent_by_admin' => $sentByAmin,
        ]);
        $this->touch();
    }

    public function approve(int $userId): void
    {
        if ($this->isApproved()) {
            throw new \DomainException('Ticket is already approved.');
        }
        $this->setStatus(Status::APPROVED, $userId);
    }

    public function close(int $userId): void
    {
        if ($this->isClosed()) {
            throw new \DomainException('Ticket is already closed.');
        }
        $this->setStatus(Status::CLOSED, $userId);
    }

    public function reopen(int $userId): void
    {
        if (! $this->isClosed()) {
            throw new \DomainException('Ticket is not closed.');
        }
        $this->setStatus(Status::APPROVED, $userId);
    }

    /**
     * Set admin's unread messages to zero
     */
    public function readByAdmin(): void
    {
        $unreadMessages = $this->messagesUnreadByAdmin;
        foreach ($unreadMessages as $message) {
            // Disable touching updated_at
            // $message->timestamps = false;
            $message->update(['is_new_message' => 0]);
        }
    }

    /**
     * Set user's unread messages to zero
     */
    public function readByUser(): void
    {
        // // it is impossible to disable touching updated_at
        // $this->messagesUnreadByUser()->update([
        //     'is_new_message' => 0,
        // ]);;
        // todo: try Model::withoutTimestamps()
        // https://laravel.com/docs/10.x/eloquent#timestamps

        foreach ($this->messagesUnreadByUser as $message) {
            // Disable touching updated_at
            $message->timestamps = false;
            $message->update(['is_new_message' => 0]);
        }
    }

    // Ticket's Statuses === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function isOpen(): bool
    {
        return $this->status === Status::OPENED;
    }

    public function isApproved(): bool
    {
        return $this->status === Status::APPROVED;
    }

    public function isClosed(): bool
    {
        return $this->status === Status::CLOSED;
    }

    public function canBeRemoved(): bool
    {
        return ($this->isOpen() || $this->isClosed()) ? true : false;
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include tickets which which belong to user
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    // HELPERS sub-methods ====================================

    private function setStatus(string $status, int $userId): void
    {
        DB::transaction(function () use ($status, $userId): void {
            $this->statuses()->create(['status' => $status, 'user_id' => $userId]);
            // after status was added in `ticket_statuses` table -> update status in `ticket_tickets` table
            $this->update(['status' => $status]);
        });
    }
}
