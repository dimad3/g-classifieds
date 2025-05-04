<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

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
            'type' => ['nullable', 'in:published,unpublished'],
            'period' => ['nullable', 'in:today,yesterday,last_three_days,last_five_days,this_week,previous_week,this_month,previous_month,other'],
            'id' => ['nullable', 'integer'],
            'published' => ['nullable', 'date_format:d.m.Y'],
            'title' => ['nullable', 'string', 'max:16'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
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
