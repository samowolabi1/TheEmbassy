<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GiveController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrayerRequestController;
use App\Http\Controllers\TicketOrderController;
use App\Http\Controllers\Unit;
use App\Http\Controllers\Vendor;
use App\Http\Controllers\WelcomeController;
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
Route::get('/give', [GiveController::class, 'create'])->name('give');
Route::post('/give', [GiveController::class, 'store'])->middleware('throttle:10,1')->name('give.store');
Route::get('/give/thanks/{gift}', [GiveController::class, 'thanks'])->middleware('signed')->name('give.thanks');

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
        Route::get('/shop', [Vendor\ShopController::class, 'edit'])->name('shop.edit');
        Route::put('/shop', [Vendor\ShopController::class, 'update'])->name('shop.update');
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

// Member accounts: joining the church register, signing in, and the member's own page.
Route::middleware('guest')->group(function () {
    Route::get('/login', [Account\AuthController::class, 'create'])->name('login');
    Route::post('/login', [Account\AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [Account\AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [Account\AuthController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [Account\AuthController::class, 'editPassword'])->name('password.reset');
    Route::post('/reset-password', [Account\AuthController::class, 'updatePassword'])->middleware('throttle:10,1')->name('password.update');
});
Route::get('/join', [Account\AuthController::class, 'join'])->name('join');
Route::post('/join', [Account\AuthController::class, 'storeJoin'])->middleware('throttle:5,1')->name('join.store');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [Account\AuthController::class, 'destroy'])->name('logout');
    Route::get('/account', [Account\AccountController::class, 'show'])->name('account');
    Route::put('/account', [Account\AccountController::class, 'update'])->name('account.update');
    Route::get('/account/tokens', [Account\AccountController::class, 'tokens'])->name('account.tokens');
    Route::get('/account/welfare', [Account\WelfareController::class, 'index'])->name('account.welfare');
    Route::post('/account/welfare', [Account\WelfareController::class, 'store'])->middleware('throttle:5,1')->name('account.welfare.store');
    Route::get('/account/welfare/cases/{case}', [Account\WelfareController::class, 'show'])->name('account.welfare.case');
    Route::post('/account/welfare/cases/{case}', [Account\WelfareController::class, 'note'])->name('account.welfare.note');
    Route::get('/check-in', [Account\CheckInController::class, 'show'])->name('check-in');
    Route::post('/check-in', [Account\CheckInController::class, 'store'])->middleware('throttle:10,1')->name('check-in.store');
    Route::post('/account/tokens', [Account\AccountController::class, 'payTokens'])->middleware('throttle:10,1')->name('account.tokens.pay');
});

// First-timer card for visitors (e.g. from a QR code at the door).
Route::get('/welcome', [WelcomeController::class, 'create'])->name('welcome');
Route::post('/welcome', [WelcomeController::class, 'store'])->middleware('throttle:5,1')->name('welcome.store');

// Unit leaders' area: a unit's HOD and assistant (and staff who manage units).
Route::middleware(['auth', 'can:lead-unit,unit'])->prefix('units/{unit}')->name('unit.')->group(function () {
    Route::get('/', [Unit\UnitController::class, 'show'])->name('show');
    Route::get('/members', [Unit\UnitController::class, 'members'])->name('members');
    Route::post('/members', [Unit\UnitController::class, 'addMember'])->name('members.add');
    Route::delete('/members/{member}', [Unit\UnitController::class, 'removeMember'])->name('members.remove');
    Route::get('/attendance', [Unit\UnitController::class, 'attendance'])->name('attendance');
    Route::get('/messages', [Unit\UnitController::class, 'messages'])->name('messages');
    Route::post('/messages', [Unit\UnitController::class, 'sendMessage'])->middleware('throttle:10,1')->name('messages.send');
    Route::get('/rota', [Unit\RotaController::class, 'index'])->name('rota');
    Route::post('/rota', [Unit\RotaController::class, 'store'])->name('rota.store');
    Route::delete('/rota/{duty}', [Unit\RotaController::class, 'destroy'])->name('rota.destroy');
    Route::get('/meetings', [Unit\MeetingController::class, 'index'])->name('meetings.index');
    Route::post('/meetings', [Unit\MeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', [Unit\MeetingController::class, 'show'])->name('meetings.show');
    Route::put('/meetings/{meeting}', [Unit\MeetingController::class, 'update'])->name('meetings.update');
    Route::post('/meetings/{meeting}/present', [Unit\MeetingController::class, 'mark'])->name('meetings.mark');
    Route::get('/meetings/{meeting}/screen', [Unit\MeetingController::class, 'screen'])->name('meetings.screen');
    Route::delete('/meetings/{meeting}', [Unit\MeetingController::class, 'destroy'])->name('meetings.destroy');

    Route::middleware('can:manage-welfare,unit')->prefix('welfare')->name('welfare.')->group(function () {
        Route::get('/', [Unit\WelfareController::class, 'index'])->name('index');
        Route::get('/new', [Unit\WelfareController::class, 'create'])->name('create');
        Route::post('/', [Unit\WelfareController::class, 'store'])->name('store');
        Route::get('/celebrations', [Unit\WelfareController::class, 'celebrations'])->name('celebrations');
        Route::get('/export', [Unit\WelfareController::class, 'export'])->name('export');
        Route::get('/{case}', [Unit\WelfareController::class, 'show'])->name('show');
        Route::put('/{case}', [Unit\WelfareController::class, 'update'])->name('update');
        Route::post('/{case}/act', [Unit\WelfareController::class, 'act'])->name('act');
        Route::post('/{case}/pay', [Unit\WelfareController::class, 'pay'])->name('pay');
        Route::post('/{case}/log', [Unit\WelfareController::class, 'note'])->name('note');
    });
});
// The media unit's tools: equipment, service setup and streaming, social media.
Route::middleware(['auth', 'can:media-team,unit'])->prefix('units/{unit}/media')->name('unit.')->group(function () {
    Route::get('/equipment', [Unit\EquipmentController::class, 'index'])->name('equipment.index');
    Route::post('/equipment/{equipment}/report', [Unit\EquipmentController::class, 'report'])->name('equipment.report');
    Route::get('/setup', [Unit\SetupController::class, 'index'])->name('setup.index');
    Route::get('/setup/{service}', [Unit\SetupController::class, 'show'])->name('setup.show');
    Route::post('/setup/tasks/{task}/toggle', [Unit\SetupController::class, 'toggleTask'])->name('setup.tasks.toggle');
    Route::get('/social', [Unit\SocialController::class, 'index'])->name('social.index');
    Route::post('/social', [Unit\SocialController::class, 'store'])->name('social.store');
    Route::get('/social/{post}', [Unit\SocialController::class, 'show'])->name('social.show');
    Route::post('/social/{post}/act', [Unit\SocialController::class, 'act'])->name('social.act');
    Route::delete('/social/{post}', [Unit\SocialController::class, 'destroy'])->name('social.destroy');

    Route::middleware('can:lead-media,unit')->group(function () {
        Route::post('/equipment', [Unit\EquipmentController::class, 'store'])->name('equipment.store');
        Route::put('/equipment/{equipment}', [Unit\EquipmentController::class, 'update'])->name('equipment.update');
        Route::post('/equipment/{equipment}/out', [Unit\EquipmentController::class, 'checkOut'])->name('equipment.out');
        Route::post('/equipment/{equipment}/in', [Unit\EquipmentController::class, 'checkIn'])->name('equipment.in');
        Route::post('/issues/{issue}/fixed', [Unit\EquipmentController::class, 'resolve'])->name('equipment.resolve');
        Route::put('/setup/{service}', [Unit\SetupController::class, 'update'])->name('setup.update');
        Route::post('/setup/{service}/tasks', [Unit\SetupController::class, 'addTask'])->name('setup.tasks.store');
        Route::put('/setup/tasks/{task}', [Unit\SetupController::class, 'assignTask'])->name('setup.tasks.assign');
        Route::post('/setup/{service}/live', [Unit\SetupController::class, 'goLive'])->name('setup.live');
        Route::post('/setup/{service}/end', [Unit\SetupController::class, 'endLive'])->name('setup.end');
        Route::put('/checklist', [Unit\SetupController::class, 'checklist'])->name('setup.checklist');
        Route::post('/streams', [Unit\SetupController::class, 'storeDestination'])->name('streams.store');
        Route::put('/streams/{destination}', [Unit\SetupController::class, 'updateDestination'])->name('streams.update');
        Route::delete('/streams/{destination}', [Unit\SetupController::class, 'destroyDestination'])->name('streams.destroy');
        Route::get('/streams/{destination}/key', [Unit\SetupController::class, 'revealKey'])->name('streams.key');
        Route::post('/youtube/connect', [Unit\YouTubeController::class, 'connect'])->name('youtube.connect');
        Route::delete('/youtube', [Unit\YouTubeController::class, 'disconnect'])->name('youtube.disconnect');
        Route::post('/setup/{service}/youtube', [Unit\YouTubeController::class, 'create'])->name('youtube.create');
        Route::put('/setup/{service}/youtube', [Unit\YouTubeController::class, 'push'])->name('youtube.push');
        Route::delete('/setup/{service}/youtube', [Unit\YouTubeController::class, 'destroy'])->name('youtube.destroy');
    });
});

// Google sends the media leader back here after YouTube sign-in (register this address in Google Cloud).
Route::get('/youtube/callback', [Unit\YouTubeController::class, 'callback'])->middleware('auth')->name('youtube.callback');

Route::post('/meetings/{meeting}/check-in', [Unit\MeetingController::class, 'checkIn'])->middleware(['auth', 'throttle:10,1'])->name('meetings.check-in');

// Contact and legal pages.
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/terms', 'pages.terms')->name('terms');

// Paystack: the payer's return and Paystack's signed notifications (no CSRF token on the webhook).
Route::get('/payments/callback', [PaymentController::class, 'callback'])->name('payments.callback');
Route::post('/webhooks/paystack', [PaymentController::class, 'webhook'])->name('webhooks.paystack');

// Church admin. The first administrator is created with `php artisan admin:create`; they add other staff in Settings & roles.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [Admin\AuthController::class, 'create'])->name('login');
        Route::post('/login', [Admin\AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'can:access-admin'])->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'destroy'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::middleware('can:manage,"members"')->group(function () {
            Route::get('/members/export', [Admin\MemberController::class, 'export'])->name('members.export');
            Route::post('/members/import', [Admin\MemberController::class, 'import'])->name('members.import');
            Route::post('/members/{member}/approve', [Admin\MemberController::class, 'approve'])->name('members.approve');
            Route::resource('members', Admin\MemberController::class);
        });

        Route::middleware('can:manage,"attendance"')->group(function () {
            Route::get('/attendance', [Admin\AttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance', [Admin\AttendanceController::class, 'store'])->name('attendance.store');
            Route::get('/attendance/{service}', [Admin\AttendanceController::class, 'show'])->name('attendance.show');
            Route::get('/attendance/{service}/screen', [Admin\AttendanceController::class, 'screen'])->name('attendance.screen');
            Route::put('/attendance/{service}', [Admin\AttendanceController::class, 'update'])->name('attendance.update');
            Route::post('/attendance/{service}/present', [Admin\AttendanceController::class, 'mark'])->name('attendance.mark');
            Route::delete('/attendance/{service}', [Admin\AttendanceController::class, 'destroy'])->name('attendance.destroy');

            Route::get('/first-timers', [Admin\FirstTimerController::class, 'index'])->name('first-timers.index');
            Route::get('/first-timers/export', [Admin\FirstTimerController::class, 'export'])->name('first-timers.export');
            Route::post('/first-timers', [Admin\FirstTimerController::class, 'store'])->name('first-timers.store');
            Route::post('/first-timers/{firstTimer}/follow-up', [Admin\FirstTimerController::class, 'followUp'])->name('first-timers.follow-up');
            Route::post('/first-timers/{firstTimer}/register', [Admin\FirstTimerController::class, 'addToRegister'])->middleware('can:manage,"members"')->name('first-timers.register');
            Route::delete('/first-timers/{firstTimer}', [Admin\FirstTimerController::class, 'destroy'])->name('first-timers.destroy');
        });

        Route::middleware('can:manage,"care"')->group(function () {
            Route::get('/care', [Admin\CareController::class, 'index'])->name('care.index');
            Route::post('/care', [Admin\CareController::class, 'store'])->name('care.store');
            Route::post('/care/{followUp}/assign', [Admin\CareController::class, 'assign'])->name('care.assign');
            Route::post('/care/{followUp}/done', [Admin\CareController::class, 'complete'])->name('care.complete');
        });

        // Pastors reach welfare cases through the welfare unit's area.
        Route::get('/welfare', [Admin\UnitController::class, 'welfare'])->middleware('can:manage,"welfare"')->name('welfare');

        Route::middleware('can:manage,"units"')->group(function () {
            Route::get('/units', [Admin\UnitController::class, 'index'])->name('units.index');
            Route::post('/units', [Admin\UnitController::class, 'storeUnit'])->name('units.store');
            Route::put('/units/{unit}', [Admin\UnitController::class, 'updateUnit'])->name('units.update');
            Route::delete('/units/{unit}', [Admin\UnitController::class, 'destroyUnit'])->name('units.destroy');
            Route::post('/fellowships', [Admin\UnitController::class, 'storeFellowship'])->name('fellowships.store');
            Route::put('/fellowships/{fellowship}', [Admin\UnitController::class, 'updateFellowship'])->name('fellowships.update');
            Route::delete('/fellowships/{fellowship}', [Admin\UnitController::class, 'destroyFellowship'])->name('fellowships.destroy');
        });

        Route::middleware('can:manage,"finance"')->group(function () {
            Route::get('/finance', [Admin\FinanceController::class, 'index'])->name('finance.index');
            Route::get('/finance/export', [Admin\FinanceController::class, 'export'])->name('finance.export');
            Route::post('/finance/gifts', [Admin\FinanceController::class, 'storeGift'])->name('gifts.store');
            Route::delete('/finance/gifts/{gift}', [Admin\FinanceController::class, 'destroyGift'])->name('gifts.destroy');
            Route::post('/finance/funds', [Admin\FinanceController::class, 'storeFund'])->name('funds.store');
            Route::put('/finance/funds/{fund}', [Admin\FinanceController::class, 'updateFund'])->name('funds.update');
            Route::post('/finance/token-plans', [Admin\FinanceController::class, 'storePlan'])->name('token-plans.store');
            Route::put('/finance/token-plans/{plan}', [Admin\FinanceController::class, 'updatePlan'])->name('token-plans.update');
            Route::post('/finance/tokens', [Admin\FinanceController::class, 'storeTokenPayment'])->name('tokens.store');
            Route::post('/finance/tokens/remind', [Admin\FinanceController::class, 'remind'])->name('tokens.remind');
        });

        Route::middleware('can:manage,"expenses"')->group(function () {
            Route::get('/expenses', [Admin\ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('/expenses/export', [Admin\ExpenseController::class, 'export'])->name('expenses.export');
            Route::post('/expenses', [Admin\ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('/expenses/{expense}/receipt', [Admin\ExpenseController::class, 'receipt'])->name('expenses.receipt');
            Route::post('/expenses/{expense}/approve', [Admin\ExpenseController::class, 'approve'])->name('expenses.approve');
            Route::post('/expenses/{expense}/decline', [Admin\ExpenseController::class, 'decline'])->name('expenses.decline');
            Route::put('/budgets', [Admin\ExpenseController::class, 'budgets'])->name('budgets.update');
        });

        Route::middleware('can:manage,"prayer"')->group(function () {
            Route::get('/prayer-requests', [Admin\PrayerRequestController::class, 'index'])->name('prayer-requests.index');
            Route::patch('/prayer-requests/{prayerRequest}', [Admin\PrayerRequestController::class, 'update'])->name('prayer-requests.update');
            Route::delete('/prayer-requests/{prayerRequest}', [Admin\PrayerRequestController::class, 'destroy'])->name('prayer-requests.destroy');
        });

        Route::middleware('can:manage,"subscribers"')->group(function () {
            Route::get('/subscribers', [Admin\SubscriberController::class, 'index'])->name('subscribers.index');
            Route::get('/subscribers/export', [Admin\SubscriberController::class, 'export'])->name('subscribers.export');
            Route::delete('/subscribers/{subscriber}', [Admin\SubscriberController::class, 'destroy'])->name('subscribers.destroy');
        });

        Route::middleware('can:manage,"content"')->group(function () {
            Route::get('/content', Admin\ContentController::class)->name('content');
            Route::resource('announcements', Admin\AnnouncementController::class)->only(['store', 'edit', 'update', 'destroy']);
            Route::post('/declarations', [Admin\DeclarationController::class, 'store'])->name('declarations.store');
        });

        Route::middleware('can:manage,"notices"')->group(function () {
            Route::get('/notices', [Admin\NoticeController::class, 'index'])->name('notices.index');
            Route::post('/notices/{notice}/approve', [Admin\NoticeController::class, 'approve'])->name('notices.approve');
            Route::post('/notices/{notice}/reject', [Admin\NoticeController::class, 'reject'])->name('notices.reject');
        });

        Route::middleware('can:manage,"sermons"')->group(function () {
            Route::post('/sermons/live', [Admin\SermonController::class, 'live'])->name('sermons.live');
            Route::post('/series', [Admin\SermonController::class, 'storeSeries'])->name('series.store');
            Route::put('/series/{series}', [Admin\SermonController::class, 'updateSeries'])->name('series.update');
            Route::delete('/series/{series}', [Admin\SermonController::class, 'destroySeries'])->name('series.destroy');
            Route::resource('sermons', Admin\SermonController::class)->except('show');
        });

        Route::middleware('can:manage,"events"')->group(function () {
            Route::get('/events/{event}/attendees.csv', [Admin\EventController::class, 'export'])->name('events.export');
            Route::resource('events', Admin\EventController::class);
            Route::get('/tickets', [Admin\TicketController::class, 'lookup'])->name('tickets.lookup');
            Route::get('/tickets/{ticket}', [Admin\TicketController::class, 'show'])->name('tickets.show');
            Route::post('/tickets/{ticket}/check-in', [Admin\TicketController::class, 'checkIn'])->name('tickets.check-in');
        });

        Route::middleware('can:manage,"market"')->group(function () {
            Route::get('/market', [Admin\MarketController::class, 'index'])->name('market.index');
            Route::get('/market/subscriptions.csv', [Admin\MarketController::class, 'export'])->name('market.export');
            Route::post('/market/plans', [Admin\MarketController::class, 'plans'])->name('market.plans');
            Route::post('/market/coupons', [Admin\MarketController::class, 'storeCoupon'])->name('market.coupons.store');
            Route::post('/market/coupons/{coupon}/toggle', [Admin\MarketController::class, 'toggleCoupon'])->name('market.coupons.toggle');
            Route::post('/market/shops/{shop}/approve', [Admin\MarketController::class, 'approve'])->name('market.approve');
            Route::post('/market/shops/{shop}/decline', [Admin\MarketController::class, 'decline'])->name('market.decline');
            Route::post('/market/shops/{shop}/suspend', [Admin\MarketController::class, 'toggleSuspend'])->name('market.suspend');
        });

        Route::middleware('can:manage,"messages"')->group(function () {
            Route::get('/messages', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
            Route::patch('/messages/{message}', [Admin\ContactMessageController::class, 'update'])->name('messages.update');
            Route::delete('/messages/{message}', [Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');
        });

        Route::middleware('can:manage,"settings"')->group(function () {
            Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
            Route::post('/staff', [Admin\StaffController::class, 'store'])->name('staff.store');
            Route::patch('/staff/{user}', [Admin\StaffController::class, 'update'])->name('staff.update');
            Route::delete('/staff/{user}', [Admin\StaffController::class, 'destroy'])->name('staff.destroy');
        });
    });
});
