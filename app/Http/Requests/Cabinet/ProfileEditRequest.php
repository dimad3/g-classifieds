<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\Http\Requests\Traits\UserRules;
use Illuminate\Foundation\Http\FormRequest;

class ProfileEditRequest extends FormRequest
{
    use UserRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = $this->allUserRules($this->all(), true);

        // if you need change $rules[] here
        return $rules;
    }
}
