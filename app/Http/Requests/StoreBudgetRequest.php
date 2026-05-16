<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
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
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'numeric', 'min:0'],
            'alert_threshold_pct' => ['nullable', 'integer', 'min:1', 'max:100'],
            'channel' => ['nullable', Rule::in(['app', 'telegram', 'both'])],
        ];
    }
}
