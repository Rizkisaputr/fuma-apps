<?php

namespace App\Providers;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Schema;
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
        View::composer('*', function ($view): void {
            $settings = Schema::hasTable('application_settings')
                ? ApplicationSetting::current()
                : new ApplicationSetting([
                    'app_name' => 'FUMA',
                    'default_fee_amount' => 15000,
                    'default_court_count' => 1,
                ]);

            $view->with('appSettings', $settings);
        });
    }
}
