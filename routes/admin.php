<?php

/*
 * The management APPLICATION (the public website lives in routes/site.php).
 * Views in resources/views/admin, assets in public/admin, controllers in App\Http\Controllers\Admin.
 * Under /admin on a shared address, or at the root of its own sub-domain (config/routing.php).
 */

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AffectationController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingRequestController;
use App\Http\Controllers\Admin\CatalogItemController;
use App\Http\Controllers\Admin\CheckoutAlertController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DutyController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FrontDeskController;
use App\Http\Controllers\Admin\GuestController;
use App\Http\Controllers\Admin\HotelInfoController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LiveFiguresController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PerkController;
use App\Http\Controllers\Admin\PilotageController;
use App\Http\Controllers\Admin\PlanningController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PwaController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RequestQrCodeController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoomAssignmentController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomImportController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\Site\EventController;
use App\Http\Controllers\Admin\Site\GalleryController;
use App\Http\Controllers\Admin\Site\HomeContentController;
use App\Http\Controllers\Admin\Site\ServiceController;
use App\Http\Controllers\Admin\Site\SiteStatsController;
use App\Http\Controllers\Admin\SpecialRateController;
use App\Http\Controllers\Admin\StayRequestController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Admin\UiModeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VenueBookingController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Middleware\EnsureDutyTaken;
use App\Http\Middleware\EnsureServiceOffered;
use App\Http\Middleware\EnsureStaffAccountIsUsable;
use App\Http\Middleware\NormalizeMoneyInputs;
use Illuminate\Support\Facades\Route;

/*
 * Every route below the auth group must carry a `can:<permission>` middleware (see
 * App\Enums\Permission), except the few listed in PermissionTest::UNGUARDED_ROUTES.
 * That test fails if a new route is added without one.
 */
Route::domain(config('routing.admin_domain'))->prefix(config('routing.admin_prefix'))->name('admin.')->group(function () {
    // Installable application (PWA), public because the login page needs it; scoped to this path.
    Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
    Route::get('/hors-ligne', [PwaController::class, 'offline'])->name('pwa.offline');

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');

    // Presentation demo: standalone page with fictitious data, no database access; public so the
    // link can be shared with prospects.
    Route::view('/demo', 'admin.demo')->name('demo');

    Route::middleware(['auth', EnsureStaffAccountIsUsable::class, EnsureDutyTaken::class, NormalizeMoneyInputs::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Home: front-desk tiles in "Mode simple", figures dashboard in "Mode complet" (per account).
        Route::get('/', [DashboardController::class, 'home'])->name('dashboard');
        Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('stats');
        // Live refresh of the home / dashboard money (each block gated inside, like those pages).
        Route::get('/chiffres-en-direct', [LiveFiguresController::class, 'show'])->name('live-figures');
        Route::post('/mode', [UiModeController::class, 'update'])->name('ui-mode.update');

        // Mode simple task lists (read only; actions post to the existing routes).
        Route::get('/departs', [FrontDeskController::class, 'departures'])->middleware('can:affectations.view')->name('front-desk.departures');
        Route::get('/arrivees', [FrontDeskController::class, 'arrivals'])->middleware('can:reservations.view')->name('front-desk.arrivals');
        Route::get('/a-encaisser', [FrontDeskController::class, 'collect'])->middleware('can:invoices.view')->name('front-desk.collect');
        // « Recettes »: payments received over a period (the manager sees everyone, others their own).
        Route::get('/recettes', [RevenueController::class, 'index'])->middleware('can:payments.create')->name('revenue.index');
        Route::get('/recettes/export', [RevenueController::class, 'export'])->middleware('can:payments.create')->name('revenue.export');
        // « Pilotage »: turnover (cash received) globally and per service, over a period.
        Route::get('/pilotage', [PilotageController::class, 'index'])->middleware('can:dashboard.revenue')->name('pilotage.index');
        Route::get('/pilotage/export', [PilotageController::class, 'export'])->middleware('can:dashboard.revenue')->name('pilotage.export');
        // Sales of the other services (restaurant, beach & pool), whitelisted in the URL.
        Route::prefix('/ventes/{department}')->where(['department' => 'restauration|bar|plage-piscine'])->middleware(EnsureServiceOffered::class)->group(function () {
            Route::get('/', [SaleController::class, 'index'])->middleware('can:sales.create')->name('sales.index');
            Route::get('/nouvelle', [SaleController::class, 'create'])->middleware('can:sales.create')->name('sales.create');
            Route::post('/', [SaleController::class, 'store'])->middleware('can:sales.create')->name('sales.store');
            Route::get('/{sale}', [SaleController::class, 'show'])->middleware('can:sales.create')->whereNumber('sale')->name('sales.show');
            Route::get('/{sale}/recu', [SaleController::class, 'receipt'])->middleware('can:sales.create')->whereNumber('sale')->name('sales.receipt');
            Route::post('/{sale}/annuler', [SaleController::class, 'cancel'])->middleware('can:sales.cancel')->whereNumber('sale')->name('sales.cancel');
        });
        // Meetings & events: room bookings (events.manage), cancelling (sales.cancel), venues (catalog.manage).
        Route::get('/evenements', [VenueBookingController::class, 'index'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->name('events.index');
        Route::get('/evenements/planning', [VenueBookingController::class, 'planning'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->name('events.planning');
        Route::get('/evenements/nouvelle', [VenueBookingController::class, 'create'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->name('events.create');
        Route::post('/evenements', [VenueBookingController::class, 'store'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->name('events.store');
        Route::get('/evenements/{booking}', [VenueBookingController::class, 'show'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->whereNumber('booking')->name('events.show');
        Route::get('/evenements/{booking}/modifier', [VenueBookingController::class, 'edit'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->whereNumber('booking')->name('events.edit');
        Route::put('/evenements/{booking}', [VenueBookingController::class, 'update'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->whereNumber('booking')->name('events.update');
        Route::post('/evenements/{booking}/confirmer', [VenueBookingController::class, 'confirm'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->whereNumber('booking')->name('events.confirm');
        Route::post('/evenements/{booking}/annuler', [VenueBookingController::class, 'cancel'])->middleware(['can:sales.cancel', EnsureServiceOffered::class.':evenements'])->whereNumber('booking')->name('events.cancel');
        Route::get('/evenements/{booking}/document', [VenueBookingController::class, 'document'])->middleware(['can:events.manage', EnsureServiceOffered::class.':evenements'])->whereNumber('booking')->name('events.document');
        Route::get('/salles', [VenueController::class, 'index'])->middleware(['can:catalog.manage', EnsureServiceOffered::class.':evenements'])->name('venues.index');
        Route::post('/salles', [VenueController::class, 'store'])->middleware(['can:catalog.manage', EnsureServiceOffered::class.':evenements'])->name('venues.store');
        Route::put('/salles/{venue}', [VenueController::class, 'update'])->middleware(['can:catalog.manage', EnsureServiceOffered::class.':evenements'])->whereNumber('venue')->name('venues.update');
        Route::delete('/salles/{venue}', [VenueController::class, 'destroy'])->middleware(['can:catalog.manage', EnsureServiceOffered::class.':evenements'])->whereNumber('venue')->name('venues.destroy');
        // « Catalogue des prestations » (Gérant).
        Route::get('/catalogue', [CatalogItemController::class, 'index'])->middleware('can:catalog.manage')->name('catalog.index');
        Route::post('/catalogue', [CatalogItemController::class, 'store'])->middleware('can:catalog.manage')->name('catalog.store');
        Route::put('/catalogue/{item}', [CatalogItemController::class, 'update'])->middleware('can:catalog.manage')->whereNumber('item')->name('catalog.update');
        Route::delete('/catalogue/{item}', [CatalogItemController::class, 'destroy'])->middleware('can:catalog.manage')->whereNumber('item')->name('catalog.destroy');
        Route::get('/recherche', [FrontDeskController::class, 'search'])->middleware('can:guests.view')->name('front-desk.search');

        // « Prise de service »: the receptionist of the business day (mandatory), every sale of the day is hers.
        Route::get('/prise-de-service', [DutyController::class, 'show'])->middleware('can:duty.take')->name('duty.show');
        Route::post('/prise-de-service', [DutyController::class, 'take'])->middleware('can:duty.take')->name('duty.take');
        // « Non, je viens en renfort »: works without relieving the colleague on duty (her till).
        Route::post('/prise-de-service/renfort', [DutyController::class, 'decline'])->middleware('can:duty.take')->name('duty.decline');
        Route::post('/prise-de-service/terminer', [DutyController::class, 'end'])->middleware('can:duty.take')->name('duty.end');
        Route::get('/prises-de-service', [DutyController::class, 'index'])->middleware('can:duty.manage')->name('duty.index');
        Route::post('/prises-de-service', [DutyController::class, 'assign'])->middleware('can:duty.manage')->name('duty.assign');
        Route::post('/prises-de-service/{shift}/corriger', [DutyController::class, 'reassign'])->middleware('can:duty.manage')->whereNumber('shift')->name('duty.reassign');
        Route::post('/prises-de-service/{shift}/clore', [DutyController::class, 'close'])->middleware('can:duty.manage')->whereNumber('shift')->name('duty.close');

        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profil', [ProfileController::class, 'update'])->middleware('throttle:6,1')->name('profile.update');
        Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password.update');

        Route::resource('room-types', RoomTypeController::class)->except(['show'])->middleware('can:rooms.manage');
        // Forfaits for affectations (« 2 heures = 5 000 FCFA »), applied automatically.
        Route::resource('tarifs-speciaux', SpecialRateController::class)->except(['show'])
            ->parameters(['tarifs-speciaux' => 'specialRate'])->names('special-rates')->middleware('can:rooms.manage');
        // Included perks (« Accès piscine gratuit » for the suites in séjour): receipt, desk, website.
        Route::resource('avantages', PerkController::class)->except(['show'])
            ->parameters(['avantages' => 'perk'])->names('perks')->middleware('can:rooms.manage');

        // Rooms CSV import: analyse (nothing saved) then confirm; existing rooms are never modified.
        Route::get('/rooms/import', [RoomImportController::class, 'create'])->middleware('can:rooms.manage')->name('rooms.import');
        Route::get('/rooms/import/modele', [RoomImportController::class, 'template'])->middleware('can:rooms.manage')->name('rooms.import.template');
        Route::post('/rooms/import', [RoomImportController::class, 'preview'])->middleware(['can:rooms.manage', 'throttle:20,1'])->name('rooms.import.preview');
        Route::post('/rooms/import/confirmer', [RoomImportController::class, 'store'])->middleware('can:rooms.manage')->name('rooms.import.store');
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
            ->middlewareFor('index', 'can:guests.list')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:guests.manage')
            ->middlewareFor('destroy', 'can:guests.delete');

        // Réservations (booked ahead): "check-in" = the guest arrives → the file becomes a stay.
        // « Arrivée du client » page: what is left to pay, optionally cashed with the check-in.
        Route::get('/reservations/{reservation}/arrivee', [ReservationController::class, 'arrival'])->middleware('can:reservations.manage')->name('reservations.arrival');
        Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn'])->middleware('can:reservations.manage')->name('reservations.check-in');
        Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('can:reservations.cancel')->name('reservations.cancel');
        Route::resource('reservations', ReservationController::class)->except(['show', 'destroy'])
            ->middlewareFor('index', 'can:reservations.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:reservations.manage');

        // Affectations (stays in progress / past): created from the room board (rooms.assign).
        Route::get('/affectations', [AffectationController::class, 'index'])->middleware('can:affectations.view')->name('affectations.index');
        // Gérant only (system role): delete affectations typed by mistake → « Corbeille » (restore / erase for good).
        Route::delete('/affectations', [AffectationController::class, 'destroyMany'])->middleware('can:affectations.delete')->name('affectations.destroy-many');
        Route::get('/corbeille', [TrashController::class, 'index'])->middleware('can:affectations.delete')->name('trash.index');
        Route::post('/corbeille/restaurer', [TrashController::class, 'restore'])->middleware('can:affectations.delete')->name('trash.restore');
        Route::post('/corbeille/supprimer', [TrashController::class, 'purge'])->middleware('can:affectations.delete')->name('trash.purge');
        Route::get('/affectations/{affectation}', [AffectationController::class, 'show'])->middleware('can:affectations.view')->name('affectations.show');
        Route::put('/affectations/{affectation}', [AffectationController::class, 'update'])->middleware('can:affectations.manage')->name('affectations.update');
        // Extend at the guest's request, any time: + N units of the same formula, supplement cashed or left due.
        Route::post('/affectations/{affectation}/prolonger', [AffectationController::class, 'extend'])->middleware('can:affectations.manage')->name('affectations.extend');
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
        Route::post('/alertes/departs/{reservation}/couper', [CheckoutAlertController::class, 'snooze'])->middleware('can:alerts.checkout')->name('alerts.checkouts.snooze');

        Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:users.manage');
        Route::resource('roles', RoleController::class)->except(['show'])->middleware('can:roles.manage');

        // Online booking requests from the website (« Réserver »).
        Route::get('/demandes-reservation', [BookingRequestController::class, 'index'])->middleware('can:reservations.view')->name('booking-requests.index');
        Route::post('/demandes-reservation/{bookingRequest}/refuser', [BookingRequestController::class, 'refuse'])->middleware('can:reservations.manage')->name('booking-requests.refuse');

        // « Site web »: content of the public website managed from here.
        Route::get('/site/statistiques', [SiteStatsController::class, 'index'])->middleware('can:site.stats')->name('site.stats');
        Route::get('/site/accueil', [HomeContentController::class, 'edit'])->middleware('can:site.manage')->name('site.home.edit');
        Route::put('/site/accueil', [HomeContentController::class, 'update'])->middleware('can:site.manage')->name('site.home.update');
        Route::get('/site/galerie', [GalleryController::class, 'index'])->middleware('can:site.manage')->name('site.gallery.index');
        Route::post('/site/galerie', [GalleryController::class, 'store'])->middleware('can:site.manage')->name('site.gallery.store');
        Route::put('/site/galerie', [GalleryController::class, 'update'])->middleware('can:site.manage')->name('site.gallery.update');
        Route::delete('/site/galerie/{photo}', [GalleryController::class, 'destroy'])->middleware('can:site.manage')->name('site.gallery.destroy');
        Route::resource('site/evenements', EventController::class)->except(['show'])->middleware('can:site.manage')
            ->names('site.events')->parameters(['evenements' => 'event']);
        Route::resource('site/services', ServiceController::class)->except(['show'])->middleware('can:site.manage')
            ->names('site.services')->parameters(['services' => 'service']);

        Route::get('/hotel', [HotelInfoController::class, 'edit'])->middleware('can:settings.manage')->name('hotel.edit');
        Route::put('/hotel', [HotelInfoController::class, 'update'])->middleware('can:settings.manage')->name('hotel.update');

        // « Dépenses » (V2): requested with a receipt, validated by the Gérant. Never deleted.
        Route::get('/depenses', [ExpenseController::class, 'index'])->middleware('can:expenses.access')->name('expenses.index');
        Route::get('/depenses/nouvelle', [ExpenseController::class, 'create'])->middleware('can:expenses.access')->name('expenses.create');
        Route::post('/depenses', [ExpenseController::class, 'store'])->middleware(['can:expenses.access', 'throttle:30,1'])->name('expenses.store');
        Route::get('/depenses/export', [ExpenseController::class, 'export'])->middleware('can:expenses.access')->name('expenses.export');
        Route::get('/depenses/categories', [ExpenseCategoryController::class, 'index'])->middleware('can:expenses.validate')->name('expense-categories.index');
        Route::post('/depenses/categories', [ExpenseCategoryController::class, 'store'])->middleware('can:expenses.validate')->name('expense-categories.store');
        Route::put('/depenses/categories/{category}', [ExpenseCategoryController::class, 'update'])->middleware('can:expenses.validate')->name('expense-categories.update');
        Route::delete('/depenses/categories/{category}', [ExpenseCategoryController::class, 'destroy'])->middleware('can:expenses.validate')->name('expense-categories.destroy');
        Route::get('/depenses/{expense}', [ExpenseController::class, 'show'])->middleware('can:expenses.access')->whereNumber('expense')->name('expenses.show');
        Route::get('/depenses/{expense}/justificatif', [ExpenseController::class, 'receipt'])->middleware('can:expenses.access')->whereNumber('expense')->name('expenses.receipt');
        Route::post('/depenses/{expense}/valider', [ExpenseController::class, 'approve'])->middleware('can:expenses.validate')->whereNumber('expense')->name('expenses.approve');
        Route::post('/depenses/{expense}/refuser', [ExpenseController::class, 'refuse'])->middleware('can:expenses.validate')->whereNumber('expense')->name('expenses.refuse');
        Route::post('/depenses/{expense}/annuler', [ExpenseController::class, 'cancel'])->middleware('can:expenses.access')->whereNumber('expense')->name('expenses.cancel');

        // « Planning » (V2): rooms × time, read only.
        Route::get('/planning', [PlanningController::class, 'index'])->middleware('can:planning.view')->name('planning.index');

        // « Journal d'activité » (V2): read only, no route changes or deletes a line.
        Route::get('/journal', [ActivityLogController::class, 'index'])->middleware('can:activity.view')->name('activity.index');
        Route::get('/journal/export', [ActivityLogController::class, 'export'])->middleware('can:activity.view')->name('activity.export');

        Route::get('/parametres', [SettingsController::class, 'edit'])->middleware('can:settings.manage')->name('settings.edit');
        Route::put('/parametres', [SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');
    });
});

// Android APK (Trusted Web Activity) verification, at the root of the application's host.
Route::domain(config('routing.admin_domain'))->get('/.well-known/assetlinks.json', [PwaController::class, 'assetLinks'])->name('admin.pwa.asset-links');
