<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Categories;

use App\Http\Requests\Traits\AttributeRules;
use App\Models\Adverts\Attribute;
use App\Models\Adverts\Category;
use Illuminate\Foundation\Http\FormRequest;

class AttributeRequest extends FormRequest
{
    use AttributeRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->attributeRules();
    }

    public function storeOrUpdate(Category $category, Attribute $attribute): bool
    {
        $attribute->category_id = $category->id;
        $attribute->name = $this->name;
        $attribute->sort = $this->sort;
        $attribute->type = $this->type;
        $attribute->options = array_map('trim', preg_split('#[\r\n]+#', (string) $this->options));

        return $attribute->save();
    }
}
