<?php

namespace App\Providers;

use App\Models\ServiceProfile;
use App\Policies\ServiceProfilePolicy;
use App\Policies\LocationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ServiceProfile::class => ServiceProfilePolicy::class,
        Location::class => LocationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
