<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'description' => 'nullable|string|max:1000',
            'location' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'sub_city' => 'nullable|string|max:255',
            // ❌ Latitude/Longitude ተወግደዋል
            'capacity' => 'required|integer|min:1',
            'price_per_hour' => 'required|numeric|min:0',
            'sport_types' => 'nullable|string',
            'facilities' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Venue name is required',
            'name.min' => 'Venue name must be at least 3 characters',
            'image.required' => 'Venue image is required',
            'image.image' => 'File must be an image',
            'image.mimes' => 'Image must be JPEG, PNG, or JPG',
            'image.max' => 'Image size must be less than 2MB',
            'location.required' => 'Location is required',
            'city.required' => 'City is required',
            'capacity.required' => 'Capacity is required',
            'capacity.min' => 'Capacity must be at least 1',
            'price_per_hour.required' => 'Price per hour is required',
            'price_per_hour.min' => 'Price must be at least 0',
        ];
    }
}