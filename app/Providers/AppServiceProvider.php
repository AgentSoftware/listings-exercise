<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
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
        // Inertia props are consumed directly by Vue, so the extra "data"
        // envelope around every resource just gets in the way. Paginated
        // collections keep their data/links/meta structure regardless.
        JsonResource::withoutWrapping();

        // Turn a lazy load into a failure outside production, so an N+1 shows up
        // as a broken test rather than as a slow page. The listings index was
        // quietly issuing one branch query per row before this.
        //
        // Note the framework only arms this on models hydrated from a query that
        // returned more than one row (Builder::hydrate), which is where an N+1
        // can actually happen. A lazy load on a single model — one resolved by
        // route-model binding, say — still goes through silently.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
