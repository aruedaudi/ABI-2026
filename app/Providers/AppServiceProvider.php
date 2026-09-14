<?php

namespace App\Providers;

use App\Helpers\AuthUserHelper;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        Schema::defaultStringLength(191);

        // Forzar HTTPS en producción
        if (config('app.env') === 'production') {
            URL::forceScheme('http');
        }

        View::composer('tablar::partials.header.sidebar-top', static function ($view): void {
            $view->with('authenticatedUserDisplayName', AuthUserHelper::displayName());
        });
    }
}
