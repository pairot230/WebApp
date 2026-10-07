<?php

use App\Models\AuditLog;
use App\Models\Dorm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function authorizationMember(string $role = 'user', ?Dorm $dorm = null): User
{
    return User::factory()->create(['role' => $role, 'dorm_id' => $dorm?->id]);
}

test('guests cannot access protected URLs or change roles', function () {
    $member = authorizationMember();
    foreach (['/dashboard', '/me', '/admin/users', '/admin/users/'.$member->id, '/superadmin/admins'] as $url) {
        $this->get($url)->assertRedirect(route('login'));
        $this->getJson($url)->assertUnauthorized();
    }
    $this->patchJson('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertUnauthorized();
    expect($member->fresh()->role)->toBe('user');
});

test('superadmin accesses all members and can appoint and remove admin', function () {
    $dorm = Dorm::create(['code' => 'A', 'name' => 'Dorm A']);
    $superadmin = authorizationMember('superadmin');
    $member = authorizationMember('user', $dorm);
    $this->actingAs($superadmin)->get('/admin/users')->assertOk()->assertSee($member->name);
    $this->get('/admin/users/'.$member->id)->assertOk();
    $this->get('/superadmin/admins')->assertOk();
    $this->patch('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertRedirect(route('superadmin.admins.index'));
    expect($member->fresh()->role)->toBe('admin');
    $this->patch('/superadmin/users/'.$member->id.'/role', ['role' => 'user'])->assertRedirect(route('superadmin.admins.index'));
    expect($member->fresh()->role)->toBe('user')->and(AuditLog::count())->toBe(2);
    $audit = AuditLog::latest('id')->first();
    expect($audit->actor_id)->toBe($superadmin->id)
        ->and($audit->old_values)->toBe(['role' => 'admin'])
        ->and($audit->new_values)->toBe(['role' => 'user']);
});

test('admin can access ordinary members only within their dorm', function () {
    $dorm = Dorm::create(['code' => 'A', 'name' => 'Dorm A']);
    $otherDorm = Dorm::create(['code' => 'B', 'name' => 'Dorm B']);
    $admin = authorizationMember('admin', $dorm);
    $member = authorizationMember('user', $dorm);
    $outsider = authorizationMember('user', $otherDorm);
    $superadmin = authorizationMember('superadmin', $dorm);
    $otherAdmin = authorizationMember('admin', $dorm);
    $this->actingAs($admin)->get('/admin/users')->assertOk()
        ->assertSee($member->name)->assertDontSee($outsider->name)
        ->assertDontSee($superadmin->name)->assertDontSee($otherAdmin->name);
    $this->get('/admin/users/'.$member->id)->assertOk();
    foreach ([$outsider, $superadmin, $otherAdmin] as $forbidden) {
        $this->get('/admin/users/'.$forbidden->id)->assertForbidden();
        expect(Gate::forUser($admin)->allows('update', $forbidden))->toBeFalse()
            ->and(Gate::forUser($admin)->allows('delete', $forbidden))->toBeFalse();
    }
    expect(Gate::forUser($admin)->allows('update', $member))->toBeTrue();
});

test('admin cannot promote demote or manage superadmin through direct URLs', function () {
    $dorm = Dorm::create(['code' => 'A', 'name' => 'Dorm A']);
    $admin = authorizationMember('admin', $dorm);
    $member = authorizationMember('user', $dorm);
    $otherAdmin = authorizationMember('admin', $dorm);
    $superadmin = authorizationMember('superadmin');
    $this->actingAs($admin)->get('/superadmin/admins')->assertForbidden();
    foreach ([[$member, 'admin'], [$otherAdmin, 'user'], [$superadmin, 'user']] as [$target, $role]) {
        $this->patchJson('/superadmin/users/'.$target->id.'/role', ['role' => $role])->assertForbidden();
    }
    expect($member->fresh()->role)->toBe('user')
        ->and($otherAdmin->fresh()->role)->toBe('admin')
        ->and($superadmin->fresh()->role)->toBe('superadmin')
        ->and(AuditLog::count())->toBe(0);
});

test('user sees only own profile and cannot override identity via request input', function () {
    $member = authorizationMember();
    $other = authorizationMember();
    $this->actingAs($member)->get('/me?user_id='.$other->id.'&role=superadmin')->assertOk()
        ->assertSee($member->email)->assertDontSee($other->email);
    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/users/'.$other->id)->assertForbidden();
    $this->get('/superadmin/admins')->assertForbidden();
    $this->patchJson('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertForbidden();
    expect(Gate::forUser($member)->allows('view', $member))->toBeTrue()
        ->and(Gate::forUser($member)->allows('view', $other))->toBeFalse()
        ->and(Gate::forUser($member)->allows('update', $other))->toBeFalse();
});

test('unknown role and unassigned admin fail closed', function () {
    $unknown = authorizationMember('unexpected');
    $this->actingAs($unknown)->get('/dashboard')->assertForbidden();
    $this->get('/me')->assertForbidden();
    $this->get('/admin/users')->assertForbidden();
    $admin = authorizationMember('admin');
    $this->actingAs($admin)->get('/admin/users')->assertForbidden();
});

test('role changes take effect on next request without trusting stale session data', function () {
    $dorm = Dorm::create(['code' => 'A', 'name' => 'Dorm A']);
    $admin = authorizationMember('admin', $dorm);
    $this->actingAs($admin)->withSession(['role' => 'superadmin'])->get('/admin/users')->assertOk();
    User::whereKey($admin->id)->update(['role' => 'user']);
    $this->get('/admin/users')->assertForbidden();
    $this->get('/superadmin/admins')->assertForbidden();
});

test('superadmin role cannot be assigned removed or changed by the role endpoint', function () {
    $superadmin = authorizationMember('superadmin');
    $otherSuperadmin = authorizationMember('superadmin');
    $member = authorizationMember();
    $this->actingAs($superadmin);
    foreach ([$superadmin, $otherSuperadmin] as $target) {
        $this->patchJson('/superadmin/users/'.$target->id.'/role', ['role' => 'user'])->assertForbidden();
    }
    $this->patchJson('/superadmin/users/'.$member->id.'/role', ['role' => 'superadmin'])->assertUnprocessable();
    expect($member->fresh()->role)->toBe('user')->and(AuditLog::count())->toBe(0);
});

test('promotion requires active member with dorm and no-op changes add no audit', function () {
    $dorm = Dorm::create(['code' => 'A', 'name' => 'Dorm A']);
    $superadmin = authorizationMember('superadmin');
    $unassigned = authorizationMember();
    $inactive = authorizationMember('user', $dorm);
    $inactive->is_active = false;
    $inactive->save();
    $this->actingAs($superadmin);
    foreach ([$unassigned, $inactive] as $member) {
        $this->patchJson('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertUnprocessable();
        expect($member->fresh()->role)->toBe('user');
    }
    $this->patch('/superadmin/users/'.$unassigned->id.'/role', ['role' => 'user'])->assertRedirect();
    expect(AuditLog::count())->toBe(0);
});

test('audit failure rolls back role changes', function () {
    $dorm = Dorm::create(['code' => 'A', 'name' => 'Dorm A']);
    $superadmin = authorizationMember('superadmin');
    $member = authorizationMember('user', $dorm);
    AuditLog::creating(function (): void {
        throw new RuntimeException('Test audit failure');
    });
    try {
        $this->actingAs($superadmin)->patch('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertStatus(500);
        expect($member->fresh()->role)->toBe('user');
    } finally {
        AuditLog::flushEventListeners();
    }
});

test('inactive sessions and forged session roles cannot access admin pages', function () {
    $member = authorizationMember();
    $this->actingAs($member)->withSession(['role' => 'superadmin'])->get('/admin/users')->assertForbidden();
    User::whereKey($member->id)->update(['is_active' => false]);
    $this->get('/me')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('role endpoint requires CSRF even for superadmin', function () {
    $superadmin = authorizationMember('superadmin');
    $member = authorizationMember();
    $this->app['env'] = 'production';
    $this->actingAs($superadmin)->patchJson('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertStatus(419);
    expect($member->fresh()->role)->toBe('user');
});
