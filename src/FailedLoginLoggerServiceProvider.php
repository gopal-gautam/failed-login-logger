<?php

namespace GG\FailedLoginLogger;

use GG\FailedLoginLogger\Listeners\RecordFailedLoginAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class FailedLoginLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/failed-login-logger.php',
            'failed-login-logger'
        );
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(Failed::class, RecordFailedLoginAttempt::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/failed-login-logger.php' => config_path('failed-login-logger.php'),
            ], 'failed-login-logger-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_failed_login_attempts_table.php.stub' => $this->migrationTarget(),
            ], 'failed-login-logger-migrations');
        }
    }

    /**
     * Resolve the publish target for the migration. If a previously-published
     * migration already exists we re-use the same path so that
     * `vendor:publish --force` overwrites in place rather than creating a
     * second timestamped copy.
     */
    protected function migrationTarget(): string
    {
        $existing = glob(database_path('migrations/*_create_failed_login_attempts_table.php'));

        if (! empty($existing)) {
            return $existing[0];
        }

        return database_path('migrations/' . date('Y_m_d_His') . '_create_failed_login_attempts_table.php');
    }
}
