<?php

declare(strict_types=1);

namespace App\Models\Action;

use App\Models\Adverts\Category;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionCategory extends Model
{
    use HasFactory;

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'action_category';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function action()
    {
        return $this->belongsTo(Action::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====
}
