<?php

declare(strict_types=1);

namespace App\Http\Resources\Adverts;

use App\Models\Adverts\Advert\AttributeValue;
use App\Models\Adverts\Advert\Photo;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property int $region_id
 * @property string $title
 * @property string $content
 * @property int $price
 * @property Carbon $published_at
 * @property Carbon $expires_at
 * @property User $user
 * @property Region $region
 * @property Category $category
 * @property AttributeValue[] $values
 * @property Photo[]|Collection $photos
 */
class AdvertDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'user' => [
                'name' => $this->user->name,
                'phone' => $this->user->phone,
            ],
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'region' => $this->region ? [
                'id' => $this->region->id,
                'name' => $this->region->name,
            ] : [],
            'title' => $this->title,
            'content' => $this->content,
            // 'price' => $this->price,
            'date' => [
                'published' => $this->published_at,
                'expires' => $this->expires_at,
            ],
            'attributes' => $this->attributesWithValues->map(function (Attribute $attribute) {
                return [
                    'name' => $attribute->name,
                    'value' => $attribute->pivot->value,
                ];
            }),
            'photos' => $this->activePhotos->map(function (Photo $photo) {
                return [
                    'photo' => $photo->file,
                ];
            }),
        ];
    }
}
