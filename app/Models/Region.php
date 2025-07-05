<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Adverts\Advert\Advert;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\NodeTrait;

class Region extends Model
{
    use HasFactory;
    use NodeTrait;  // Add this trait for nested set functionality

    /**
     * If you do not wish to have timestamps columns automatically maintained, set the property to false
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'sort', 'slug', 'parent_id'];

    // Relationships === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * @comment Get the adverts for the region
     */
    public function adverts(): HasMany
    {
        return $this->hasMany(Advert::class);
    }

    // Methods === ==== === ==== ==== ==== ==== === ==== === ==== ==== ==== ====

    /**
     * Get regions's path (slugs of ancestors' + category's slug divided by "/")
     */
    public function getPath(): string
    {
        // Lesson 8 - 01:41:00 - explanation
        // bug: cits-15/cits-1/cits/cits-152 -> commented on 12.10.2024
        // return implode('/', array_merge($this->ancestors()->pluck('slug')->toArray(), [$this->slug]));
        // resolved: (see Bug Nr.1 and https://chatgpt.com/c/670b8ba6-e800-800f-b367-9228dbc34ede)?

        $ancestorsAndSelf = $this->ancestorsAndSelf($this->id)->sortBy('_lft');

        return implode('/', $ancestorsAndSelf->pluck('slug')->toArray());
    }

    /**
     * Create unique slug inside parent region.
     * from 14.10.2024 - is not used because new method is used in RegionService.php (see https://chatgpt.com/c/670d5c8f-be70-800f-a203-9300d3ec2ab7)
     * https://laracasts.com/discuss/channels/general-discussion/l5-routing-1
     *
     * @param  string  $name  string to be transformed in slug
     * @param  int  $parentId  parent region for which slug will be unique
     */
    // public function makeUniqueSlugForParent(string $name, ?int $parentId): string
    // {
    //     $slug = Str::slug($name);

    //     $count = $this->where('parent_id', $parentId)
    //         ->whereKeyNot($this->id)
    //         ->whereRaw("slug RLIKE '^{$slug}(-[0-9]+)?$'")->count();

    //     return $count ? "{$slug}-{$count}" : $slug;
    // }

    // Scopes ======================================================

}
