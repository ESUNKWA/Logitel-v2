<?php

namespace App\Http\Controllers\Site;

use App\Enums\PricingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\BookingRequestRequest;
use App\Models\BookingRequest;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * « Réserver » on the website: a guest asks for a booking (room type, séjour dates — the only
 * formula the site offers, PricingMode::WEBSITE). The
 * request lands in the application (Réservations → Demandes en ligne), where the réception calls
 * the guest back and creates the réservation. See App\Http\Controllers\Admin\BookingRequestController.
 */
class BookingRequestController extends Controller
{
    public function create(Request $request): View
    {
        $types = BookingRequest::offeredRoomTypes();

        return view('site.pages.reservation', [
            'types' => $types,
            'selectedType' => old('room_type_id', $types->firstWhere('id', $request->integer('type'))?->id ?? $types->first()?->id),
            // The séjour only: the website never offers the other formulas (PricingMode::WEBSITE).
            'rates' => $types->mapWithKeys(fn (RoomType $type) => [$type->id => [PricingMode::WEBSITE->value => (float) PricingMode::WEBSITE->rateFor($type)]]),
        ]);
    }

    public function store(BookingRequestRequest $request): RedirectResponse
    {
        $mode = $request->pricingMode();
        [$start, , $duration] = $request->period();

        $booking = new BookingRequest([
            'room_type_id' => $request->roomType()->id,
            'pricing_mode' => $mode,
            'check_in' => $start,
            'duration' => $duration,
            'adults' => $request->validated('adults'),
            'children' => $request->validated('children'),
            'unit_rate' => $mode->rateFor($request->roomType()), // indicative, never from input
            'first_name' => (string) $request->validated('first_name'),
            'last_name' => (string) $request->validated('last_name'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'message' => $request->validated('message'),
        ]);
        $booking->ip_address = $request->ip();
        $booking->save();

        return redirect(URL::signedRoute('booking.show', $booking));
    }

    /**
     * Summary page (signed link: only the guest who sent the request can open it).
     */
    public function show(BookingRequest $bookingRequest): View
    {
        return view('site.pages.reservation-envoyee', ['booking' => $bookingRequest->load('roomType')]);
    }
}
