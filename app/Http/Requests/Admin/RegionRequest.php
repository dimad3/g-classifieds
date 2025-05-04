<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\RegionRules;
use Illuminate\Foundation\Http\FormRequest;

class RegionRequest extends FormRequest
{
    use RegionRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->allRegionRules($this->all());
    }
}
