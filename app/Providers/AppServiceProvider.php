<?php

namespace App\Providers;

use App\Services\Illuminate\CredentialStore;
use App\Services\Illuminate\IlluminateClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(IlluminateClient::class, fn (): IlluminateClient => new IlluminateClient(
            baseUrl: config('services.illuminate.url'),
            token: config('services.illuminate.token'),
            timeout: config('services.illuminate.timeout'),
        ));

        $this->app->singleton(CredentialStore::class, fn (): CredentialStore => new CredentialStore(Storage::disk('local')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
