<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\GreensController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GreensController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/greens/{green}/{date}/sheet', [GreensController::class, 'sheet'])->name('greens.sheet');
    Route::post('/greens/{green}/{date}/close', [GreensController::class, 'close'])->name('greens.close');
    Route::post('/greens/{green}/{date}/open', [GreensController::class, 'open'])->name('greens.open');
});

Route::get('/greens/{green}/{date?}', [GreensController::class, 'show'])->name('greens.show');

Route::get('/setup', [SetupController::class, 'create'])->name('setup');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::view('/info', 'pages.info')->name('info');
Route::view('/help', 'pages.help')->name('help');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
Route::get('/documents/{document}', [PageController::class, 'document'])
    ->where('document', 'terms|privacy')->name('documents.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/book', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/book', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}/confirmation', [BookingController::class, 'confirmation'])->name('bookings.confirmation');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/email', [AccountController::class, 'updateEmail'])->name('account.email');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::get('/account/data', [AccountController::class, 'download'])->name('account.data');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
});
