<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoomAmenity;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class RoomRequest extends FormRequest
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
        $room = $this->route('room');

        return [
            'room_type_id' => ['required', 'exists:room_types,id'],
            'number' => ['required', 'string', 'max:50', Rule::unique('rooms', 'number')->ignore($room)],
            'floor' => ['nullable', 'string', 'max:50'],
            'status' => ['required', new Enum(RoomStatus::class)],
            'notes' => ['nullable', 'string'],
            // Photos: real images only (content-checked; no SVG), 2 MB each — the browser downsizes
            // them before upload (admin.js). The total per room is checked in after().
            'images' => ['nullable', 'array', 'max:'.RoomImage::MAX_PER_ROOM],
            // `dimensions` makes PHP actually decode the header (getimagesize): a script renamed
            // .jpg fails it whatever its claimed type, and absurd sizes (decompression bombs) too.
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=50,min_height=50,max_width=8000,max_height=8000'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            // Public presentation (room page on the website)
            'is_published' => ['sometimes', 'boolean'],
            'public_name' => ['nullable', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'surface' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'beds' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:10'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amenities' => ['sometimes', 'array'],
            'amenities.*' => ['distinct', Rule::enum(RoomAmenity::class)],
            'features' => ['sometimes', 'array', 'max:20'],
            'features.*' => ['string', 'max:150'],
            'rules' => ['sometimes', 'array', 'max:20'],
            'rules.*' => ['string', 'max:150'],
            'cancellation_policy' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The presentation form sends "features" and "rules" as textareas (one item per line) and
     * a `presentation` marker so that unticking every amenity clears them.
     */
    protected function prepareForValidation(): void
    {
        foreach (['features', 'rules'] as $key) {
            // An empty textarea arrives as null (ConvertEmptyStringsToNull): it means "no item".
            if ($this->has($key) && $this->input($key) === null) {
                $this->merge([$key => []]);
            } elseif (is_string($this->input($key))) {
                $lines = array_map(fn ($line) => trim($line), preg_split('/\R/', $this->input($key)));
                $this->merge([$key => array_values(array_unique(array_filter($lines, fn ($line) => $line !== '')))]);
            }
        }

        if ($this->boolean('presentation') && ! $this->has('amenities')) {
            $this->merge(['amenities' => []]);
        }
    }

    public function touchesPresentation(): bool
    {
        return $this->boolean('presentation') || collect(Room::PRESENTATION_FIELDS)->contains(fn ($field) => $this->has($field));
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $room = $this->route('room');
                $kept = $room instanceof Room
                    ? $room->images()->whereNotIn('id', array_map('intval', (array) $this->input('remove_images', [])))->count()
                    : 0;
                $added = count(array_filter((array) $this->file('images', [])));

                if ($kept + $added > RoomImage::MAX_PER_ROOM) {
                    $validator->errors()->add('images', 'Une chambre peut avoir au maximum '.RoomImage::MAX_PER_ROOM." photos ({$kept} conservée(s) + {$added} ajoutée(s)).");
                }
            },
        ];
    }

    public function touchesImages(): bool
    {
        return filled($this->file('images')) || filled($this->input('remove_images'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'images' => 'photos', 'images.*' => 'photo',
            'public_name' => 'nom affiché', 'tagline' => 'phrase d\'accroche', 'surface' => 'surface',
            'beds' => 'nombre de lits', 'bathrooms' => 'nombre de salles de bain', 'amenities.*' => 'équipement',
            'features' => 'caractéristiques', 'features.*' => 'caractéristique', 'rules' => 'règles', 'rules.*' => 'règle',
            'cancellation_policy' => 'politique d\'annulation',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.image' => 'Chaque photo doit être une image (JPG, PNG ou WebP).',
            'images.*.mimes' => 'Chaque photo doit être au format JPG, PNG ou WebP.',
            'images.*.max' => 'Chaque photo doit faire au maximum 2 Mo.',
            'images.*.uploaded' => 'Une photo n\'a pas pu être envoyée (trop lourde ?). Réessayez.',
            'images.*.dimensions' => 'Chaque photo doit être une image valide (entre 50 et 8000 pixels de côté).',
        ];
    }
}
