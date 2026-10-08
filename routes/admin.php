<?php

use App\Http\Controllers\Admin\AffectationController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CheckoutAlertController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FrontDeskController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\HotelInfoController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RequestQrCodeController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoomAssignmentController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StayRequestController;
use App\Http\Controllers\Admin\UiModeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\EnsureStaffAccountIsUsable;
use App\Http\Middleware\NormalizeMoneyInputs;
use Illuminate\Support\Facades\Route;

/*
 * Every route below the auth group must carry a `can:<permission>` middleware (see
 * App\Enums\Permission), except the few listed in PermissionTest::UNGUARDED_ROUTES.
 * That test fails if a new route is added without one.
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');

    Route::middleware(['auth', EnsureStaffAccountIsUsable::class, NormalizeMoneyInputs::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Home: front-desk tiles in "Mode simple", figures dashboard in "Mode complet" (per account).
        Route::get('/', [DashboardController::class, 'home'])->name('dashboard');
        Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('stats');
        Route::post('/mode', [UiModeController::class, 'update'])->name('ui-mode.update');

        // Mode simple task lists (read only; actions post to the existing routes).
        Route::get('/departs', [FrontDeskController::class, 'departures'])->middleware('can:affectations.view')->name('front-desk.departures');
        Route::get('/arrivees', [FrontDeskController::class, 'arrivals'])->middleware('can:reservations.view')->name('front-desk.arrivals');
        Route::get('/a-encaisser', [FrontDeskController::class, 'collect'])->middleware('can:invoices.view')->name('front-desk.collect');
        Route::get('/recherche', [FrontDeskController::class, 'search'])->middleware('can:guests.view')->name('front-desk.search');

        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password.update');

        Route::resource('room-types', RoomTypeController::class)->except(['show'])->middleware('can:rooms.manage');

        Route::get('/rooms/board', [RoomController::class, 'board'])->middleware('can:rooms.view')->name('rooms.board');
        Route::post('/rooms/{room}/ready', [RoomController::class, 'markReady'])->middleware('can:rooms.edit')->name('rooms.ready');
        Route::get('/rooms/available', [RoomController::class, 'available'])->middleware('can:reservations.manage')->name('rooms.available');
        Route::post('/rooms/{room}/assign', [RoomAssignmentController::class, 'store'])->middleware('can:affectations.manage')->name('rooms.assign');
        Route::resource('rooms', RoomController::class)->except(['show'])
            ->middlewareFor('index', 'can:rooms.view')
            ->middlewareFor(['edit', 'update'], 'can:rooms.edit')
            ->middlewareFor(['create', 'store', 'destroy'], 'can:rooms.manage');

        Route::get('/guests/search', [GuestController::class, 'search'])->middleware('can:guests.view')->name('guests.search');
        Route::resource('guests', GuestController::class)->except(['show'])
            ->middlewareFor('index', 'can:guests.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:guests.manage')
            ->middlewareFor('destroy', 'can:guests.delete');

        // Réservations (booked ahead): "check-in" = the guest arrives → the file becomes a stay.
        Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->middleware('can:reservations.manage')->name('reservations.check-in');
        Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('can:reservations.cancel')->name('reservations.cancel');
        Route::resource('reservations', ReservationController::class)->except(['show', 'destroy'])
            ->middlewareFor('index', 'can:reservations.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:reservations.manage');

        // Affectations (stays in progress / past): created from the room board (rooms.assign).
        Route::get('/affectations', [AffectationController::class, 'index'])->middleware('can:affectations.view')->name('affectations.index');
        Route::get('/affectations/{affectation}', [AffectationController::class, 'show'])->middleware('can:affectations.view')->name('affectations.show');
        Route::put('/affectations/{affectation}', [AffectationController::class, 'update'])->middleware('can:affectations.manage')->name('affectations.update');
        Route::post('/affectations/{affectation}/check-out', [AffectationController::class, 'checkOut'])->middleware('can:affectations.manage')->name('affectations.check-out');
        Route::post('/affectations/{affectation}/cancel', [AffectationController::class, 'cancel'])->middleware('can:affectations.cancel')->name('affectations.cancel');

        // Demandes clients (sent from the guest's phone, public page /demande): validate (→ affectation, cashed) or refuse.
        Route::get('/demandes', [StayRequestController::class, 'index'])->middleware('can:affectations.view')->name('stay-requests.index');
        Route::get('/demandes/qr-code', [RequestQrCodeController::class, 'show'])->middleware('can:affectations.view')->name('stay-requests.qr');
        Route::get('/demandes/qr-code.svg', [RequestQrCodeController::class, 'download'])->middleware('can:affectations.view')->name('stay-requests.qr.download');
        Route::get('/demandes/flux', [StayRequestController::class, 'feed'])->middleware('can:affectations.manage')->name('stay-requests.feed');
        Route::get('/demandes/{stayRequest}', [StayRequestController::class, 'show'])->middleware('can:affectations.view')->name('stay-requests.show');
        Route::post('/demandes/{stayRequest}/valider', [StayRequestController::class, 'approve'])->middleware('can:affectations.manage')->name('stay-requests.approve');
        Route::post('/demandes/{stayRequest}/refuser', [StayRequestController::class, 'refuse'])->middleware('can:affectations.manage')->name('stay-requests.refuse');

        // Customer receipt (thermal ticket) for an affectation or a réservation.
        Route::get('/recus/{reservation}', [ReceiptController::class, 'show'])->middleware('can:invoices.view')->name('receipts.show');

        Route::resource('invoices', InvoiceController::class)->only(['index', 'show'])->middleware('can:invoices.view');
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->middleware('can:payments.create')->name('invoices.payments.store');
        Route::delete('/invoices/{invoice}/payments/{payment}', [PaymentController::class, 'destroy'])->middleware('can:payments.delete')->name('invoices.payments.destroy');

        Route::get('/alertes/departs', [CheckoutAlertController::class, 'index'])->middleware('can:alerts.checkout')->name('alerts.checkouts');
        Route::post('/alertes/departs/{reservation}/vu', [CheckoutAlertController::class, 'acknowledge'])->middleware('can:alerts.checkout')->name('alerts.checkouts.acknowledge');

        Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:users.manage');
        Route::resource('roles', RoleController::class)->except(['show'])->middleware('can:roles.manage');

        Route::get('/hotel', [HotelInfoController::class, 'edit'])->middleware('can:settings.manage')->name('hotel.edit');
        Route::put('/hotel', [HotelInfoController::class, 'update'])->middleware('can:settings.manage')->name('hotel.update');

        Route::get('/parametres', [SettingsController::class, 'edit'])->middleware('can:settings.manage')->name('settings.edit');
        Route::put('/parametres', [SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');
    });
});
