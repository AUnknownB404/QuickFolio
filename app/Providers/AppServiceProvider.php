<?php

namespace App\Providers;

use App\Database\Connectors\NeonPostgresConnector;
use App\Models\Portfolio;
use App\Policies\PortfolioPolicy;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\PostgresConnection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
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
        DB::extend('pgsql', function (array $config, string $name): PostgresConnection {
            $config['name'] = $name;
            $connector = new NeonPostgresConnector;

            return new PostgresConnection(
                fn () => $connector->connect($config),
                $config['database'],
                $config['prefix'],
                $config
            );
        });

        Gate::policy(Portfolio::class, PortfolioPolicy::class);

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Verify your quickFolio email address')
                ->view('emails.verify-email', [
                    'recipientName' => $notifiable->name ?? null,
                    'url' => $url,
                ]);
        });
    }
}
