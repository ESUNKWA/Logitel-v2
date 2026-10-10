<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GalleryUpdateRequest extends FormRequest
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
            'photos' => ['required', 'array'],
            'photos.*.caption' => ['nullable', 'string', 'max:160'],
            'photos.*.category' => ['nullable', 'string', 'max:60'],
            'photos.*.position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'photos.*.is_published' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photos.*.caption' => 'légende',
            'photos.*.category' => 'catégorie',
            'photos.*.position' => 'ordre',
        ];
    }
}
