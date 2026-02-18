<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:promo_codes,code', 'regex:/^[A-Za-z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_usages' => ['required', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['required', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode promo wajib diisi.',
            'code.unique' => 'Kode promo sudah digunakan.',
            'code.regex' => 'Kode promo hanya boleh mengandung huruf, angka, underscore, dan dash.',
            'duration_days.required' => 'Durasi perpanjangan wajib diisi.',
            'duration_days.min' => 'Durasi perpanjangan minimal 1 hari.',
            'max_usages.required' => 'Maksimal penggunaan wajib diisi.',
            'max_usages.min' => 'Maksimal penggunaan minimal 1.',
            'expires_at.required' => 'Tanggal kadaluarsa wajib diisi.',
            'expires_at.after' => 'Tanggal kadaluarsa harus setelah hari ini.',
        ];
    }
}
