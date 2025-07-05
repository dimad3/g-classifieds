<?php

declare(strict_types=1);

namespace App\Models\Adverts;

use App\Models\Action\Action;
use App\Models\Action\ActionAttributeSetting;
use App\Models\Adverts\Advert\Advert;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attribute extends Model
{
    use HasFactory;

    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_FLOAT = 'float';

    public const TYPE_JSON = 'json';

    public const TYPE_BOOLEAN = 'boolean';

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'advert_attributes';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     * https://laravel.com/docs/8.x/eloquent-mutators#attribute-casting
     */
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    // protected $with = ['adverts.pivot.value'];  // 09.02.24 Call to undefined relationship [pivot] on model

    public static function typesList(): array
    {
        return [
            self::TYPE_STRING => 'String',
            self::TYPE_INTEGER => 'Integer',
            self::TYPE_FLOAT => 'Float',
            self::TYPE_JSON => 'JSON',
            self::TYPE_BOOLEAN => 'Boolean',
        ];
    }

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Get a category to which attribute belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * @comment The adverts that belong to the attribute (many-to-many).
     */
    public function adverts(): BelongsToMany
    {
        return $this->belongsToMany(Advert::class, 'advert_attribute_values')->withPivot('value');
    }

    /**
     * @comment The actions that belong to the attribute (many-to-many).
     */
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_attribute_settings')->withPivot(['required', 'column', 'excluded']);
    }

    /**
     * @comment The actions that belong to the attribute, for which required setting is set to true (many-to-many)
     */
    public function actionsForWhichIAmRequired(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_attribute_settings')
            ->withPivot(['required', 'column', 'excluded'])
            ->where('required', true);
    }

    /**
     * @comment The actions that belong to the attribute, for which column setting is set to true (many-to-many).
     */
    public function actionsForWhichIAmColumn(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_attribute_settings')
            ->withPivot(['required', 'column', 'excluded'])
            ->where('column', true);
    }

    /**
     * @comment The actions that belong to the attribute, for which column setting is set to true (many-to-many).
     */
    public function actionsForWhichIWillBeExcluded(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_attribute_settings')
            ->withPivot(['required', 'column', 'excluded'])
            ->where('excluded', true);
    }

    /**
     * @comment Get the settings for the attribute
     */
    public function settings(): HasMany
    {
        return $this->hasMany(ActionAttributeSetting::class);
    }

    /**
     * @comment The adverts that belong to the attribute (many-to-many).
     */
    public function setting(): HasOne
    {
        return $this->hasOne(ActionAttributeSetting::class)->where('action_id', null);
    }

    // Getting Attributes Types === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    public function isString(): bool
    {
        return $this->type === self::TYPE_STRING;
    }

    public function isInteger(): bool
    {
        return $this->type === self::TYPE_INTEGER;
    }

    public function isFloat(): bool
    {
        return $this->type === self::TYPE_FLOAT;
    }

    public function isSelect(): bool
    {
        ($types = array_keys(Attribute::typesList())); // all types
        ($typesToExclude = [self::TYPE_BOOLEAN, self::TYPE_JSON]); // items you want to exclude

        // Exclude items from the available types
        ($validTypes = array_diff($types, $typesToExclude));

        /**
         * A "\" before the beginning of a function represents the Global Namespace.
         * Putting it there will ensure that the function called is from the global namespace,
         * even if there is a function by the same name in the current namespace.
         */
        // return \count($this->options) > 0;  // empty JSON array returns 1
        // return \count($this->options) > 1 && ! $this->isJson();
        // return $this->isString() && \count((array) $this->options) > 0;
        return \count($this->options) > 1 && in_array($this->type, $validTypes);
    }

    public function isBoolean(): bool
    {
        return $this->type === self::TYPE_BOOLEAN;
    }

    public function isJson(): bool
    {
        return $this->type === self::TYPE_JSON;
    }

    public function isPrice(): bool
    {
        return $this->name === 'Cena';
    }

    // public function isRequired(Collection $requiredAttributes): bool
    // {
    //     return $requiredAttributes->contains($this);
    // }

    // public function isInColumnsList(): bool
    // {
    //     return $this->is_in_columns_list === true;
    // }

    // Scopes === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====
}
