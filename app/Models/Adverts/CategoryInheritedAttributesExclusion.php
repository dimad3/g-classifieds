<?php

declare(strict_types=1);

namespace App\Models\Adverts;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryInheritedAttributesExclusion extends Model
{
    use HasFactory;

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'advert_category_inherited_attributes_exclusions';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====
}
