<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A list of ticked lines (« Supprimer la sélection », corbeille « Restaurer » / « Supprimer
 * définitivement »): integer ids only, at most 100 at a time. The permission is checked by the
 * route (can:affectations.delete); which ids exist / may be handled is checked by the action.
 */
class FileSelectionRequest extends FormRequest
{
    public const MAX = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Cochez au moins une ligne.',
            'ids.min' => 'Cochez au moins une ligne.',
            'ids.max' => 'Pas plus de '.self::MAX.' lignes à la fois.',
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->validated('ids'))));
    }
}
