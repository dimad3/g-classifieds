<?php

declare(strict_types=1);

namespace App\Models\User;

use App\Models\Adverts\Advert\Advert;
use App\Models\Ticket\Message;
use App\Models\Ticket\Ticket;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MODERATOR = 'moderator';

    public const STATUS_WAIT = 'wait';

    public const STATUS_ACTIVE = 'active';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'email_verified_at',
        'phone',
        'password',
        'status',
        'role',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'phone_auth' => 'boolean',
        'phone_verified' => 'boolean',
        'phone_verify_token_expire' => 'datetime',
    ];

    public static function rolesList(): array
    {
        return [
            self::ROLE_USER => 'User',
            self::ROLE_MODERATOR => 'Moderator',
            self::ROLE_ADMIN => 'Admin',
        ];
    }

    public static function statusesList(): array
    {
        return [
            self::STATUS_WAIT => 'Waiting',
            self::STATUS_ACTIVE => 'Active',
        ];
    }

    // Methods for Users managing ========================

    /**
     * Add new user in db. Is used from user register form only?
     */
    public static function register(string $name, string $email, string $password): self
    {
        $data = [
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => self::ROLE_USER,
            'status' => self::STATUS_WAIT,
        ];

        return static::create($data);
    }

    /**
     * Create a new user who was authanticated by a network API.
     */
    public static function registerByNetwork(array $data): self
    {
        $user = static::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'status' => $data['status'],
        ]);
        $user->networks()->create([
            'network' => $data['network'],
            'identity' => $data['identity'],
        ]);

        return $user;
    }

    /**
     * Add new user in db. Is used from admin panel to add users
     */
    public static function storeUser(string $name, string $email, string $role): self
    {
        return static::create([
            'name' => $name,
            'email' => $email,
            'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret -> todo: change on production
            'role' => $role,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    // Relationships ========================

    /**
     * @comment Get all adverts which belong to user.
     */
    public function adverts()
    {
        return $this->hasMany(Advert::class, 'user_id', 'id');
    }

    /**
     * @comment Define a many-to-many relationship. One user can mark many adverts as favorite
     */
    public function favorites()
    {
        return $this->belongsToMany(Advert::class, 'advert_favorites', 'user_id', 'advert_id');
    }

    /**
     * @comment Get all networks which belong to user.
     */
    public function networks()
    {
        return $this->hasMany(Network::class, 'user_id', 'id');
    }

    /**
     * @comment Get all tickets which belong to user.
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'ticket_id', 'id');
    }

    /**
     * @comment Get all tickets' messages for the user.
     */
    public function messages(): HasManyThrough
    {
        return $this->hasManyThrough(Message::class, Ticket::class);
    }

    /**
     * @comment Get received tickets' messages for the user.
     */
    public function messagesReceived(): HasManyThrough
    {
        return $this->messages()->whereNot('ticket_messages.user_id', auth()->user()?->id);
    }

    /**
     * @comment Get sent tickets' messages for the user.
     */
    public function messagesSent(): HasManyThrough
    {
        return $this->messages()->where('ticket_messages.user_id', auth()->user()?->id);
    }

    /**
     * Update user in db. Is used from admin panel only
     */
    public function updateUser(string $name, string $email, string $role): bool
    {
        return $this->update([
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ]);
    }

    /**
     * L11 03:16:00 - explanation. todo: do we need it?
     */
    // public function findForPassport($identifier)
    // {
    //     return self::where('email', $identifier)->where('status', self::STATUS_ACTIVE)->first();
    // }

    /**
     * Change the user's role.
     *
     * This method allows updating the user's role to a new one, as long as:
     * - The new role exists in the predefined list of roles.
     * - The new role is different from the current role.
     *
     * If the role doesn't exist in the list or if the new role is the same as
     * the current one, an exception will be thrown.
     *
     * @param  string  $role  The new role to assign to the user.
     *
     * @throws \InvalidArgumentException If the provided role is not in the predefined roles list.
     * @throws \DomainException If the user already has the provided role.
     */
    public function changeRole(string $role): void
    {
        // Check if the provided role exists in the predefined list of roles.
        if (! array_key_exists($role, self::rolesList())) {
            // Throw an exception if the role is not defined.
            throw new \InvalidArgumentException('Undefined role "' . $role . '"');
        }

        // Check if the user already has the provided role.
        if ($this->role === $role) {
            // Throw an exception if the user already has the given role.
            throw new \DomainException("Role '{$role}' is already assigned for user '{$this->name}'.");
        }

        // Update the user's role if both checks pass.
        $this->update(['role' => $role]);
    }

    // Methods for User Status & Role detection ========================

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAIT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isModerator(): bool
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    // Methods for Phone verification ========================

    /**
     * Marks the user's phone as unverified.
     *
     * Resets phone verification attributes, including the authentication status,
     * verification status, token, and token expiration time. Saves the changes to
     * the database, and will fail if saving is unsuccessful.
     */
    public function unverifyPhone(): void
    {
        // Disable phone-based authentication
        $this->phone_auth = false;

        // Mark the phone as unverified
        $this->phone_verified = false;

        // Clear the phone verification token and expiration time
        $this->phone_verify_token = null;
        $this->phone_verify_token_expire = null;

        // Persist changes to the database; throws an error if saving fails
        $this->saveOrFail();
    }

    /**
     * Generates a verification token and expiration time, and stores them in the database for the user's phone verification.
     *
     * @param  Carbon  $now  The current timestamp used to calculate the token expiration.
     * @return string The generated phone verification token.
     *
     * @throws \DomainException If the user's phone number is missing or if a valid token has already been generated.
     */
    public function requestPhoneVerificationToken(Carbon $now): string
    {
        // Check if the user has a phone number set; if not, throw an exception
        if (empty($this->phone)) {
            throw new \DomainException('Phone number is empty.');
        }

        // Ensure no active token is already in use; throw exception if token is still valid
        if (
            ! empty($this->phone_verify_token)  // Check if a token exists
            && $this->phone_verify_token_expire  // Ensure expiration time is set
            && $this->phone_verify_token_expire->gt($now)  // Confirm token hasn't expired
        ) {
            throw new \DomainException('Token is already requested.');
        }

        // Reset phone verification status and generate a new token with expiration
        $this->phone_verified = false;  // Mark phone as unverified
        $this->phone_verify_token = (string) random_int(10000, 99999);  // Generate a 5-digit token
        $this->phone_verify_token_expire = $now->copy()->addSeconds(300);  // Set token expiry to 5 minutes from now
        $this->saveOrFail();  // Save changes to the database, throwing exception on failure

        // Return the newly generated token for use in verification
        return $this->phone_verify_token;
    }

    /**
     * Verifies the user's phone number using the provided verification token and current time.
     *
     * This method checks if the provided token matches the stored token and ensures that it hasn't expired.
     * If the token is valid and not expired, the phone is marked as verified, and the token and expiration are cleared.
     *
     * @param  string  $token  The verification token to check.
     * @param  Carbon  $now  The current time to check for token expiration.
     *
     * @throws \DomainException If the token is incorrect or expired.
     */
    public function verifyPhone($token, Carbon $now): void
    {
        // Check if the provided token matches the stored verification token.
        // If the token is incorrect, throw an exception.
        if ($token !== $this->phone_verify_token) {
            throw new \DomainException('Invalid verify token.');
        }

        // Check if the verification token has expired.
        // If the token is expired, throw an exception.
        if ($this->phone_verify_token_expire->lt($now)) {
            throw new \DomainException('Token is expired.');
        }

        // If token is valid and not expired, mark the phone as verified.
        $this->phone_verified = true;

        // Clear the phone verification token and its expiration as the phone is now verified.
        $this->phone_verify_token = null;
        $this->phone_verify_token_expire = null;

        // Save the updated user data.
        $this->saveOrFail();
    }

    /**
     * Checks if the user's phone number has been verified.
     *
     * @return bool True if the phone is verified, false otherwise.
     */
    public function isPhoneVerified(): bool
    {
        // Returns the 'phone_verified' status from the database (true or false).
        return $this->phone_verified;
    }

    /**
     * check whether profile's attributes (name + lastname + phone_verified) is set
     */
    public function hasFilledProfile(): bool
    {
        // return ! empty($this->name) && ! empty($this->last_name) && $this->isPhoneVerified();
        return ! empty($this->name) && ! empty($this->phone);
    }

    // Methods for Two-Factor Authentication ========================

    /**
     * set phone_auth attribute to true
     */
    public function enablePhoneAuth(): void
    {
        if (! empty($this->phone) && ! $this->isPhoneVerified()) {
            throw new \DomainException('The phone number is either empty or unverified.');
        }
        $this->phone_auth = true;
        $this->saveOrFail();
    }

    /**
     * set phone_auth attribute to false
     */
    public function disablePhoneAuth(): void
    {
        $this->phone_auth = false;
        $this->saveOrFail();
    }

    /**
     * Check if phone-based authentication is enabled for the user.
     *
     * @return bool True if phone authentication is enabled, false otherwise.
     */
    public function isPhoneAuthEnabled(): bool
    {
        // Cast the phone_auth attribute to boolean to ensure it returns true or false.
        return (bool) $this->phone_auth;
    }

    // Methods for Favorite adverts ========================

    public function addToFavorites(int $advertId): void
    {
        if ($this->hasInFavorites($advertId)) {
            throw new \DomainException('This advert is already added to favorites.');
        }
        $this->favorites()->attach($advertId);
    }

    public function removeFromFavorites(int $advertId): void
    {
        $this->favorites()->detach($advertId);
    }

    public function hasInFavorites(int $advertId): bool
    {
        return $this->favorites()->where('id', $advertId)->exists();
    }

    /**
     * Verifies the user's email by updating the user's status to active.
     *
     * @throws \DomainException If the user is already verified, a DomainException is thrown.
     */
    public function verifyEmail(): void
    {
        // Check if the user is in the "waiting" state and requires email verification
        // if (! $this->isWaiting()) {
        if ($this->hasVerifiedEmail()) {
            throw new \DomainException('User\'s email is already verified.');
        }

        if (! $this->hasVerifiedEmail()) {
            // Manually trigger the email verification
            $this->markEmailAsVerified();
        }

    }

    /**
     * Determine if the user has verified their email address.
     *
     * This method overrides the `hasVerifiedEmail()` method from the
     * `MustVerifyEmail` interface.
     */
    public function hasVerifiedEmail(): bool
    {
        // return ! is_null($this->email_verified_at);
        return $this->isActive() && ! is_null($this->email_verified_at) ? true : false;
    }

    /**
     * Mark the user's email as verified.
     *
     * This method overrides the `markEmailAsVerified()` method from the
     * `MustVerifyEmail` interface. It sets the `email_verified_at` field
     * to the current timestamp and update the user's status to active,
     * indicating that the user's email has been successfully verified.
     * After updating the attribute, it saves the model to persist the changes in the database.
     *
     * @return bool Returns true if the model was successfully saved, false otherwise.
     */
    public function markEmailAsVerified(): bool
    {
        // Use forceFill to set the 'email_verified_at' attribute to the current timestamp.
        // The forceFill method bypasses the model's mass assignment protection,
        // allowing the 'email_verified_at' field to be filled even if it's not in the $fillable array.
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(), // Set the 'email_verified_at' field to the current timestamp
            'status' => self::STATUS_ACTIVE,                // Set status to active
        ])->save(); // Save the model, which will persist the changes to the database
    }

    // Scopes ========================

    /**
     * @comment Scope a query to only include particular network user from the `user_networks` table.
     */
    public function scopeByNetwork(Builder $query, string $network, string $identity): void
    {
        $query->whereHas('networks', function (Builder $query) use ($network, $identity): void {
            $query->where('network', $network)->where('identity', $identity);
        });
    }
}
