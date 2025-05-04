<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\UserRules;
use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    use UserRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            $rules = $this->allUserRules($this->all());
        } else {
            $rules = $this->allUserRules($this->all(), true);
        }

        return $rules;
    }

    /**
     * Store new user in db or update the existing one.
     */
    public function storeOrUpdate(User $user): User|bool
    {
        if ($user->exists) {
            return $user->updateUser(
                $this->name,
                $this->email,
                $this->role
            );
        }

        return User::storeUser(
            $this->name,
            $this->email,
            $this->role
        );

        // tested on 03.08.2023 - ok
        // $user->name = $this->name;
        // $user->email = $this->email;
        // $user->role = $this->role;
        // if (!$user->exists) {
        //     $user->password = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm'; // secret
        //     $user->status = User::STATUS_ACTIVE;
        // }

        // return $user->save();
    }
}
