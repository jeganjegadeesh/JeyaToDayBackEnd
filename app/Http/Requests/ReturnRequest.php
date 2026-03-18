<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'retailer_id'          => 'required|exists:users,id',
            'date'                 => 'required|date',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'retailer_id.required'        => 'Retailer is required.',
            'retailer_id.exists'          => 'Selected retailer does not exist.',
            'date.required'               => 'Date is required.',
            'date.date'                   => 'Invalid date format.',
            'items.required'              => 'At least one product is required.',
            'items.min'                   => 'At least one product is required.',
            'items.*.product_id.required' => 'Product is required.',
            'items.*.product_id.exists'   => 'Selected product does not exist.',
            'items.*.quantity.required'   => 'Quantity is required.',
            'items.*.quantity.min'        => 'Quantity must be at least 1.',
        ];
    }
}