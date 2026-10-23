<?php

namespace App\Providers;

use App\Models\ServicePage;
use App\Services\SharedWebsiteDesign;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ViewFacade::composer('services.layout', function (View $view): void {
            $view->with('sharedWebsiteDesign', app(SharedWebsiteDesign::class)->get());
            $view->with('navigationPages', ServicePage::where('published', true)
                ->whereIn('slug', ['domestic-cleaning', 'commercial-cleaning', 'care-home-cleaning'])
                ->get());
        });
    }
}
