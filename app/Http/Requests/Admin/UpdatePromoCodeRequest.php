<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromoCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'description' => ['nullable', 'string', 'max:255'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_usages' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['required', 'boolean'],
            'expires_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duration_days.required' => 'Durasi perpanjangan wajib diisi.',
            'duration_days.min' => 'Durasi perpanjangan minimal 1 hari.',
            'max_usages.required' => 'Maksimal penggunaan wajib diisi.',
            'expires_at.required' => 'Tanggal kadaluarsa wajib diisi.',
        ];
    }
}
