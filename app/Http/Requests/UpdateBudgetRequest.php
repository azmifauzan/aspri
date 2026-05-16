<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'category_id' => [
                'nullable',
                Rule::exists('finance_categories', 'id')->where('user_id', $user->id),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'alert_threshold_pct' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
