<?php

use App\Http\Controllers\StayRequestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.accueil')->name('home');
Route::view('/a-propos', 'pages.a-propos')->name('about');

Route::view('/chambres', 'pages.chambres')->name('rooms.index');
Route::view('/chambres/detail', 'pages.chambres-detail')->name('rooms.show');

Route::view('/services', 'pages.services')->name('services.index');
Route::view('/services/detail', 'pages.services-detail')->name('services.show');

Route::view('/equipe', 'pages.equipe')->name('team.index');
Route::view('/equipe/detail', 'pages.equipe-detail')->name('team.show');

Route::view('/offres', 'pages.offres')->name('offers.index');
Route::view('/offres/detail', 'pages.offres-detail')->name('offers.show');

Route::view('/tarifs', 'pages.tarifs')->name('pricing');
Route::view('/galerie', 'pages.galerie')->name('gallery');
Route::view('/faq', 'pages.faq')->name('faq');
Route::view('/contact', 'pages.contact')->name('contact');

Route::view('/blog', 'pages.blog')->name('blog.index');
Route::view('/blog/detail', 'pages.blog-detail')->name('blog.show');

// Guest's room request from their phone on arrival (the only public page backed by the database):
// pick a free room, send the request; the réception validates it and cashes in /admin/demandes.
Route::get('/demande', [StayRequestController::class, 'index'])->name('stay-request.index');
Route::get('/demande/chambre/{room}', [StayRequestController::class, 'create'])->name('stay-request.create');
Route::post('/demande/chambre/{room}', [StayRequestController::class, 'store'])->middleware('throttle:10,10')->name('stay-request.store');
Route::get('/demande/suivi/{stayRequest}', [StayRequestController::class, 'show'])->middleware('signed')->name('stay-request.show');
