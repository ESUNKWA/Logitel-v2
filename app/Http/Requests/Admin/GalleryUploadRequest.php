<?php

namespace App\Http\Requests\Admin;

use App\Models\GalleryPhoto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GalleryUploadRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1', 'max:'.GalleryPhoto::MAX_PER_UPLOAD],
            // Real, decodable pictures only (no SVG, no renamed script, no decompression bomb).
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=200,max_width=8000,max_height=8000'],
            'category' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $room = GalleryPhoto::MAX_TOTAL - GalleryPhoto::count();
            if (count((array) $this->file('images', [])) > $room) {
                $validator->errors()->add('images', $room > 0
                    ? 'La galerie est limitée à '.GalleryPhoto::MAX_TOTAL." photos : vous pouvez encore en ajouter {$room}."
                    : 'La galerie est complète ('.GalleryPhoto::MAX_TOTAL.' photos) : supprimez-en avant d\'en ajouter.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Choisissez au moins une photo.',
            'images.max' => 'Au plus '.GalleryPhoto::MAX_PER_UPLOAD.' photos par envoi.',
            'images.*.image' => 'Chaque fichier doit être une photo (JPG, PNG ou WebP).',
            'images.*.mimes' => 'Chaque fichier doit être une photo (JPG, PNG ou WebP).',
            'images.*.max' => 'Chaque photo doit faire au plus 2 Mo.',
            'images.*.dimensions' => 'Chaque photo doit mesurer au moins 300 × 200 pixels.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['category' => 'catégorie'];
    }
}
