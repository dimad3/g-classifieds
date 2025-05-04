<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsersIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:16'],
            'last_name' => ['nullable', 'string', 'max:16'],
            'email' => ['nullable', 'string', 'max:16'],
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(User::statusesList())),
            ],
            'role' => [
                'nullable',
                'string',
                Rule::in(array_keys(User::rolesList())),
            ],
        ];
    }
}
