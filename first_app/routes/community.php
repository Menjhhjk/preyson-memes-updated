<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\PostDiscussionController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportLogController;
use Illuminate\Support\Facades\Route;

Route::get('/members/{member}', [PublicProfileController::class, 'show'])->whereNumber('member')->name('profiles.show');
Route::get('/posts/{post}', [PostDiscussionController::class, 'show'])->whereNumber('post')->name('posts.show');
Route::get('/posts/{post}/media', [PostDiscussionController::class, 'media'])->whereNumber('post')->name('posts.media');
Route::get('/comments/{comment}', [CommentController::class, 'show'])->whereNumber('comment')->name('comments.show');

Route::middleware('auth')->group(function () {
    Route::put('/posts/{post}/pin', [PublicProfileController::class, 'pin'])->whereNumber('post')->name('posts.pin');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->whereNumber('post')->middleware('throttle:12,1,comment-create')->name('comments.store');
    Route::patch('/comments/{comment}', [CommentController::class, 'update'])->whereNumber('comment')->middleware('throttle:30,1,comment-edit')->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->whereNumber('comment')->name('comments.destroy');
    Route::get('/report/{type}/{id}', [ReportController::class, 'create'])->whereIn('type', ['post', 'comment', 'account'])->whereNumber('id')->name('reports.create');
    Route::post('/report/{type}/{id}', [ReportController::class, 'store'])->whereIn('type', ['post', 'comment', 'account'])->whereNumber('id')->middleware('throttle:10,1,report-create')->name('reports.store');
    Route::get('/reports', [ReportLogController::class, 'index'])->name('reports.index');
    Route::get('/reports/leaderboard', [ReportLogController::class, 'leaderboard'])->name('reports.leaderboard');
    Route::patch('/reports/{report}', [ReportLogController::class, 'update'])->whereNumber('report')->middleware('throttle:60,1,report-review')->name('reports.update');
});
