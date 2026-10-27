<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrayerRequestController;
use App\Http\Controllers\TicketOrderController;
use App\Http\Controllers\Vendor;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/watch', [PageController::class, 'watch'])->name('watch');
Route::get('/events', [EventController::class, 'index'])->name('events');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/calendar.ics', [EventController::class, 'calendar'])->name('events.calendar');
Route::post('/events/{event}/book', [TicketOrderController::class, 'store'])->middleware('throttle:10,1')->name('events.book');
Route::middleware('signed')->group(function () {
    Route::get('/tickets/{order}', [TicketOrderController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{order}/pay', [TicketOrderController::class, 'pay'])->middleware('throttle:10,1')->name('tickets.pay');
});
Route::get('/give', [PageController::class, 'give'])->name('give');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Member marketplace.
Route::get('/market', [MarketController::class, 'index'])->name('market.index');
Route::get('/market/open-a-shop', [MarketController::class, 'apply'])->name('market.apply');
Route::post('/market/open-a-shop', [MarketController::class, 'storeApplication'])->middleware('throttle:5,1')->name('market.apply.store');
Route::get('/market/shops/{shop}', [MarketController::class, 'shop'])->name('market.shop');
Route::get('/market/products/{product}', [MarketController::class, 'product'])->name('market.product');
Route::post('/market/products/{product}/enquire', [MarketController::class, 'enquire'])->middleware('throttle:5,1')->name('market.enquire');

// Vendors manage their shop here.
Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [Vendor\AuthController::class, 'create'])->name('login');
        Route::post('/login', [Vendor\AuthController::class, 'store'])->name('login.store');
        Route::get('/forgot-password', [Vendor\AuthController::class, 'forgot'])->name('password.request');
        Route::post('/forgot-password', [Vendor\AuthController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('/set-password/{token}', [Vendor\AuthController::class, 'editPassword'])->name('password.edit');
        Route::post('/set-password', [Vendor\AuthController::class, 'updatePassword'])->middleware('throttle:10,1')->name('password.update');
    });

    Route::middleware(['auth', 'can:run-shop'])->group(function () {
        Route::post('/logout', [Vendor\AuthController::class, 'destroy'])->name('logout');
        Route::redirect('/', '/vendor/products')->name('home');
        Route::get('/enquiries', [Vendor\EnquiryController::class, 'index'])->name('enquiries.index');
        Route::patch('/enquiries/{enquiry}', [Vendor\EnquiryController::class, 'markRead'])->name('enquiries.read');
        Route::get('/subscription', [Vendor\SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('/subscription', [Vendor\SubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('subscription.store');
        Route::resource('products', Vendor\ProductController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    });
});

// Paid notice board for other churches and businesses.
Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
Route::get('/notices/advertise', [NoticeController::class, 'create'])->name('notices.create');
Route::post('/notices', [NoticeController::class, 'store'])->middleware('throttle:10,1')->name('notices.store');
Route::middleware('signed')->group(function () {
    Route::get('/notices/{notice}/status', [NoticeController::class, 'status'])->name('notices.status');
    Route::post('/notices/{notice}/pay', [NoticeController::class, 'pay'])->middleware('throttle:10,1')->name('notices.pay');
});

// Public forms, rate limited to slow down spam.
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/prayer-requests', [PrayerRequestController::class, 'store'])->name('prayer-requests.store');
    Route::post('/newsletter', [NewsletterSubscriptionController::class, 'store'])->name('newsletter.store');
});

// Paystack: the payer's return and Paystack's signed notifications (no CSRF token on the webhook).
Route::get('/payments/callback', [PaymentController::class, 'callback'])->name('payments.callback');
Route::post('/webhooks/paystack', [PaymentController::class, 'webhook'])->name('webhooks.paystack');

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

        Route::get('/notices', [Admin\NoticeController::class, 'index'])->name('notices.index');
        Route::post('/notices/{notice}/approve', [Admin\NoticeController::class, 'approve'])->name('notices.approve');
        Route::post('/notices/{notice}/reject', [Admin\NoticeController::class, 'reject'])->name('notices.reject');

        Route::get('/events/{event}/attendees.csv', [Admin\EventController::class, 'export'])->name('events.export');
        Route::resource('events', Admin\EventController::class);
        Route::get('/tickets', [Admin\TicketController::class, 'lookup'])->name('tickets.lookup');
        Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/check-in', [Admin\TicketController::class, 'checkIn'])->name('tickets.check-in');

        Route::get('/market', [Admin\MarketController::class, 'index'])->name('market.index');
        Route::post('/market/plans', [Admin\MarketController::class, 'plans'])->name('market.plans');
        Route::post('/market/coupons', [Admin\MarketController::class, 'storeCoupon'])->name('market.coupons.store');
        Route::post('/market/coupons/{coupon}/toggle', [Admin\MarketController::class, 'toggleCoupon'])->name('market.coupons.toggle');
        Route::post('/market/shops/{shop}/approve', [Admin\MarketController::class, 'approve'])->name('market.approve');
        Route::post('/market/shops/{shop}/decline', [Admin\MarketController::class, 'decline'])->name('market.decline');
        Route::post('/market/shops/{shop}/suspend', [Admin\MarketController::class, 'toggleSuspend'])->name('market.suspend');
    });
});
