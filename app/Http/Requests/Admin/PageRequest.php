<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\PageRules;
use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
{
    use PageRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->allPageRules($this->all());
    }
}
