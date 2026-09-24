<?php

use App\Http\Controllers\PostController;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $posts = Post::latest()->get();

    return view('blog', compact('posts'));
})->name('home');

// Fortify owns login, registration and logout, including throttling and session security.
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $posts = Post::query()
            ->when(! auth()->user()->is_admin, fn ($query) => $query->where('user_id', auth()->id()))
            ->latest()->get();

        return view('dashboard', compact('posts'));
    })->name('dashboard');

    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('/posts/zip', [PostController::class, 'store'])->name('posts.zip');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/batch-delete', [PostController::class, 'batchDelete'])->name('posts.batchDelete');
});
