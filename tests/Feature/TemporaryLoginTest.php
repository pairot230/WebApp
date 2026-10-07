<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\TemporaryMemberSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['dorm.temporary_login' => true]);
    $this->withoutMiddleware(PreventRequestForgery::class);
});

test('temporary seed creates the requested members and is idempotent', function () {
    $this->seed(TemporaryMemberSeeder::class);
    $this->seed(TemporaryMemberSeeder::class);
    expect(User::count())->toBe(5)->and(AuditLog::count())->toBe(5);
    foreach (config('dorm.temporary_members') as $member) {
        $user = User::where('email', $member['email'])->firstOrFail();
        expect($user->role)->toBe($member['role'])->and($user->room->number)->toBe($member['room']);
    }
});

test('temporary entry remains disabled in production and when switched off', function () {
    $this->app->instance('env', 'production');
    $this->post('/auth/temporary', ['email' => 'pairoj.c@kkumail.com'])->assertNotFound();
    $this->get('/login')->assertViewIs('auth.login');
    $this->app->instance('env', 'local');
    config(['dorm.temporary_login' => false]);
    $this->post('/auth/temporary', ['email' => 'pairoj.c@kkumail.com'])->assertNotFound();
});

test('each temporary member enters with database permissions and can logout', function () {
    $this->seed(TemporaryMemberSeeder::class);
    $this->app->instance('env', 'local');
    $this->get('/login')->assertViewIs('auth.temporary-login')->assertSee('pairoj.c@kkumail.com');
    foreach (config('dorm.temporary_members') as $member) {
        $this->post('/auth/temporary', ['email' => $member['email']])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', $member['email'])->firstOrFail());
        $this->get('/dashboard')->assertOk();
        $response = $this->get('/superadmin/admins');
        if ($member['role'] === 'superadmin') {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
        $response = $this->get('/admin/users');
        if ($member['role'] === 'user') {
            $response->assertForbidden();
        } else {
            $response->assertOk();
        }
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
});

test('temporary entry rejects outsiders inactive members and supplied roles', function () {
    $this->seed(TemporaryMemberSeeder::class);
    $this->app->instance('env', 'local');
    $this->postJson('/auth/temporary', ['email' => 'outsider@kkumail.com'])->assertUnprocessable();
    $this->postJson('/auth/temporary', ['email' => 'tharm.a@kkumail.com', 'role' => 'superadmin'])->assertUnprocessable();
    User::where('email', 'tharm.a@kkumail.com')->update(['is_active' => false]);
    $this->post('/auth/temporary', ['email' => 'tharm.a@kkumail.com'])->assertForbidden();
    $this->assertGuest();
});
