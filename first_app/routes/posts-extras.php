<?php

use App\Http\Controllers\PostController;
use App\Http\Controllers\ReactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->whereNumber('post')->name('posts.destroy');
    Route::post('/posts/{post}/reaction', [ReactionController::class, 'store'])->middleware('throttle:60,1,post-reactions')->name('posts.react');
});
