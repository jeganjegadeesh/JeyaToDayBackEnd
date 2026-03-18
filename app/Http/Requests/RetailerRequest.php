<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetailerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $retailerId = $this->route('retailer')?->id;

        return [
            'name'       => 'required|string|max:255',
            'mobile'     => 'required|string|max:15|unique:users,mobile,' . $retailerId,
            'password'   => $this->isMethod('POST') ? 'required|string|min:6' : 'nullable|string|min:6',
            'commission' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Retailer name is required.',
            'mobile.required'   => 'Mobile number is required.',
            'mobile.unique'     => 'This mobile number is already registered.',
            'password.required' => 'Password is required.',
            'password.min'      => 'Password must be at least 6 characters.',
            'commission.min'    => 'Commission cannot be negative.',
            'commission.max'    => 'Commission cannot exceed 100%.',
        ];
    }
}