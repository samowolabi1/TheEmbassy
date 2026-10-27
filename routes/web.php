<?php

use App\Http\Controllers\Admin;
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

// Church admin. Accounts are created with `php artisan admin:create`; there is no public sign-up.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [Admin\AuthController::class, 'create'])->name('login');
        Route::post('/login', [Admin\AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'can:access-admin'])->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'destroy'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/prayer-requests', [Admin\PrayerRequestController::class, 'index'])->name('prayer-requests.index');
        Route::patch('/prayer-requests/{prayerRequest}', [Admin\PrayerRequestController::class, 'update'])->name('prayer-requests.update');
        Route::delete('/prayer-requests/{prayerRequest}', [Admin\PrayerRequestController::class, 'destroy'])->name('prayer-requests.destroy');

        Route::get('/subscribers', [Admin\SubscriberController::class, 'index'])->name('subscribers.index');
        Route::get('/subscribers/export', [Admin\SubscriberController::class, 'export'])->name('subscribers.export');
        Route::delete('/subscribers/{subscriber}', [Admin\SubscriberController::class, 'destroy'])->name('subscribers.destroy');

        Route::get('/content', Admin\ContentController::class)->name('content');
        Route::resource('announcements', Admin\AnnouncementController::class)->only(['store', 'edit', 'update', 'destroy']);
        Route::post('/declarations', [Admin\DeclarationController::class, 'store'])->name('declarations.store');
    });
});
