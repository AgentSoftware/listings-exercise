<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\SavedSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ListingController::class, 'index'])->name('listings.index');
Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');

Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
// whereNumber so a non-numeric id is a 404 from the router rather than reaching
// findOrFail(), which on Postgres would be an invalid-integer 500.
Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])
    ->whereNumber('savedSearch')
    ->name('saved-searches.destroy');

Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::post('/alerts/read', [AlertController::class, 'markAllRead'])->name('alerts.read');
