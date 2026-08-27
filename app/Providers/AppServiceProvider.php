<?php

namespace App\Providers;

use App\Enums\Rolle;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Ein Gate pro Rolle; Admin darf alles.
        foreach (Rolle::cases() as $rolle) {
            Gate::define('rolle-'.$rolle->value, fn (User $user): bool => $user->role === $rolle || $user->role === Rolle::Admin);
        }
    }
}
