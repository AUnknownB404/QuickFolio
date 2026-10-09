<?php

namespace App\Http\Requests\SocialLink;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocialLinkRequest extends FormRequest
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
            'platform' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
