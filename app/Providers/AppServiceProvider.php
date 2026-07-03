<?php

namespace App\Providers;

use App\Listeners\OrderEventSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // cPanel / older MySQL: utf8mb4 unique indexes max ~1000 bytes (255 chars = 1020).
        Schema::defaultStringLength(191);

        Event::subscribe(OrderEventSubscriber::class);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('SuperAdmin') ? true : null;
        });
    }
}
