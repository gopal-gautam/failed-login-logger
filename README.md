# Failed Login Logger for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gopal-gautam/failed-login-logger.svg?style=flat-square)](https://packagist.org/packages/gopal-gautam/failed-login-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/gopal-gautam/failed-login-logger.svg?style=flat-square)](https://packagist.org/packages/gopal-gautam/failed-login-logger)
[![License](https://img.shields.io/packagist/l/gopal-gautam/failed-login-logger.svg?style=flat-square)](LICENSE)

Persist every failed authentication attempt in your Laravel application to the
database for auditing, brute-force detection and incident response. The package
listens to Laravel's built-in `Illuminate\Auth\Events\Failed` event so it works
with the standard `web` guard, custom guards, Sanctum, Fortify, Jetstream,
Breeze and any code path that goes through Laravel's authentication
infrastructure.

## Features

- Listens to `Illuminate\Auth\Events\Failed` automatically &mdash; no controller
  changes needed.
- Captures the supplied identifier (email, username, …), IP address, user
  agent, guard name and the matching `user_id` when the user exists.
- Configurable: change the table name, the model, the captured fields, the
  credential keys to inspect, and queue settings without forking the package.
- Optional asynchronous logging via Laravel queues.
- Built-in `MassPrunable` integration with a configurable retention window.
- Listener swallows synchronous failures (and reports them via `report()`) so a
  misconfigured logger can never break the login flow.
- Compatible with Laravel 9 &ndash; 12 and PHP 8.0+.

## Installation

```bash
composer require gopal-gautam/failed-login-logger
```

The service provider is auto-discovered, so no manual registration is needed.

### Publish the migration and run it

```bash
php artisan vendor:publish --tag=failed-login-logger-migrations
php artisan migrate
```

### Publish the config (optional)

```bash
php artisan vendor:publish --tag=failed-login-logger-config
```

This drops `config/failed-login-logger.php` into your application where every
option is documented inline.

## Usage

Once installed and migrated the package starts recording failed logins
automatically. Inspect them with the Eloquent model:

```php
use GG\FailedLoginLogger\Models\FailedLoginAttempt;

// Recent attempts (last 60 minutes)
FailedLoginAttempt::recent()->latest()->get();

// Attempts for a specific email or IP
FailedLoginAttempt::forEmail('alice@example.com')->count();
FailedLoginAttempt::fromIp('203.0.113.4')->recent(15)->count();

// Resolve the related user, when the identifier matched a known account
FailedLoginAttempt::with('user')->latest()->limit(20)->get();
```

### Throttling / brute-force detection example

```php
$count = FailedLoginAttempt::fromIp(request()->ip())->recent(15)->count();

if ($count >= 10) {
    abort(429, 'Too many failed login attempts. Please try again later.');
}
```

## Configuration

Every option is in `config/failed-login-logger.php` after publishing:

| Key                         | Default                          | Description                                                           |
| --------------------------- | -------------------------------- | --------------------------------------------------------------------- |
| `enabled`                   | `true`                           | Master switch. Set to `false` to skip recording without uninstalling. |
| `table`                     | `failed_login_attempts`          | Database table name.                                                  |
| `model`                     | `FailedLoginAttempt::class`      | Eloquent model. Replace with your own subclass to add behaviour.      |
| `capture.ip`                | `true`                           | Whether to record the client IP address.                              |
| `capture.user_agent`        | `true`                           | Whether to record the request `User-Agent`.                           |
| `capture.guard`             | `true`                           | Whether to record the auth guard that emitted the event.              |
| `identifier_keys`           | `['email', 'username', 'name']`  | Ordered list of credential keys searched for the identifier.          |
| `queue.enabled`             | `false`                          | Run the listener on a queue instead of synchronously.                 |
| `queue.connection` / `queue.queue` | `null` / `null`            | Override the connection / queue name when queuing.                    |
| `pruning.enabled`           | `false`                          | Allow `model:prune` to delete old rows.                               |
| `pruning.retention_days`    | `30`                             | How long to keep records when pruning is enabled.                     |

Each option can also be controlled via environment variables &mdash; see the
`env(...)` calls in the published config file.

### Asynchronous logging

To move the database write off the request lifecycle:

```env
FAILED_LOGIN_LOGGER_QUEUE=true
FAILED_LOGIN_LOGGER_QUEUE_CONNECTION=redis
FAILED_LOGIN_LOGGER_QUEUE_NAME=auth
```

When `queue.enabled` is true the listener dispatches the database write as a
queued closure with `afterResponse()`, so the response is sent first and the
write happens on a worker (or after the request completes when the queue is
`sync`). The listener itself is *not* `ShouldQueue` &mdash; Laravel 12's
dispatcher silently drops `ShouldQueue` listeners whose `shouldQueue()`
returns false, so we dispatch a separate job from a regular listener instead.
This works across Laravel 9&ndash;12.

### Pruning old records

The model uses Laravel's `MassPrunable` trait. After enabling pruning in the
config, schedule the prune command (e.g. in `routes/console.php` on Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [\GG\FailedLoginLogger\Models\FailedLoginAttempt::class],
])->daily();
```

### Replacing the model

```php
// app/Models/FailedLoginAttempt.php
namespace App\Models;

use GG\FailedLoginLogger\Models\FailedLoginAttempt as BaseAttempt;

class FailedLoginAttempt extends BaseAttempt
{
    protected $appends = ['country'];

    public function getCountryAttribute(): ?string
    {
        return geoip()->getLocation($this->ip_address)->iso_code ?? null;
    }
}
```

```php
// config/failed-login-logger.php
'model' => App\Models\FailedLoginAttempt::class,
```

## Schema

The published migration creates a table with the following columns:

| Column          | Type                                | Notes                                |
| --------------- | ----------------------------------- | ------------------------------------ |
| `id`            | `bigIncrements`                     | Primary key.                         |
| `user_id`       | `unsignedBigInteger`, nullable      | Indexed. Set when identifier matches a user. |
| `email_address` | `string`, nullable                  | Indexed. The identifier supplied at login. |
| `ip_address`    | `string(45)`, nullable              | Indexed. IPv4 or IPv6.               |
| `user_agent`    | `string(1024)`, nullable            | Truncated to 1024 chars.             |
| `guard`         | `string(64)`, nullable              | Auth guard that emitted the event.   |
| `created_at`    | `timestamp`                         | Indexed for time-window queries.     |
| `updated_at`    | `timestamp`                         |                                      |

No foreign key is added to `users` so the table can coexist with non-standard
user schemas. Add one in your own migration if you need cascade behaviour.

## Behind a proxy / load balancer

`request()->ip()` returns the address of the immediate client. If your
application sits behind a reverse proxy or load balancer, configure Laravel's
`TrustProxies` middleware so that the `X-Forwarded-For` header is honoured.
The package will then automatically capture the real client IP.

## Testing

```bash
composer install
composer test
```

The suite uses Orchestra Testbench against an in-memory SQLite database.


## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## License

Released under the [MIT License](LICENSE).
