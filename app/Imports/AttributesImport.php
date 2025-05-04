<?php

declare(strict_types=1);

namespace App\Imports;

use App\Http\Requests\Traits\AttributeRules;
use App\Models\Adverts\Attribute;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AttributesImport implements ToModel, WithHeadingRow, WithValidation
{
    use AttributeRules;

    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $category = request()->route()->parameter('category');

        return new Attribute([
            'category_id' => $category->id,
            'name' => $row['name'],
            'sort' => $row['sort'],
            'type' => $row['type'],
            'required' => $row['required'],
            'options' => explode('|', $row['options']),
        ]);
    }

    public function rules(): array
    {
        return $this->attributeRules();
    }
}
