<?php

namespace GG\FailedLoginLogger\Tests;

use GG\FailedLoginLogger\FailedLoginLoggerServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createFailedLoginAttemptsTable();
    }

    protected function getPackageProviders($app): array
    {
        return [
            FailedLoginLoggerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function createFailedLoginAttemptsTable(): void
    {
        $table = config('failed-login-logger.table', 'failed_login_attempts');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email_address')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 1024)->nullable();
            $table->string('guard', 64)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }
}
