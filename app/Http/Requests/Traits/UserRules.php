<?php

declare(strict_types=1);

namespace App\Http\Requests\Traits;

use App\Models\User\User;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

trait UserRules
{
    protected function commonUserRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:64'],
            'last_name' => ['string', 'min:2', 'max:64'],
            'email' => ['string', 'min:6', 'max:128', 'email'],
            'phone' => ['string', 'min:4', 'max:32', 'regex:/^\+?[0-9]{4,32}$/'],
            // 'phone' => ['string', 'min:2', 'max:32', 'phone:LENIENT'],
            'password' => ['string', 'min:6', 'max:255'],
            'role' => ['string', 'min:2', 'max:16', Rule::in(array_keys(User::rolesList()))],
            'status' => ['string', 'min:2', 'max:16', Rule::in(array_keys(User::statusesList()))],
        ];

        return $rules;
    }

    /**
     * Add extra rules to Common Rules depending on some conditions
     *
     * @param  array  $data  Data to be validated
     * @param  bool  $isActionUpdate  if the input will be updated set to 'true', if stored set to 'false'
     */
    protected function allUserRules(array $data, bool $isActionUpdate = false): array
    {
        $rules = $this->commonUserRules();

        if (array_key_exists('email', $data)) {
            array_unshift($rules['email'], 'required');

            if ($isActionUpdate === false) {
                array_push($rules['email'], 'unique:users');
                // array_push($rules['email'], Rule::unique('users'));
            } else {
                // https://devdocs.io/laravel~8/docs/8.x/validation#available-validation-rules
                // Forcing A Unique Rule To Ignore A Given ID
                array_push($rules['email'], Rule::unique('users')->ignore($this->user->id));
            }
        }
        if (array_key_exists('last_name', $data)) {
            array_unshift($rules['last_name'], 'required');
        }
        if (array_key_exists('phone', $data)) {
            array_unshift($rules['phone'], 'required');
        }
        if (array_key_exists('password', $data)) {
            array_unshift($rules['password'], 'required');
        }
        if (array_key_exists('password_confirmation', $data)) {
            array_push($rules['password'], 'confirmed');
        }
        if (array_key_exists('role', $data)) {
            array_unshift($rules['role'], 'required');
        }
        if (array_key_exists('status', $data)) {
            array_unshift($rules['status'], 'required');
        }

        // dd($rules);
        return $rules;
    }
}
