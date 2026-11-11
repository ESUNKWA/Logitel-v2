<?php

namespace App\Http\Requests\Admin;

/**
 * Deleting affectations (Gérant only, route can:affectations.delete): the ticked lines and an
 * optional reason kept in the deletion log.
 */
class AffectationDeletionRequest extends FileSelectionRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'motif'];
    }

    public function reason(): ?string
    {
        $reason = trim((string) $this->validated('reason'));

        return $reason === '' ? null : $reason;
    }
}
