<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Models\Adverts\Advert\Advert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvertsIndexRequest extends FormRequest
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
            'expires_at' => ['nullable', 'date_format:d.m.Y'],
            'title' => ['nullable', 'string', 'max:16'],
            'region' => ['nullable', 'integer', 'exists:regions,id'],
            'category' => ['nullable', 'integer', 'exists:advert_categories,id'],
            'status' => [
                'nullable',
                'string',
                Rule::in(array_keys(Advert::statusesList())), // Dynamically validate against statusesList keys
            ],
        ];
    }
}
