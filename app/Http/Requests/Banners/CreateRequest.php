<?php

declare(strict_types=1);

namespace App\Http\Requests\Banners;

use App\Models\Banner\Banner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        [$width, $height] = [0, 0];
        if ($format = $this->input('format')) {
            [$width, $height] = explode('x', $format);
        }

        return [
            'name' => 'required|string',
            'limit' => 'required|integer',
            'url' => 'required|url',
            'format' => ['required', 'string', Rule::in(Banner::formatsList())],
            'file' => 'required|image|mimes:jpg,jpeg,png,gif|dimensions:width=' . $width . ',height=' . $height,
        ];
    }
}
