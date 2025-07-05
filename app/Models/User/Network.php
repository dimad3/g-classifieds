<?php

declare(strict_types=1);

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class Network extends Model
{
    public const TWITTER = 'twitter';
    // public const TWITTER = 'twitter-oauth-2';
    public const GOOGLE = 'google';
    public const FACEBOOK = 'facebook';

    // public $timestamps = false;

    protected $table = 'user_networks';

    protected $fillable = ['network', 'identity'];

    public static function networksList(): array
    {
        return [
            self::TWITTER => 'Twitter',
            self::GOOGLE => 'Google',
            self::FACEBOOK => 'Facebook',
        ];
    }
}
