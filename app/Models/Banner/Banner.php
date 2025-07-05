<?php

declare(strict_types=1);

namespace App\Models\Banner;

use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_MODERATION = 'moderation';

    public const STATUS_MODERATED = 'moderated';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'banner_banners';

    protected $guarded = ['id'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public static function statusesList(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_MODERATION => 'On Moderation',
            self::STATUS_MODERATED => 'moderated',
            self::STATUS_ORDERED => 'Payment',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    public static function formatsList(): array
    {
        return [
            '240x400',
        ];
    }

    public function view(): void
    {
        $this->assertIsActive();
        $this->views++;
        if ($this->views >= $this->limit) {
            $this->status = self::STATUS_CLOSED;
        }
        $this->save();
    }

    public function click(): void
    {
        $this->assertIsActive();
        $this->clicks++;
        $this->save();
    }

    public function sendToModeration(): void
    {
        if (! $this->isDraft()) {
            throw new \DomainException('Advert is not draft.');
        }
        $this->update([
            'status' => self::STATUS_MODERATION,
        ]);
    }

    public function cancelModeration(): void
    {
        if (! $this->isOnModeration()) {
            throw new \DomainException('Advert is not sent to moderation.');
        }
        $this->update([
            'status' => self::STATUS_DRAFT,
        ]);
    }

    public function moderate(): void
    {
        if (! $this->isOnModeration()) {
            throw new \DomainException('Advert is not sent to moderation.');
        }
        $this->update([
            'status' => self::STATUS_MODERATED,
        ]);
    }

    public function reject($reason): void
    {
        $this->update([
            'status' => self::STATUS_DRAFT,
            'reject_reason' => $reason,
        ]);
    }

    public function order(int $cost): void
    {
        if (! $this->isModerated()) {
            throw new \DomainException('Advert is not moderated.');
        }
        $this->update([
            'cost' => $cost,
            'status' => self::STATUS_ORDERED,
        ]);
    }

    public function pay(Carbon $date): void
    {
        if (! $this->isOrdered()) {
            throw new \DomainException('Advert is not ordered.');
        }
        $this->update([
            'published_at' => $date,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function getWidth(): int
    {
        return explode('x', $this->format)[0];
    }

    public function getHeight(): int
    {
        return explode('x', $this->format)[1];
    }

    public function canBeChanged(): bool
    {
        return $this->isDraft();
    }

    public function canBeRemoved(): bool
    {
        return $this->isDraft();
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOnModeration(): bool
    {
        return $this->status === self::STATUS_MODERATION;
    }

    public function isModerated(): bool
    {
        return $this->status === self::STATUS_MODERATED;
    }

    public function isOrdered(): bool
    {
        return $this->status === self::STATUS_ORDERED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    private function assertIsActive(): void
    {
        if (! $this->isActive()) {
            throw new \DomainException('Banner is not active.');
        }
    }
}
