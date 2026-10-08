<?php

namespace App\Http\Requests\Admin;

use App\Models\Room;
use App\Models\StayRequest;

/**
 * Validating a guest's request ("demande") into an affectation: same rules as an assignment from
 * the room board (mode, duration, rate, payment in full at check-in, cash received…), except the
 * guest comes from the request — matched to an existing client by phone unless `new_guest` is
 * ticked — and the room is the request's.
 */
class StayRequestApprovalRequest extends RoomAssignmentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...collect(parent::rules())->except(['guest_id', 'new_first_name', 'new_last_name', 'new_phone'])->all(),
            'new_guest' => ['nullable', 'boolean'],
        ];
    }

    public function targetRoom(): ?Room
    {
        $stayRequest = $this->route('stayRequest');

        return $stayRequest instanceof StayRequest ? $stayRequest->room : null;
    }
}
