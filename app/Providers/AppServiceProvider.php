<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
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
                // Basic production check
                URL::forceHttps($this->app->isProduction());
                // Or more specific environment control
                URL::forceHttps(
                    $this->app->environment(['staging', 'production', 'demo'])
                    && !$this->app->environment('testing')
                );
                // You might also want to combine it with other security headers
                if ($this->app->isProduction()) {
                    $this->app['request']->server->set('HTTPS', true);

                    // Add security headers
                    $this->app['router']->middleware(function ($request, $next) {
                        $response = $next($request);

                        return $response->withHeaders([
                            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                            'X-Frame-Options' => 'SAMEORIGIN',
                            'X-Content-Type-Options' => 'nosniff'
                        ]);
                    });
                }
    }
}
