<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\CornerRequestController;
use App\Http\Controllers\MemberWarningController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostPowerController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\SuperReactionCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/comments/{comment}/replies', [CommentController::class, 'replies'])->whereNumber('comment')->name('comments.replies');
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->middleware('throttle:20,1,notification-count')->name('notifications.count');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->whereNumber('notification')->name('notifications.open');
    Route::get('/rewards', [RewardController::class, 'index'])->name('rewards.index');
    Route::post('/corner-requests', [CornerRequestController::class, 'store'])->middleware('throttle:5,1,corner-request')->name('corners.store');
    Route::get('/admin/corner-requests', [CornerRequestController::class, 'index'])->name('corners.review');
    Route::patch('/admin/corner-requests/{corner}', [CornerRequestController::class, 'update'])->whereNumber('corner')->name('corners.update');
    Route::get('/warnings', [MemberWarningController::class, 'mine'])->name('warnings.mine');
    Route::get('/moderation/members/{member}/warnings', [MemberWarningController::class, 'create'])->whereNumber('member')->name('warnings.create');
    Route::post('/moderation/members/{member}/warnings', [MemberWarningController::class, 'store'])->whereNumber('member')->middleware('throttle:10,1,member-warning')->name('warnings.store');
    Route::post('/posts/{post}/boost', [PostPowerController::class, 'boost'])->whereNumber('post')->middleware('throttle:15,1,post-power')->name('posts.boost');
    Route::post('/posts/{post}/super-react', [PostPowerController::class, 'superReact'])->whereNumber('post')->middleware('throttle:15,1,post-power')->name('posts.super-react');
    Route::put('/comments/{comment}/heart', [CommentController::class, 'heart'])->whereNumber('comment')->middleware('throttle:30,1,comment-control')->name('comments.heart');
    Route::put('/comments/{comment}/pin', [CommentController::class, 'pin'])->whereNumber('comment')->middleware('throttle:30,1,comment-control')->name('comments.pin');
    Route::get('/admin/super-reactions', [SuperReactionCatalogController::class, 'index'])->name('super-catalog.index');
    Route::post('/admin/super-reactions', [SuperReactionCatalogController::class, 'store'])->name('super-catalog.store');
    Route::put('/admin/super-reactions/{type}', [SuperReactionCatalogController::class, 'update'])->whereNumber('type')->name('super-catalog.update');
});
