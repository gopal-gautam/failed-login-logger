<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Logging
    |--------------------------------------------------------------------------
    |
    | When set to false, the package's listener becomes a no-op. Useful for
    | turning logging off in specific environments without uninstalling.
    |
    */

    'enabled' => env('FAILED_LOGIN_LOGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Table
    |--------------------------------------------------------------------------
    |
    | Name of the table that stores failed login attempts. Change before the
    | first migration runs, or rename the table separately afterward.
    |
    */

    'table' => env('FAILED_LOGIN_LOGGER_TABLE', 'failed_login_attempts'),

    /*
    |--------------------------------------------------------------------------
    | Eloquent Model
    |--------------------------------------------------------------------------
    |
    | Replace this with your own subclass of FailedLoginAttempt to add fields,
    | relations or behavior without forking the package.
    |
    */

    'model' => GG\FailedLoginLogger\Models\FailedLoginAttempt::class,

    /*
    |--------------------------------------------------------------------------
    | Captured Fields
    |--------------------------------------------------------------------------
    |
    | Toggle which optional fields are captured per attempt. Disabling these
    | reduces row size and avoids storing PII you don't need.
    |
    */

    'capture' => [
        'ip'         => true,
        'user_agent' => true,
        'guard'      => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Identifier Credential Keys
    |--------------------------------------------------------------------------
    |
    | The listener looks for the user-supplied identifier in this order and
    | stores the first non-empty string match in the `email_address` column.
    | Add `username`, `phone`, etc. if your auth flow uses different keys.
    |
    */

    'identifier_keys' => ['email', 'username', 'name'],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | When `enabled` is true the listener is dispatched onto a queue, removing
    | the database write from the request lifecycle. Leave `connection` /
    | `queue` null to use your application defaults.
    |
    */

    'queue' => [
        'enabled'    => env('FAILED_LOGIN_LOGGER_QUEUE', false),
        'connection' => env('FAILED_LOGIN_LOGGER_QUEUE_CONNECTION'),
        'queue'      => env('FAILED_LOGIN_LOGGER_QUEUE_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Old attempts can be pruned via Laravel's `model:prune` command. Set
    | `enabled` to true and adjust `retention_days` to taste, then schedule
    | `model:prune` in your console kernel / routes/console.php.
    |
    */

    'pruning' => [
        'enabled'        => env('FAILED_LOGIN_LOGGER_PRUNE', false),
        'retention_days' => (int) env('FAILED_LOGIN_LOGGER_RETENTION_DAYS', 30),
    ],

];
