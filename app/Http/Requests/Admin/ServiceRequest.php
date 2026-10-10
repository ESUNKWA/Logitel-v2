<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1500'],
            'icon' => ['nullable', Rule::in(array_keys(Service::ICONS))],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_published' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=200,max_width=8000,max_height=8000'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceAttributes(): array
    {
        return [
            'title' => $this->validated('title'),
            'category' => $this->validated('category'),
            'description' => $this->validated('description'),
            'icon' => $this->validated('icon'),
            'position' => (int) ($this->validated('position') ?? 0),
            'is_published' => $this->boolean('is_published'),
            'show_on_home' => $this->boolean('show_on_home'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => 'titre', 'category' => 'catégorie', 'icon' => 'icône', 'position' => 'ordre', 'photo' => 'photo'];
    }
}
