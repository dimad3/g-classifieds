<?php

declare(strict_types=1);

namespace App\Models\Action;

use App\Models\Adverts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ActionAttributeSetting extends Pivot
    // class ActionAttributeSetting extends Model // To pass factory tests -> switch the base class of ActionAttributeSetting from Pivot to Model
{
    use HasFactory;

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'action_attribute_settings';

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'required' => 'boolean',
        'column' => 'boolean',
        'excluded' => 'boolean',
    ];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function action()
    {
        return $this->belongsTo(Action::class);
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Scope a query to only include required attributes for one specific action
     */
    public function scopeIsColumn(Builder $query, array $attributes, ?Action $action): Builder
    {
        return $query->whereIn('attribute_id', $attributes)->where('column', true)->where('action_id', $action ? $action->id : null);
    }

    /**
     * @comment Scope a query to only include excluded attributes (from allAttributes Collection) for one specific action
     */
    public function scopeIsExcluded(Builder $query, array $attributes, ?Action $action): Builder
    {
        return $query->whereIn('attribute_id', $attributes)->where('excluded', true)->where('action_id', $action ? $action->id : null);
    }

    /**
     * @comment Scope a query to only include required attributes for one specific action
     */
    public function scopeIsRequired(Builder $query, array $attributes, ?Action $action): Builder
    {
        return $query->whereIn('attribute_id', $attributes)->where('required', true)->where('action_id', $action ? $action->id : null);
    }
}
