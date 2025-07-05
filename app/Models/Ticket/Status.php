<?php

declare(strict_types=1);

namespace App\Models\Ticket;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    public const OPENED = 'opened';

    public const APPROVED = 'approved';

    public const CLOSED = 'closed';

    protected $table = 'ticket_statuses';

    protected $guarded = ['id'];

    /**
     * All of the relationships to be touched.
     */
    protected $touches = ['ticket'];

    // is used for filter dropdown in index file, and maybe somewhere else
    public static function statusesList(): array
    {
        return [
            self::OPENED => 'Opened',
            self::APPROVED => 'Approved',
            self::CLOSED => 'Closed',
        ];
    }

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Ticket's Statuses === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function isOpen(): bool
    {
        return $this->status === self::OPENED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }
}
