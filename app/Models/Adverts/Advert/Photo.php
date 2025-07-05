<?php

declare(strict_types=1);

namespace App\Models\Adverts\Advert;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'advert_photos';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['file', 'status'];

    // why do we need statusesList(): array -> for option labels in dropdowns
    // public static function statusesList(): array
    // {
    //     return [
    //         self::STATUS_ACTIVE => 'Active',
    //         self::STATUS_PENDING => 'Pending',
    //     ];
    // }

    /**
     * All of the relationships to be touched.
     * https://laravel.com/docs/10.x/eloquent-relationships#touching-parent-timestamps
     *
     * @var array
     */
    protected $touches = ['advert'];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Get an advert to which photo belongs to
     */
    public function advert()
    {
        return $this->belongsTo(Advert::class, 'advert_id', 'id');
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include active photos
     */
    public function scopeActive(Builder $query, Advert $advert): void
    {
        $query->where('advert_id', $advert->id)->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @comment Scope a query to only include pending photos
     */
    public function scopePending(Builder $query, Advert $advert): void
    {
        $query->where('advert_id', $advert->id)->where('status', self::STATUS_PENDING);
    }
}
