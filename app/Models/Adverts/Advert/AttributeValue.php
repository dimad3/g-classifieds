<?php

declare(strict_types=1);

namespace App\Models\Adverts\Advert;

use App\Models\Adverts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'advert_attribute_values';

    // /**
    //  * The attributes that are mass assignable.
    //  */
    // protected $fillable = ['attribute_id', 'value'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['advert_id'];

    /**
     * All of the relationships to be touched.
     * https://laravel.com/docs/10.x/eloquent-relationships#touching-parent-timestamps
     *
     * @var array
     */
    protected $touches = ['advert'];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Get an advert to which attribute's value belongs to
     */
    public function advert()
    {
        return $this->belongsTo(Advert::class, 'advert_id', 'id');
    }

    /**
     * Get an attribute to which value belongs to
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id', 'id');
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include attributes values which belong to the provided advert
     */
    public function scopeForAdvert(Builder $query, Advert $advert): void
    {
        $query->where('advert_id', $advert->id);
    }
}
