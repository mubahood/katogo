<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('firebase', function ($app) {
            $credentialsPath = config('firebase.credentials.file');
            
            if (!file_exists($credentialsPath)) {
                throw new \Exception("Firebase credentials file not found at: {$credentialsPath}");
            }

            $factory = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->withProjectId(config('firebase.project_id'));

            return $factory;
        });

        $this->app->singleton('firebase.storage', function ($app) {
            return $app['firebase']->createStorage();
        });

        $this->app->singleton('firebase.database', function ($app) {
            return $app['firebase']->createDatabase();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}