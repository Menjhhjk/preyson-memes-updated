<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\PremiumController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [MemberProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['put', 'patch'], '/profile', [MemberProfileController::class, 'update'])->middleware('throttle:20,1,profile-update')->name('profile.update');
    Route::delete('/profile', [MemberProfileController::class, 'destroy'])->middleware('throttle:6,1,profile-delete')->name('profile.destroy');

    Route::get('/premium', [PremiumController::class, 'index'])->name('premium.index');
    Route::post('/premium/checkout', [PremiumController::class, 'checkout'])->middleware('throttle:6,1,premium-checkout')->name('premium.checkout');

    Route::resource('accounts', AccountController::class)->except('show')->middleware('throttle:60,1,account-management');
});
