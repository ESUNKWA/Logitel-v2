<?php

use App\Http\Controllers\Site\BookingRequestController;
use App\Http\Controllers\Site\EventController;
use App\Http\Controllers\Site\GalleryController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\RoomController;
use App\Http\Controllers\Site\ServiceController;
use App\Http\Controllers\Site\StayRequestController;
use App\Http\Middleware\RecordSiteVisit;
use Illuminate\Support\Facades\Route;

/*
 * The public WEBSITE (the management application lives in routes/admin.php).
 * Views in resources/views/site, assets in public/site, controllers in App\Http\Controllers\Site.
 * The site only reads what the application publishes; the guests' room request (/demande) is
 * the one place it writes.
 */
// Every public page is counted (anonymously) for « Site web → Statistiques ».
Route::domain(config('routing.site_domain'))->middleware(RecordSiteVisit::class)->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Rooms published from the application (presentation, photos, rates).
    Route::get('/chambres', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/chambres/{room}/{slug?}', [RoomController::class, 'show'])->whereNumber('room')->name('rooms.show');

    // Content managed in the application (« Site web »).
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/galerie', [GalleryController::class, 'index'])->name('gallery');
    Route::get('/evenements', [EventController::class, 'index'])->name('events.index');
    Route::view('/contact', 'site.pages.contact')->name('contact');

    // « Réserver »: online booking requests, handled by the réception in the application.
    Route::get('/reservation', [BookingRequestController::class, 'create'])->name('booking.create');
    Route::post('/reservation', [BookingRequestController::class, 'store'])->middleware('throttle:10,10')->name('booking.store');
    Route::get('/reservation/suivi/{bookingRequest}', [BookingRequestController::class, 'show'])->middleware('signed')->name('booking.show');

    // Guest's room request from their phone on arrival (QR code in the lobby only — never linked from the site): pick a free room,
    // send the request; the réception validates it and cashes in the application.
    Route::get('/demande', [StayRequestController::class, 'index'])->name('stay-request.index');
    Route::get('/demande/chambre/{room}', [StayRequestController::class, 'create'])->name('stay-request.create');
    Route::post('/demande/chambre/{room}', [StayRequestController::class, 'store'])->middleware('throttle:10,10')->name('stay-request.store');
    Route::get('/demande/suivi/{stayRequest}', [StayRequestController::class, 'show'])->middleware('signed')->name('stay-request.show');
});
