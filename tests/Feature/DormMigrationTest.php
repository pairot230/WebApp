<?php

use Illuminate\Support\Facades\Schema;

test('dorm migration can roll back and run again on an empty database', function () {
    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    expect(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'room_id'))->toBeTrue();
    $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertExitCode(0);
    expect(Schema::hasTable('audit_logs'))->toBeTrue()
        ->and(Schema::hasColumn('audit_logs', 'actor_snapshot'))->toBeFalse();
    $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertExitCode(0);
    expect(Schema::hasTable('audit_logs'))->toBeFalse()
        ->and(Schema::hasTable('rooms'))->toBeFalse()
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'room_id'))->toBeFalse();
    $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    expect(Schema::hasTable('attendances'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'room_id'))->toBeTrue();
});
