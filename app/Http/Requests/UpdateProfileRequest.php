<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'กรุณากรอกชื่อ - นามสกุล',
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique' => 'อีเมลนี้ถูกใช้งานในระบบแล้ว',
            'avatar.image' => 'ไฟล์รูปโปรไฟล์ต้องเป็นรูปภาพเท่านั้น',
            'avatar.mimes' => 'รองรับเฉพาะไฟล์รูปภาพนามสกุล JPG, PNG หรือ WEBP',
            'avatar.max' => 'ขนาดรูปโปรไฟล์ต้องไม่เกิน 2 MB',
        ];
    }
}
