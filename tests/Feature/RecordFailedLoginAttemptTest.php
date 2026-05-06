<?php

namespace GG\FailedLoginLogger\Tests\Feature;

use GG\FailedLoginLogger\Models\FailedLoginAttempt;
use GG\FailedLoginLogger\Tests\TestCase;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Auth\User as Authenticatable;

class RecordFailedLoginAttemptTest extends TestCase
{
    /** @test */
    public function it_records_a_failed_login_attempt_from_credentials(): void
    {
        event(new Failed('web', null, ['email' => 'foo@example.com', 'password' => 'secret']));

        $this->assertSame(1, FailedLoginAttempt::query()->count());

        $row = FailedLoginAttempt::query()->first();
        $this->assertSame('foo@example.com', $row->email_address);
        $this->assertSame('web', $row->guard);
        $this->assertNull($row->user_id);
    }

    /** @test */
    public function it_falls_back_to_username_when_email_is_missing(): void
    {
        event(new Failed('web', null, ['username' => 'jdoe', 'password' => 'secret']));

        $this->assertDatabaseHas(
            config('failed-login-logger.table'),
            ['email_address' => 'jdoe']
        );
    }

    /** @test */
    public function it_records_the_user_id_when_user_is_known(): void
    {
        $user = new class extends Authenticatable {
            public $id = 42;
            public function getKey() { return $this->id; }
        };

        event(new Failed('web', $user, ['email' => 'me@example.com']));

        $this->assertDatabaseHas(
            config('failed-login-logger.table'),
            ['email_address' => 'me@example.com', 'user_id' => 42]
        );
    }

    /** @test */
    public function it_does_not_record_when_logging_is_disabled(): void
    {
        config()->set('failed-login-logger.enabled', false);

        event(new Failed('web', null, ['email' => 'foo@example.com']));

        $this->assertSame(0, FailedLoginAttempt::query()->count());
    }

    /** @test */
    public function it_truncates_an_oversized_identifier(): void
    {
        $long = str_repeat('a', 500);

        event(new Failed('web', null, ['email' => $long]));

        $row = FailedLoginAttempt::query()->first();
        $this->assertSame(255, mb_strlen($row->email_address));
    }

    /** @test */
    public function it_swallows_listener_failures_so_login_flow_is_not_broken(): void
    {
        config()->set('failed-login-logger.model', \stdClass::class);

        // Should not throw despite the model being invalid.
        event(new Failed('web', null, ['email' => 'foo@example.com']));

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_capture_the_user_agent_from_the_request(): void
    {
        request()->headers->set('User-Agent', 'PHPUnit-Test-Agent');

        event(new Failed('web', null, ['email' => 'foo@example.com']));

        $row = FailedLoginAttempt::query()->first();
        $this->assertSame('PHPUnit-Test-Agent', $row->user_agent);
    }

    /** @test */
    public function it_skips_user_agent_capture_when_disabled(): void
    {
        config()->set('failed-login-logger.capture.user_agent', false);
        request()->headers->set('User-Agent', 'PHPUnit-Test-Agent');

        event(new Failed('web', null, ['email' => 'foo@example.com']));

        $this->assertNull(FailedLoginAttempt::query()->first()->user_agent);
    }

    /** @test */
    public function the_recent_scope_filters_by_minutes(): void
    {
        FailedLoginAttempt::create([
            'email_address' => 'old@example.com',
            'created_at'    => now()->subHours(2),
            'updated_at'    => now()->subHours(2),
        ]);
        FailedLoginAttempt::create([
            'email_address' => 'new@example.com',
            'created_at'    => now()->subMinutes(5),
            'updated_at'    => now()->subMinutes(5),
        ]);

        $this->assertSame(1, FailedLoginAttempt::recent(30)->count());
    }
}
