<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Policies\CustomerPolicy;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\SubscriptionManager\Policies\SubscriptionPolicy;

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
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
    }
}
