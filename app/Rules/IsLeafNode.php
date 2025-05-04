<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class IsLeafNode implements Rule
{
    protected $modelClass;

    /**
     * Create a new rule instance.
     *
     * @param  string  $modelClass  The class name of the model to be validated
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $model = $this->modelClass::find($value);

        // Check if the model exists and has no child nodes
        return $model && ! $model->children()->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute is not the last in the hierarchy.';
    }
}
