<?php

namespace App\Providers;

use App\Services\Payments\PaymentService;
use App\Services\Payments\StubPaymentService;
use App\Tenancy\OrganizerContext;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Single request-scoped source of truth for the active organizer. Shared by
        // the ResolveOrganizer middleware, the BelongsToOrganizer global scope, and
        // the models' auto-stamp hook so they all read/write ONE instance.
        $this->app->singleton(OrganizerContext::class);

        // Only load Collision in local environment and if installed
        if ($this->app->environment('local') && class_exists(\NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class)) {
            $this->app->register(\NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class);
        }

        // Payment gateway resolution. The stub auto-confirms; swapping in a real
        // provider only changes this binding (and the PaymentService impl), never
        // the controllers or OrderService.
        // TODO(real gateway): bind StripePaymentService::class based on
        // config('services.payments.driver').
        $this->app->bind(PaymentService::class, StubPaymentService::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}