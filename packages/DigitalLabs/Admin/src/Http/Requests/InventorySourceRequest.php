<?php

namespace DigitalLabs\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use DigitalLabs\Core\Rules\Address;
use DigitalLabs\Core\Rules\Code;
use DigitalLabs\Core\Rules\PhoneNumber;
use DigitalLabs\Core\Rules\PostCode;

class InventorySourceRequest extends FormRequest
{
    /**
     * Determine if the Configuraion is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'code' => ['required', 'unique:inventory_sources,code,'.$this->id, new Code],
            'name' => ['required'],
            'latitude' => ['numeric', 'between:-90,90'],
            'longitude' => ['numeric', 'between:-180,180'],
            'priority' => ['numeric'],
            'contact_name' => ['required'],
            'contact_email' => ['required', 'email'],
            'contact_number' => ['required', new PhoneNumber],
            'street' => ['required', new Address],
            'country' => ['required'],
            'state' => ['required'],
            'city' => ['required'],
            'postcode' => ['required', new PostCode],
        ];
    }
}
