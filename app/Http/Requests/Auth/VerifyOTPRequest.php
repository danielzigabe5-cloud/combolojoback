<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOTPRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6'],
            'country_code' => ['required', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required' => 'OTP code is required',
            'otp.size' => 'OTP must be exactly 6 digits',
            'otp.string' => 'Invalid OTP format',
        ];
    }
}