<?php

declare(strict_types=1);

namespace App\Imports;

use App\Http\Requests\Traits\UserRules;
use App\Models\User\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithValidation;

class UsersImport implements ToModel, WithHeadingRow, WithValidation //, WithUpserts
{
    use UserRules;

    /**
     * @return User|null
     */
    public function model(array $row)
    {
        return new User([
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'status' => $row['status'],
            'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret -> todo: change on production
        ]);
    }

    public function rules(): array
    {
        $rules = $this->commonUserRules();
        array_unshift($rules['email'], 'required');
        array_push($rules['email'], 'unique:users,email');
        array_unshift($rules['role'], 'required');
        array_unshift($rules['status'], 'required');

        return $rules;
    }

    // /**
    //  * if a user already exists with the same email, the row will be updated instead
    //  * @return string|array
    //  */
    // public function uniqueBy()
    // {
    //     return 'email';
    // }
}
