# Changelog

All notable changes to `gopal-gautam/failed-login-logger` will be documented in
this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-05-06

### Added
- Standard Laravel-package directory layout (`composer.json` at the package root).
- Publishable `config/failed-login-logger.php` for full customisation.
- Configurable model, table, identifier-credential keys, captured fields and queue settings.
- Optional asynchronous logging — when `queue.enabled` is true the database write is dispatched as a queued closure with `afterResponse()`, so it runs off the request lifecycle. The listener itself is **not** `ShouldQueue` because Laravel 12's dispatcher silently drops `ShouldQueue` listeners whose `shouldQueue()` returns false (no synchronous fallback). Dispatching from inside a regular listener avoids that pitfall and works across Laravel 9–12.
- Capture of `user_agent` and `guard` in addition to email and IP.
- Eloquent scopes: `forEmail`, `fromIp`, `recent`.
- `MassPrunable` integration with a configurable retention window.
- Listener swallows synchronous failures (and reports them) so a misconfigured
  logger can never block the login flow; queued failures are re-thrown so the
  worker can retry.
- Migration uses an anonymous class, indexes `user_id`, `email_address`,
  `ip_address` and `created_at`, and reads the table name from config.
- Test suite based on Orchestra Testbench.
- `LICENSE`, `CHANGELOG.md`, `.gitattributes` and `.gitignore`.

### Changed
- Compatible with Laravel 9, 10, 11 and 12 (PHP 8.0+).
- Service provider now registers the listener directly via the dispatcher
  instead of through a separate `EventServiceProvider`.
- Migration is published with a single timestamp; re-publishing reuses the
  existing file rather than creating a new one.

### Removed
- Bundled `composer.lock` (libraries should not commit lock files).
- Redundant `FailedLoginLoggerEventServiceProvider` class.
