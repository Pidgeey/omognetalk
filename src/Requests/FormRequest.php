<?php

namespace OmogenTalk\Requests;

use Illuminate\Foundation\Http\FormRequest as IlluminateFormRequest;

/**
 * Class FormRequest
 *
 * @package OmogenTalk\Requests
 */
class FormRequest extends IlluminateFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @param array $optionalAttributes
     *
     * @return array
     */
    public function getValidatedAttributes(array $optionalAttributes = [])
    {
        return array_merge($this->validated(), $optionalAttributes);
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [];
    }
}
