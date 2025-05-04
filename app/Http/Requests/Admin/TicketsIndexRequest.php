<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Ticket\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketsIndexRequest extends FormRequest
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
            'created_at' => ['nullable', 'date_format:d.m.Y'],
            'updated_at' => ['nullable', 'date_format:d.m.Y'],
            'subject' => ['nullable', 'string', 'max:16'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Status::statusesList())),
            ],
        ];
    }
}
