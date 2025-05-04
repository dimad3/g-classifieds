<?php

declare(strict_types=1);

namespace App\Imports;

use App\Http\Requests\Traits\CategoryRules;
use App\Models\Adverts\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CategoriesImport implements ToModel, WithHeadingRow, WithValidation
{
    use CategoryRules;

    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // dump($row);
        ($fileColumns = array_keys($row));
        ($allowedColumns = ['name', 'sort']);
        // dd(empty(array_diff($fileColumns, $allowedColumns)));
        if (empty(array_diff($fileColumns, $allowedColumns))) {
            $parentCategory = request()->route()->parameter('parentCategory');

            return new Category([
                'name' => $row['name'],
                'slug' => Str::slug($row['name'], '-'),
                'has_price' => 0,
                'sort' => $row['sort'],
                'parent_id' => $parentCategory->id,
            ]);
        }
        throw ValidationException::withMessages(["Invalid headers or missing column. File must contain only 'name' and 'sort' headers in the first row!"]);
    }

    public function rules(): array
    {
        $rules = $this->commonCategoryRules();
        // array_push($rules['name'], 'distinct');  // unique rule throw the same validation error
        array_push($rules['name'], $this->ruleUniqueOnPost(request()->route()->parameter('parentCategory')));

        return $rules;
    }
}
