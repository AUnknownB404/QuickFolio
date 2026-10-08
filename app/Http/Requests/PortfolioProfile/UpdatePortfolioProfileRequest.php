<?php

namespace App\Http\Requests\PortfolioProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortfolioProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('portfolio')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'profile_image' => ['nullable', 'string', 'max:255'],
            'resume' => ['nullable', 'string', 'max:255'],
        ];
    }
}
