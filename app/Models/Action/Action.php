<?php

declare(strict_types=1);

namespace App\Models\Action;

use App\Models\Adverts\Advert\Advert;
use App\Models\Adverts\Category;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Action extends Model
{
    use HasFactory, Sluggable;

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'actions';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [];

    /**
     * This method returns an array with configuration options that define which attributes
     * should be used to generate the slug, how slugs should behave, and more
     * https://github.com/cviebrock/eloquent-sluggable
     *
     * @return array
     *               Return the sluggable configuration array for this model.
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Get the adverts for the action
     */
    public function adverts()
    {
        return $this->hasMany(Advert::class);
    }

    /**
     * @comment The category that belong to the action.
     */
    public function category(): HasOne
    {
        return $this->hasOne(Category::class, 'id', 'pivot_category_id');
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====
}
