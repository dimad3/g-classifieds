<?php

declare(strict_types=1);

namespace App\Http\Requests\Traits;

use App\Models\Adverts\Attribute;
use Illuminate\Validation\Rule;

trait AttributeRules
{
    protected function attributeRules(): array
    {
        return [
            'name' => 'required|string|min:2|max:64',
            'sort' => 'required|integer|min:0|max:255',
            'type' => ['required', 'string', 'max:16', Rule::in(array_keys(Attribute::typesList()))],
            'options' => 'nullable|string',
        ];
    }
}
