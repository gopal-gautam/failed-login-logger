<?php

namespace GG\FailedLoginLogger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedLoginAttempt extends Model
{
    use MassPrunable;

    protected $fillable = [
        'user_id',
        'email_address',
        'ip_address',
        'user_agent',
        'guard',
    ];

    protected $casts = [
        'user_id'    => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('failed-login-logger.table', 'failed_login_attempts');
    }

    /**
     * Persist a failed login attempt. Accepts an Authenticatable instance, a
     * model with a primary key, or null when the identifier did not match a
     * known user.
     */
    public static function record(
        $user,
        ?string $email,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $guard = null
    ): self {
        return static::create([
            'user_id'       => $user ? (method_exists($user, 'getKey') ? $user->getKey() : ($user->id ?? null)) : null,
            'email_address' => $email,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
            'guard'         => $guard,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model', 'App\\Models\\User'),
            'user_id'
        );
    }

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email_address', $email);
    }

    public function scopeFromIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip_address', $ip);
    }

    public function scopeRecent(Builder $query, int $minutes = 60): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Records eligible for `model:prune`. Returns an empty query when pruning
     * is disabled in config so nothing is removed accidentally.
     */
    public function prunable(): Builder
    {
        if (! config('failed-login-logger.pruning.enabled', false)) {
            return static::query()->whereRaw('1 = 0');
        }

        $days = max(1, (int) config('failed-login-logger.pruning.retention_days', 30));

        return static::query()->where('created_at', '<=', now()->subDays($days));
    }
}
