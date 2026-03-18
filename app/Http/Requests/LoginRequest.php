<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile'   => 'required|string|max:15',
            'password' => 'required|string|min:6',
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required'   => 'Mobile number is required.',
            'password.required' => 'Password is required.',
            'password.min'      => 'Password must be at least 6 characters.',
        ];
    }
}