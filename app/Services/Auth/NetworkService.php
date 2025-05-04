<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Http\Requests\Traits\UserRules;
use App\Models\User\Network;
use App\Models\User\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Contracts\User as NetworkUser;

class NetworkService
{
    use UserRules;

    /**
     * Get the user authenticated by a network and if the user with this email does not exist add him to the db
     */
    public function auth(string $network, NetworkUser $networkUser): User
    {
        // input validation:
        $data = [
            'name' => $networkUser->getName(),
            'email' => $networkUser->getEmail(),
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'network' => $network,
            'identity' => $networkUser->getId(),
            // 'name' => null,
            // 'email' => 'dmitrijs63@inbox.lv',
            // 'role' => 'User',
            // 'status' => 'Waiting',
            // 'network' => 'Twitter',
            // 'identity' => 1
        ];

        // find the user authenticated by a network in the `user_networks` table
        if ($user = User::byNetwork($data['network'], $data['identity'])->first()) {
            return $user;
        }

        $rules = $this->allUserRules($data);
        $extraRules = [
            'network' => ['required', 'string', 'min:2', 'max:16', Rule::in(array_keys(Network::networksList()))],
            'identity' => ['required', 'string', 'max:255'],
        ];
        $allRules = array_merge($rules, $extraRules);

        $validator = Validator::make($data, $allRules);

        if ($validator->fails()) {
            $result = '';
            foreach ($validator->errors()->all() as $message) {
                $result .= '<li>' . $message . '</li>';
            }
            throw new \DomainException($result);
        }

        // Retrieve the validated input...
        $validatedData = $validator->validated();

        // create a new user who was authanticated by a network API
        $user = DB::transaction(function () use ($validatedData) {
            return User::registerByNetwork($validatedData);
        });

        // event(new Registered($user));   // todo: do we need this command?

        return $user;
    }
}
