<?php

namespace App\Providers;

use Google\Client;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Client::class, function (): Client {
            $client = new Client(['client_id' => (string) config('services.google.client_id')]);
            $client->setHttpClient(new HttpClient(['timeout' => 10, 'connect_timeout' => 5]));

            return $client;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
