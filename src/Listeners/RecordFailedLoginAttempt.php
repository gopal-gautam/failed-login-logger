<?php

namespace GG\FailedLoginLogger\Listeners;

use GG\FailedLoginLogger\Models\FailedLoginAttempt;
use Illuminate\Auth\Events\Failed;
use Throwable;

class RecordFailedLoginAttempt
{
    public function handle(Failed $event): void
    {
        if (! config('failed-login-logger.enabled', true)) {
            return;
        }

        try {
            /** @var class-string<FailedLoginAttempt> $modelClass */
            $modelClass = config('failed-login-logger.model', FailedLoginAttempt::class);

            $attributes = [
                'email_address' => $this->resolveIdentifier($event->credentials ?? []),
                'ip_address'    => $this->captureIp(),
                'user_agent'    => $this->captureUserAgent(),
                'guard'         => $this->captureGuard($event->guard ?? null),
            ];

            if (config('failed-login-logger.queue.enabled', false)) {
                dispatch(function () use ($modelClass, $event, $attributes) {
                    $modelClass::record(
                        $event->user,
                        $attributes['email_address'],
                        $attributes['ip_address'],
                        $attributes['user_agent'],
                        $attributes['guard']
                    );
                })->onConnection(config('failed-login-logger.queue.connection'))
                  ->onQueue(config('failed-login-logger.queue.queue'))
                  ->afterResponse();

                return;
            }

            $modelClass::record(
                $event->user,
                $attributes['email_address'],
                $attributes['ip_address'],
                $attributes['user_agent'],
                $attributes['guard']
            );
        } catch (Throwable $e) {
            // Never break the login flow because of a logging failure.
            report($e);
        }
    }

    protected function resolveIdentifier(array $credentials): ?string
    {
        $keys = (array) config('failed-login-logger.identifier_keys', ['email', 'username']);

        foreach ($keys as $key) {
            if (isset($credentials[$key]) && is_string($credentials[$key]) && $credentials[$key] !== '') {
                return mb_substr($credentials[$key], 0, 255);
            }
        }

        return null;
    }

    protected function captureIp(): ?string
    {
        if (! config('failed-login-logger.capture.ip', true)) {
            return null;
        }

        try {
            return app('request')->ip();
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function captureUserAgent(): ?string
    {
        if (! config('failed-login-logger.capture.user_agent', true)) {
            return null;
        }

        try {
            $ua = app('request')->userAgent();
        } catch (Throwable $e) {
            return null;
        }

        return $ua ? mb_substr($ua, 0, 1024) : null;
    }

    protected function captureGuard(?string $guard): ?string
    {
        if (! config('failed-login-logger.capture.guard', true)) {
            return null;
        }

        return $guard;
    }
}
