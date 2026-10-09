<?php

namespace App\Http\Requests\ProjectSkill;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachProjectSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('portfolio')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $portfolio = $this->route('portfolio');
        $project = $this->route('project');

        return [
            'skill_id' => [
                'required',
                'integer',
                Rule::exists('portfolio_skill', 'skill_id')
                    ->where('portfolio_id', $portfolio->id),
                Rule::unique('project_skill', 'skill_id')
                    ->where('project_id', $project->id),
            ],
        ];
    }
}
