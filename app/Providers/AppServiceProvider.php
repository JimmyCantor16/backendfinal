<?php

namespace App\Providers;

use App\Models\Business;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

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
        // En este SaaS multi-tenant la suscripción pertenece al Business,
        // no al User. Por eso indicamos a Cashier que el "customer model"
        // es App\Models\Business.
        Cashier::useCustomerModel(Business::class);
    }
}
