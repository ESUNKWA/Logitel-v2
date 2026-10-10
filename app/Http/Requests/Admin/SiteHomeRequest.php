<?php

namespace App\Http\Requests\Admin;

use App\Models\SiteContent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * « Site web → Accueil »: texts (all optional — empty = not shown) and photos.
 */
class SiteHomeRequest extends FormRequest
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
        $rules = [];
        foreach (SiteContent::textKeys() as $key) {
            $long = str_ends_with($key, '_text') || $key === 'about_highlights';
            $rules[$key] = ['nullable', 'string', $long ? 'max:2000' : 'max:160'];
        }
        foreach (SiteContent::PHOTOS as $key) {
            // Real raster images only (no SVG), large enough for a web page.
            $rules[$key] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=400,min_height=300,max_width=8000,max_height=8000'];
            $rules['remove_'.$key] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'hero_title' => 'titre de la bannière', 'hero_text' => 'texte de la bannière',
            'hero_photo_1' => 'photo de gauche', 'hero_photo_2' => 'photo de droite', 'hero_background' => 'image de fond',
            'about_title' => 'titre « À propos »', 'about_text' => 'texte « À propos »', 'about_photo_1' => 'photo principale', 'about_photo_2' => 'seconde photo',
        ];
    }
}
