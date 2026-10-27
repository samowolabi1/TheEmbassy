<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PrayerRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/watch', [PageController::class, 'watch'])->name('watch');
Route::get('/events', [PageController::class, 'events'])->name('events');
Route::get('/give', [PageController::class, 'give'])->name('give');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Public forms, rate limited to slow down spam.
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/prayer-requests', [PrayerRequestController::class, 'store'])->name('prayer-requests.store');
    Route::post('/newsletter', [NewsletterSubscriptionController::class, 'store'])->name('newsletter.store');
});
