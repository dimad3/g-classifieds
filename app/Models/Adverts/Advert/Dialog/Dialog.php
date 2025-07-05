<?php

declare(strict_types=1);

namespace App\Models\Adverts\Advert\Dialog;

use App\Models\Adverts\Advert\Advert;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dialog extends Model
{
    protected $table = 'advert_dialogs';

    protected $guarded = ['id'];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function advert()
    {
        return $this->belongsTo(Advert::class, 'advert_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * Can use only as $dialog->counterpart not for relation eager loading using: with('counterpart')
     * dd($this->owner_id, $this->client_id);   // return null & null
     * dd($this->loggedInUserIsClient(), $this->loggedInUserIsOwner());   // return false & false
     * https://stackoverflow.com/questions/46274243/call-to-a-member-function-addeagerconstraints-on-null
     * workaround: https://stackoverflow.com/questions/46274243/call-to-a-member-function-addeagerconstraints-on-null
     */
    public function counterpart(): BelongsTo
    {
        if ($this->loggedInUserIsClient()) {
            return $this->owner();
        }

        return $this->client();

    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'dialog_id', 'id');
    }

    public function messagesReceived(): HasMany
    {
        return $this->messages()->receivedByUser();
    }

    public function messagesSent(): HasMany
    {
        return $this->messages()->sentByUser();
    }

    // Dialog Statuses === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Define whether current logged in user participate in this dialog as advert's owner
     */
    public function loggedInUserIsOwner(): bool
    {
        return auth()->user()?->id === $this->owner_id;
    }

    /**
     * Define whether current logged in user participate in this dialog as client
     */
    public function loggedInUserIsClient(): bool
    {
        return auth()->user()?->id === $this->client_id;
    }

    // Methods === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * For this specific DIALOG: create new message as OWNER
     * (add new `message` record in db: user_id = owner->id)
     */
    public function writeMessageByOwner(int $ownerId, string $message): void
    {
        $this->messages()->create([
            'user_id' => $ownerId,
            'message' => $message,
        ]);
        $this->client_new_messages++;
        $this->save();
    }

    /**
     * For this specific DIALOG: create new message as client
     * (add new `message` record in db: user_id = client->id)
     */
    public function writeMessageByClient(int $clientId, string $message): void
    {
        $this->messages()->create([
            'user_id' => $clientId,
            'message' => $message,
        ]);
        $this->owner_new_messages++;
        $this->save();
    }

    /**
     * Set owner's unread messages to zero
     */
    public function readByOwner(): void
    {
        // Disable touching updated_at
        $this->timestamps = false;
        $this->update(['owner_new_messages' => 0]);
    }

    /**
     * Set client's unread messages to zero
     */
    public function readByClient(): void
    {
        // Disable touching updated_at
        $this->timestamps = false;
        $this->update(['client_new_messages' => 0]);
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include dialogs which belong to advert
     */
    public function scopeForAdvert(Builder $query, Advert $advert): void
    {
        $query->where('advert_id', $advert->id);
    }

    /**
     * @comment Scope a query to only include dialogs in which logged in user participated as Client or as Seller
     *
     * @return Builder
     */
    public function scopeForUser(Builder $query): void
    {
        $query->where(function ($query) {
            $userId = auth()->user()?->id;

            return $query->where('owner_id', '=', $userId)
                ->orWhere('client_id', '=', $userId);
        });
    }

    /**
     * @comment Scope a query to only include dialogs which have unread messages and in which logged in user participated as Client or as Seller
     *
     * @return Builder
     */
    public function scopeUnreadByUser(Builder $query): void
    {
        $query->forUser()->where(function ($query) {
            return $query->where('owner_new_messages', '!=', 0)
                ->orWhere('client_new_messages', '!=', 0);
        });
    }
}
