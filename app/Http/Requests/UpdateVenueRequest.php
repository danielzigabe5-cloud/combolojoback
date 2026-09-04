<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'description' => 'nullable|string|max:1000',
            'location' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'sub_city' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'capacity' => 'sometimes|integer|min:1',
            'price_per_hour' => 'sometimes|numeric|min:0',
            'sport_types' => 'nullable|json',
            'facilities' => 'nullable|json',
            'is_active' => 'nullable|boolean',
        ];
    }
}