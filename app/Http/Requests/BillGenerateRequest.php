<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'retailer_id' => 'required|exists:users,id',
            'date'        => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'retailer_id.required' => 'Retailer is required.',
            'retailer_id.exists'   => 'Selected retailer does not exist.',
            'date.required'        => 'Date is required.',
            'date.date'            => 'Invalid date format.',
        ];
    }
}