<?php

declare(strict_types=1);

namespace App\Models\Adverts\Advert\Dialog;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'advert_dialog_messages';

    protected $guarded = ['id'];

    /**
     * All of the relationships to be touched.
     */
    protected $touches = ['dialog'];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function dialog()
    {
        return $this->belongsTo(Dialog::class, 'dialog_id', 'id');
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
     * @comment Scope a query to only include messages which belong to dialog
     *
     * @return Builder
     */
    public function scopeForDialog(Builder $query, Dialog $dialog): void
    {
        $query->where('dialog_id', $dialog->id);
    }

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
     */
    public function scopeSentByUser(Builder $query): void
    {
        $query->where('user_id', auth()->user()?->id);
    }
}
