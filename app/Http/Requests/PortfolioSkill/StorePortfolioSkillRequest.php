<?php

namespace App\Http\Requests\PortfolioSkill;

use Illuminate\Foundation\Http\FormRequest;

class StorePortfolioSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('portfolio')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'skill_name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'between:1,100'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
