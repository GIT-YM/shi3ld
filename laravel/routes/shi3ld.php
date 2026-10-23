<?php

use App\Http\Controllers\BuilderFormController;
use App\Http\Controllers\BuilderLibraryController;
use App\Http\Controllers\ServiceEnquiryController;
use App\Http\Controllers\ServicesAdminController;
use App\Http\Controllers\ServicesPageController;
use App\Http\Controllers\VisualContentController;
use App\Http\Middleware\EnsureServicesStaff;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/services');
Route::redirect('/quote', '/services/quote');

Route::prefix('services')->group(function (): void {
    Route::get('/builder/forms/{key}', [BuilderFormController::class, 'show'])->where('key', '[a-f0-9]{32}')->middleware('throttle:60,1')->name('services.builder.form');
    Route::post('/builder/forms/{key}', [BuilderFormController::class, 'store'])->where('key', '[a-f0-9]{32}')->middleware('throttle:5,1')->name('services.builder.submit');
    Route::get('/editor/locate', [VisualContentController::class, 'locate'])->name('services.visual.locate');
    Route::get('/', [ServicesPageController::class, 'home'])->name('services.home');
    Route::get('/sitemap.xml', [ServicesPageController::class, 'sitemap'])->name('services.sitemap');
    Route::get('/about', fn () => redirect(config('shi3ld.about_url'), 301))->name('services.about');
    foreach (['how-it-works', 'areas', 'faqs', 'contact', 'privacy', 'cookies', 'terms', 'gallery'] as $page) {
        Route::get('/'.$page, [ServicesPageController::class, 'information'])->defaults('page', $page)->name('services.'.$page);
    }
    Route::get('/quote', [ServiceEnquiryController::class, 'create'])->name('quote.create');
    Route::post('/quote', [ServiceEnquiryController::class, 'store'])->middleware('throttle:5,1')->name('quote.store');

    Route::get('/staff/login', [ServicesAdminController::class, 'login'])->name('login')->middleware('guest');
    Route::post('/staff/login', [ServicesAdminController::class, 'authenticate'])->middleware(['guest', 'throttle:5,1'])->name('services.staff.authenticate');
    Route::prefix('staff')->middleware(['auth', EnsureServicesStaff::class])->group(function (): void {
        Route::get('/builder/library', [BuilderLibraryController::class, 'index'])->name('services.staff.builder.library');
        Route::get('/visual/{key}', [VisualContentController::class, 'index'])->name('services.staff.visual');
        Route::get('/visual/{key}/frame', [VisualContentController::class, 'frame'])->name('services.staff.visual.frame');
        Route::get('/visual/{key}/preview', [VisualContentController::class, 'preview'])->name('services.staff.visual.preview');
        Route::post('/visual/{key}', [VisualContentController::class, 'command'])->middleware('throttle:120,1')->name('services.staff.visual.command');
        Route::get('/', [ServicesAdminController::class, 'index'])->name('services.staff.index');
        Route::post('/logout', [ServicesAdminController::class, 'logout'])->name('services.staff.logout');
        Route::get('/export', [ServicesAdminController::class, 'export'])->middleware('throttle:10,1')->name('services.staff.export');
        Route::get('/enquiries/{enquiry}', [ServicesAdminController::class, 'show'])->name('services.staff.enquiry');
        Route::patch('/enquiries/{enquiry}', [ServicesAdminController::class, 'update'])->name('services.staff.update');
        Route::get('/content', [ServicesAdminController::class, 'content'])->name('services.staff.content');
        Route::get('/content/{page}/edit', [ServicesAdminController::class, 'editContent'])->name('services.staff.content.edit');
        Route::patch('/content/{page}', [ServicesAdminController::class, 'updateContent'])->name('services.staff.content.update');
    });

    Route::get('/{slug}', [ServicesPageController::class, 'service'])->where('slug', '[a-z0-9-]+')->name('services.service');
});
