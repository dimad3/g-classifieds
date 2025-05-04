<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Categories;

use App\Http\Requests\Traits\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    use CategoryRules;

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
            $rules = $this->allCategoryRules($this->all(), false);
        } else {
            $rules = $this->allCategoryRules($this->all(), true);
        }

        return $rules;
    }
}
