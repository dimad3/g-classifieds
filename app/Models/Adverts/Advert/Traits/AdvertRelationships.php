<?php

declare(strict_types=1);

namespace App\Models\Adverts\Advert\Traits;

use App\Models\Action\Action;
use App\Models\Adverts\Advert\AttributeValue;
use App\Models\Adverts\Advert\Dialog\Dialog;
use App\Models\Adverts\Advert\Dialog\Message;
use App\Models\Adverts\Advert\Photo;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use App\Models\Region;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait AdvertRelationships
{
    /**
     * @comment The attributes that belong (WERE SET) to the advert (NOT ALL possible attributes) (many-to-many).
     */
    public function attributesWithValues()
    {
        return $this->belongsToMany(Attribute::class, 'advert_attribute_values')
            ->withPivot('value')
            ->orderBy('sort');
    }

    /**
     * @comment Get attributes and its corrsponding values which belongs to advert.
     * is used only in: \app\Services\Adverts\AdvertService.php
     */
    public function attributesValues()
    {
        return $this->hasMany(AttributeValue::class, 'advert_id', 'id');
    }

    // /**
    //  * @comment Get attributes and its corrsponding values which belongs to advert.
    //  */
    // public function attributesAsColumn()
    // {
    //     return $this->attributes();
    // }

    /**
     * @comment Get an action to which advert belongs to
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    /**
     * @comment Get a category to which advert belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * @comment Get all dialogs which belong to advert.
     */
    public function dialogs(): HasMany
    {
        return $this->hasMany(Dialog::class, 'advert_id', 'id');
    }

    /**
     * @comment Get all the advert's dialogs in which logged in user participated as Client or as Seller.
     */
    public function userDialogs(): HasMany
    {
        // 1-st approach = 0.6 ms
        return $this->dialogs()->forUser();
    }

    /**
     * @comment Get the advert's dialog in which logged in user is client.
     */
    public function dialogWereUserIsClient(): HasOne
    {
        return $this->dialogs()->where('client_id', auth()->user()?->id)->one();
    }

    /**
     * @comment Get all the advert's messages.
     */
    public function messages(): HasManyThrough
    {
        return $this->hasManyThrough(Message::class, Dialog::class);
    }

    /**
     * @comment Get the advert's the last updated dialog for logged in user.
     */
    // public function userLastUpdatedDialog(): HasOne
    // {
    //     // 1-st approach = 0.75 ms
    //     return $this->userDialogs()->orderByDesc('updated_at')->one();
    // }

    /**
     * @comment Get the advert's the last updated dialog.
     * on 29.04.2024 bug found -> extra tests must be implemented
     */
    // public function lastUpdatedDialog(): HasOne
    // {
    //     // 1-st approach = 3 ms
    //     // return $this->hasOne(Dialog::class)->latestOfMany('updated_at');

    //     // 2-nd approach = 0.4 ms
    //     return $this->dialogs()->orderByDesc('updated_at')->one();
    // }

    /**
     * @comment Get all photos which belong to advert.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'advert_id', 'id');
    }

    /**
     * @comment Get ONLY active photos which belongs to advert.
     */
    public function activePhotos(): HasMany
    {
        return $this->photos()->where('status', 'active');
    }

    /**
     * @comment Get ONLY pending photos which belongs to advert.
     */
    public function pendingPhotos(): HasMany
    {
        return $this->photos()->where('status', 'pending');
    }

    /**
     * @comment Get FIRST active photo which belongs to advert.
     * todo: see https://laracasts.com/discuss/channels/eloquent/eager-loading-constraints-with-limit-clauses?page=1&replyId=71655
     *
     * @return HasMany
     */
    public function defaultPhoto()
    {
        // return $this->hasOne(Photo::class);
        // return $this->activePhotos();
        // return $this->activePhotos()->limit(1);
        return $this->hasOne(Photo::class)->where('status', 'active')->orderBy('id');
    }

    /**
     * @comment Get a region to which advert belongs to
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    /**
     * @comment Get a user to which advert belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @comment Define a many-to-many relationship. One advert can be marked as favorite by many users
     */
    public function usersMarkedMeAsFavorite()
    {
        return $this->belongsToMany(User::class, 'advert_favorites', 'advert_id', 'user_id');
    }
}
