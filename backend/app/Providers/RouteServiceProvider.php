<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // PHASE 4 — find-my-tickets (audit H9). Contract requires a tight throttle
        // KEYED BY EMAIL so a single target email cannot be probed for existence even
        // across rotating IPs. Keyed on the normalized email + the client IP (3 / 10min):
        // the email component caps attempts against any one address, the IP component
        // still caps a single host hammering many emails. The endpoint already returns
        // an identical generic response regardless of match, so this only hardens
        // against brute-forcing; it never changes what is revealed.
        RateLimiter::for('find-tickets', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return Limit::perMinutes(10, 3)->by($email !== '' ? 'find:' . $email : 'find-ip:' . $request->ip());
        });
    }
}
