<?php

declare(strict_types=1);

namespace Database\Factories\User;

use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $statusActive = $this->faker->boolean;
        $phoneActive = $this->faker->boolean;

        return [
            'name' => $this->faker->firstname,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => $statusActive ? Carbon::now()->subHours(48) : null,
            'phone' => $this->faker->unique()->phoneNumber,
            'phone_verified' => $phoneActive,
            'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
            'remember_token' => Str::random(10),
            'role' => $statusActive ? $this->faker->randomElement([User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN]) : User::ROLE_USER,
            'status' => $statusActive ? User::STATUS_ACTIVE : User::STATUS_WAIT,
            'phone_verify_token' => $phoneActive ? null : $this->faker->numberBetween(9999, 99999),
            'phone_verify_token_expire' => $phoneActive ? null : Carbon::now()->addSeconds(300),
        ];
    }

    /**
     * Define a model state for an admin user.
     *
     * This state overrides specific attributes of the User model to ensure
     * that any user created with this state has the role of "Admin" and an "Active" status.
     *
     * Using this method simplifies the process of generating admin users
     * in tests or database seeding, making the role and status consistent and explicit.
     *
     * Usage:
     * To create a user with admin privileges, you can call this state method within a factory
     * chain, like so:
     * $adminUser = User::factory()->admin()->create();
     *
     * @return Factory
     */
    public function admin()
    {
        return $this->state([
            // Set the user role to "Admin", granting full permissions in the application
            'role' => User::ROLE_ADMIN,

            // Set the user status to "Active", indicating they are fully authorized and verified
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
