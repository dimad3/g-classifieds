<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Categories;

use App\Services\Adverts\CategoryAttributeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActionAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(CategoryAttributeService $categoryAttributeService): array
    {
        ($availableAttributes = $categoryAttributeService
            ->getAvailableAncestorsAndSelfAttributes($this->category));
        ($actions = $this->category->getAssignedActions($this->category->ancestorsAndMe()));

        $rules = [
            'settings' => ['array'],
            'checkbox.*.attributeId' => ['required', 'numeric', 'integer', Rule::in($availableAttributes->modelKeys())],
            'checkbox.*.actionId' => ['required', 'numeric', 'integer', Rule::in($actions->modelKeys())],
            'checkbox.*.settingName' => ['array', Rule::in(['required', 'column', 'excluded'])],
        ];

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // throw ValidationException::withMessages(['field_name' => 'This value is incorrect']);
        ($settings = $this->input('settings'));
        if (array_key_exists('settings', $this->input())) {
            $preparedForValidation = [];
            foreach ($settings as $attributeId => $actions) {
                foreach ($actions as $actionId => $values) {
                    $settingsNames = [];
                    foreach ($values as $key => $value) {
                        array_push($settingsNames, $key);
                    }
                    array_push($preparedForValidation, [
                        'attributeId' => $attributeId,
                        'actionId' => $actionId,
                        'settingName' => $settingsNames,
                    ]);
                }
            }
            ($this['checkbox'] = $preparedForValidation);
        }
    }
}
