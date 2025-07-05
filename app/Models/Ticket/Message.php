<?php

declare(strict_types=1);

namespace App\Models\Ticket;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'ticket_messages';

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_new_message' => 'boolean',
        'sent_by_admin' => 'boolean',
    ];

    /**
     * All of the relationships to be touched.
     */
    // protected $touches = ['ticket'];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Message features === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Define whether current logged in user is this advert owner
     */
    public function loggedInUserIsAuthor(): bool
    {
        return auth()->user()?->id === $this->user_id ? true : false;
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include messages which were received by logged in user
     *
     * @return Builder
     */
    public function scopeReceivedByUser(Builder $query): void
    {
        $query->whereNot('user_id', auth()->user()?->id);
    }

    /**
     * @comment Scope a query to only include messages which were sent by logged in user
     *
     * @return Builder
     */
    public function scopeSentByUser(Builder $query): void
    {
        $query->where('user_id', auth()->user()?->id);
    }

    /**
     * @comment Scope a query to only include messages which are unread by admins
     */
    public function scopeUnreadByAdmins(Builder $query): void
    {
        $query->where('sent_by_admin', false)->where('is_new_message', true);
    }
}
