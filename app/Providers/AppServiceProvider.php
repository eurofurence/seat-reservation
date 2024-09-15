<?php

namespace App\Providers;

use App\Models\Block;
use App\Models\Row;
use App\Observers\BlockObserver;
use App\Observers\RowObserver;
use App\Providers\Socialize\SocialiteIdentityProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;

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
        $socialite = $this->app->make(Factory::class);
        Block::observe(BlockObserver::class);
        Row::observe(RowObserver::class);
        $socialite->extend('identity', function () use ($socialite) {
            $config = config('services.identity');

            return $socialite->buildProvider(SocialiteIdentityProvider::class, $config);
        });
    }
}
