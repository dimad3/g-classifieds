<?php

declare(strict_types=1);

namespace App\Models\Adverts\Advert;

use App\Models\Action\Action;
use App\Models\Adverts\Advert\Dialog\Dialog;
use App\Models\Adverts\Advert\Traits\AdvertRelationships;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Advert extends Model
{
    use AdvertRelationships, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_MODERATION = 'moderation';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_EXPIRED = 'expired';

    /**
     * The table associated with the model.
     */
    protected $table = 'advert_adverts';
    // public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are NOT mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     * https://laravel.com/docs/5.8/eloquent-mutators#attribute-casting
     */
    protected $casts = [
        // 24.11.2024 - AdvertsSeeder does not work with these casts:
        // 'published_at' => 'datetime',
        // 'expires_at' => 'datetime',
    ];

    // why do we need statusesList(): array -> for option labels in dropdowns
    public static function statusesList(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_MODERATION => 'On Moderation',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    // Accessors === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Get the excerpt from title.
     */
    public function getTitleExcerptAttribute(): string
    {
        return Str::limit($this->title, 16, ' ...');
    }

    // Adverts Management === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Update advert's status to 'active'
     */
    public function activate(Carbon $date): void
    {
        // if (! $this->isOnModeration()) {
        //     throw new \DomainException('For activating advert it must have status `moderation`.');     // put the error in the session
        if (! $this->canBeActivated()) {
            throw new \DomainException('For activating advert it must have status `moderation` or `expired`.');     // put the error in the session
        }
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'reject_reason' => null,
            'published_at' => $date,
            'expires_at' => $date->copy()->addDays(28),
        ]);
    }

    /**
     * Update advert's status to 'expired'. Is called only by a Cron job
     */
    public function expire(): void
    {
        if (! $this->canBeSetAsExpired()) {
            // Log the reason for failure
            Log::error('Cron job attempted to expire item, but it cannot be set as expired.', [
                'advert_id' => $this->id,
                'expires_at' => $this->expires_at,
                'current_status' => $this->status,
            ]);

            // Optionally throw an exception with a meaningful message
            // throw new \DomainException("Item cannot be set as expired. Timestamp: {$timestamp}, advert_id' = {$this->id}");
        }

        // $expiresAt = $this->expires_at > now() ? now() : $this->expires_at;
        $this->update([
            'status' => self::STATUS_EXPIRED,
            // 'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Update advert's status to 'draft'
     */
    public function reject($reason): void
    {
        if (! $this->canBeRejected()) {
            throw new \DomainException('For rejecting advert it must have status `moderation` or `active`.');     // put the error in the session
        }

        $this->update([
            'status' => self::STATUS_DRAFT,
            'reject_reason' => $reason,
        ]);
    }

    // Cabinet methods:

    /**
     * Update advert's status to 'moderation'
     */
    public function sendToModeration(): void
    {
        if (! $this->canBePublished()) {
            throw new \DomainException('For sending advert to moderation it must have status `draft` or `expired`.');     // put the error in the session
        }

        $this->update([
            'status' => self::STATUS_MODERATION,
            'published_at' => null,
            'expires_at' => null,
        ]);
    }

    /**
     * Update advert's status to 'closed'
     */
    public function close(): void
    {
        if (! $this->canBeClosed()) {
            throw new \DomainException('For closing advert it must have status `active` or `expired`.');     // put the error in the session
        }
        $this->update([
            'status' => self::STATUS_CLOSED,
            // 'expires_at' => null,
        ]);
    }

    /**
     * Update advert's status to 'draft'
     */
    public function restore(): void
    {
        if (! $this->isClosed()) {
            throw new \DomainException('For restoring advert it must have status `closed`.');     // put the error in the session
        }
        $this->update([
            'status' => self::STATUS_DRAFT,
        ]);
    }

    /**
     * Update advert's status to 'draft'
     */
    public function revertToDraft(): void
    {
        if (! $this->isOnModeration()) {
            throw new \DomainException('For reverting advert to draft it must have status `under moderation`.');     // put the error in the session
        }
        $this->update([
            'status' => self::STATUS_DRAFT,
        ]);
    }

    // Advert Statuses === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOnModeration(): bool
    {
        return $this->status === self::STATUS_MODERATION;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isRejected(): bool
    {
        // return $this->status === self::STATUS_DRAFT && (bool) $this->reject_reason;
        // return $this->status === self::STATUS_DRAFT && (bool) ! empty($this->reject_reason);
        return $this->status === self::STATUS_DRAFT && (bool) ! empty($this->reject_reason);
    }

    public function canBeActivated(): bool
    {
        // uncomment if direct publishing by user is prohibited
        // return $this->isOnModeration() || $this->isExpired();

        // comment this condition if direct publishing by user is prohibited
        return $this->isOnModeration() || $this->isExpired() || $this->isDraft();
    }

    public function canBePublished(): bool
    {
        return $this->isDraft() || $this->isExpired();
    }

    public function canBeEditedByOwner(): bool
    {
        return $this->isClosed() || $this->isDraft() || $this->isExpired();
    }

    public function canBeClosed(): bool
    {
        return $this->isActive() || $this->isExpired();
    }

    public function canBeSetAsExpired(): bool
    {
        return $this->expires_at < now() && $this->isActive();
    }

    public function canBeRejected(): bool
    {
        return $this->isOnModeration() || $this->isActive();
    }

    public function hasPhotos(): bool
    {
        // return \count($this->photos) > 0 ? true : false;
        return $this->photos()->exists() ? true : false;
    }

    public function hasActivePhotos(): bool
    {
        // return \count($this->photos) > 0 ? true : false;
        return $this->activePhotos()->exists() ? true : false;
    }

    public function hasPendingPhotos(): bool
    {
        // return \count($this->photos) > 0 ? true : false;
        return $this->pendingPhotos()->exists() ? true : false;
    }

    /**
     * Define whether current logged in user is this advert owner
     */
    public function loggedInUserIsOwner(): bool
    {
        return auth()->user()?->id === $this->user_id ? true : false;
    }

    // Advert's dialog === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * 1) For this specific ADVERT: find dialog with provided client
     * 2) For this specific DIALOG: create new message as OWNER
     * (add new `message` record in db: user_id = owner->id)
     */
    // public function writeOwnerMessage(int $toId, string $message): void
    public function writeOwnerMessage(int $clientId, string $message): void
    {
        // Do not need create new dialog because create new dialog CAN only client
        // For this specific ADVERT: find dialog with provided client
        $this->getDialogWith($clientId)
            // For this specific DIALOG: create new message as OWNER (add new `message` record in db: user_id = owner->id)
            ->writeMessageByOwner($this->user_id, $message);
    }

    /**
     * 1) For this specific ADVERT: find dialog with provided client
     * or if not found -> create new dialog with this client (add new `dialog` record in db)
     * 2) For this specific DIALOG: create new message as CLIENT
     * (add new `message` record in db: user_id = client->id)
     */
    // public function writeClientMessage(int $fromId, string $message): void
    public function writeClientMessage(int $clientId, string $message): Dialog
    {
        // For this specific ADVERT: find dialog with provided client
        // or if not found -> create new dialog with this client (add new `dialog` record in db)
        // Create new dialog CAN only client
        $dialog = $this->getOrCreateDialogWith($clientId);
        // For this specific DIALOG: create new message as CLIENT (add new `message` record in db: user_id = client->id)
        $dialog->writeMessageByClient($clientId, $message);

        return $dialog;
    }

    public function readClientMessages(int $userId): void
    {
        $this->getDialogWith($userId)->readByClient();
    }

    public function readOwnerMessages(int $userId): void
    {
        $this->getDialogWith($userId)->readByOwner();
    }

    public function allowsMessages(): bool
    {
        return ($this->isActive()) ? true : false;
    }

    public function hasDialogWereUserIsClient(): bool
    {
        return $this->dialogWereUserIsClient()->exists();
    }

    // Other Methods === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function getPrice(Collection $assignedAttributes): ?string // Nullable Return Type
    {
        foreach ($assignedAttributes as $attribute) {
            if ($attribute->isPrice()) {
                // return (float) ($attribute->pivot->value);
                return format_price($attribute->pivot->value);
            }
        }

        return null;
    }

    public function hasPrice(Collection $assignedAttributes): bool
    {
        foreach ($assignedAttributes as $attribute) {
            if ($attribute->isPrice()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get advert's assigned attributes with their values
     */
    // public function assignedAttributesValues(): Collection
    // {
    //     // ($assignedValues = AttributeValue::forAdvert($this)->with('attribute')->get());

    //     // return $assignedValues->map(function (AttributeValue $attributeValue) use ($assignedValues) {
    //     //     ($attribute = $attributeValue->attribute);
    //     //     $attribute->value = $assignedValues->where('attribute_id', $attribute->id)->first()->value;
    //     //     return $attribute;
    //     // });

    //     // return
    //     // dd($this->loadMissing([
    //     //     'attributes' => function ($query) {
    //     //         $query->columnsList()
    //     //             ->select('name')
    //     //             // ->select('name', 'sort', 'is_in_columns_list')
    //     //             // ->where('is_in_columns_list', true)
    //     //             ->orderBy('sort');
    //     //     }]));
    // }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include adverts with status 'active'
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @comment Scope a query to only include adverts with status 'expired'
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * @comment Scope a query to only include adverts which are not expired
     */
    public function scopeNotExpired(Builder $query): void
    {
        $query->where('expires_at', '>=', now());
    }

    /**
     * @comment Scope a query to only include published adverts
     */
    public function scopePublished(Builder $query): void
    {
        // $query->where('status', self::STATUS_ACTIVE)->where('expires_at', '>=', now());
        // $query->active()->notExpired();
        $query->active();
    }

    /**
     * @comment Scope a query to only include unpublished adverts
     */
    public function scopeUnpublished(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_ACTIVE);
        // $query->whereNot(function ($q) {
        //     $q->published();
        // });
    }

    /**
     * @comment Scope a query to only include adverts from the provided period
     */
    public function scopeForPeriod(Builder $query, string $field, ?string $period = null): Builder
    {
        return match ($period) {
            // 'today' => $query->whereDate('published_at', today()),
            'today' => $query->whereBetween($field, [today()->startOfDay(), now()]),
            // 'yesterday' => $query->whereDate('$field', today()->subDay()),
            'yesterday' => $query->whereBetween($field, [
                today()->subDay()->startOfDay(),
                today()->subDay()->endOfDay(), // if date + time is set it is much faster than date
            ]),
            'last_three_days' => $query->whereBetween($field, [today()->subDays(2), now()]),
            'last_five_days' => $query->whereBetween($field, [today()->subDays(4), now()]),
            'this_week' => $query->whereBetween($field, [today()->startOfWeek(), now()]),
            'previous_week' => $query->whereBetween($field, [
                today()->subWeek()->startOfWeek(),
                today()->subWeek()->endOfWeek(),
            ]),
            'this_month' => $query->whereBetween($field, [today()->startOfMonth(), now()]),
            'previous_month' => $query->whereBetween($field, [
                today()->subMonth()->startOfMonth(),
                today()->subMonth()->endOfMonth(),
            ]),
            'other' => $query->where($field, '<', today()->subMonth()->startOfMonth()),
            default => $query
        };
    }

    /**
     * @comment Scope a query to only include adverts which belong to spesific category and its descendants
     */
    public function scopeForCategory(Builder $query, Category $category): void
    {
        $query->whereIn('category_id', array_merge(
            [$category->id],
            $category->descendants()->pluck('id')->toArray()
        ));
    }

    /**
     * @comment Scope a query to only include adverts which belong to spesific region and its descendants
     */
    public function scopeForRegion(Builder $query, Region $region): void
    {
        $query->whereIn('region_id', array_merge(
            [$region->id],
            $region->descendants()->pluck('id')->toArray()
        ));
    }

    /**
     * Scope a query to only include adverts associated with a specific action.
     */
    public function scopeForAction(Builder $query, ?Action $action): Builder
    {
        /**
         * If $action is null, the return statement will stop the execution of further query modifications,
         * and the query will remain unfiltered.
         * This avoids fetching all records immediately, allowing for better performance
         * and the flexibility to add additional query logic outside the scope.
         */
        if ($action) {
            $query->where('action_id', $action->id);
        }

        return $query;
    }

    /**
     * Scope a query to only include adverts belonging to the specified user.
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @comment Scope a query to only include favorite adverts which belong to Authenticated user
     */
    public function scopeFavoredByUser(Builder $query, User $user): void
    {
        // whereHas() - https://stackoverflow.com/questions/30231862/laravel-eloquent-has-with-wherehas-what-do-they-mean
        $query->active()->notExpired()->whereHas('usersMarkedMeAsFavorite', function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);
        });
    }

    /**
     * @comment Scope a query to only include adverts in which dialogs the user participated (as owner OR as client)
     */
    public function scopeWithUserDialogs(Builder $query): void
    {
        $query->has('userDialogs');
    }

    // Dialogs' helpers === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * For this specific ADVERT: find dialog with provided client
     */
    // public function getDialogWith(int $clientId): Dialog
    private function getDialogWith(int $clientId): Dialog
    {
        $dialog = $this->dialogs()->where([
            'owner_id' => $this->user_id,
            'client_id' => $clientId,
        ])->first();
        if (! $dialog) {
            throw new \DomainException('Dialog is not found.');
        }

        return $dialog;
    }

    /**
     * For this specific ADVERT: find dialog with provided client
     * or if not found -> create new dialog with this client (add new `dialog` record in db)
     */
    private function getOrCreateDialogWith(int $clientId): Dialog
    // public function getOrCreateDialogWith(int $clientId): Dialog
    {
        if ($clientId === $this->user_id) {
            throw new \DomainException('Cannot send message to myself.');
        }

        return $this->dialogs()->firstOrCreate([
            'owner_id' => $this->user_id,
            'client_id' => $clientId,
        ]);
    }
}
