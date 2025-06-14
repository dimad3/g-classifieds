<?php

declare(strict_types=1);

namespace App\Http\Requests\Adverts\Dialog;

use Illuminate\Foundation\Http\FormRequest;

class MessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string',
        ];
    }
}
