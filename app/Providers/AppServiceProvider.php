<?php

namespace App\Providers;

use App\Models\Offer;
use App\Models\RidePassenger;
use App\Models\RideRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Observers\OfferObserver;
use App\Observers\RidePassengerObserver;
use App\Observers\RideRequestObserver;
use App\Observers\ServiceRequestObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        ServiceRequest::observe(ServiceRequestObserver::class);
        RideRequest::observe(RideRequestObserver::class);
        Offer::observe(OfferObserver::class);
        RidePassenger::observe(RidePassengerObserver::class);
        Carbon::setLocale('es');
        URL::forceRootUrl(config('app.url'));

        Gate::define('search_users', function (User $user) {
            return $user->isAdmin() || $user->hasVerifiedEmail();
        });
    }
}
