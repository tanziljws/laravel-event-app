<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'code' => 'required|string|size:6|regex:/^[0-9]{6}$/', // 6 digit angka
        ];
    }
}
