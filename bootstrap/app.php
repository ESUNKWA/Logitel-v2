<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Two distinct halves: the public website and the management application (see
        // config/routing.php for giving each its own sub-domain).
        web: __DIR__.'/../routes/site.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        // Phone flag written by the admin layout's script (App\Models\User::PHONE_COOKIE).
        $middleware->encryptCookies(except: ['ek_phone']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Room number / name taken at the same moment by someone else (unique indexes on rooms):
        // the forms check it first, this only catches the race — a message, not a 500.
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            if (! $request->expectsJson() && preg_match('/rooms[._](number|name)/', $e->getMessage())) {
                return back()->withInput()->withErrors([
                    'number' => 'Une chambre avec ce numéro ou ce nom vient d\'être enregistrée : choisissez-en un autre.',
                ]);
            }
        });

        // PostgreSQL's reservations_no_overlap constraint (exclusion_violation): a double booking
        // got past the application checks — answer like a validation error, not a 500.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (($e->errorInfo[0] ?? null) === '23P01' && ! $request->expectsJson()) {
                return back()->withInput()->withErrors([
                    'room_id' => 'Cette chambre vient d\'être prise sur cette période par un autre enregistrement. Rechargez la page et choisissez une autre période ou une autre chambre.',
                ]);
            }
        });
    })->create();
