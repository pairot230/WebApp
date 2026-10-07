<?php

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Dorm;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('README fresh database bootstrap creates one passwordless superadmin without requiring demo data', function () {
    config(['dorm.academic_year' => 2569, 'dorm.superadmin_email' => 'pairoj.c@kkumail.com']);
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(0)->and(Dorm::count())->toBe(0)->and(AcademicYear::count())->toBe(1);
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $user = User::firstOrNew(['email' => strtolower(trim(config('dorm.superadmin_email')))]);
        if (! $user->exists) {
            $user->name = 'ผู้ดูแลระบบ';
            $user->password = null;
            $user->is_active = true;
            $user->save();
        }
        $this->seed(SuperAdminSeeder::class);
        $this->seed(DatabaseSeeder::class);
    }
    $user = User::firstOrFail();
    expect(User::count())->toBe(1)->and($user->role)->toBe('superadmin')
        ->and($user->password)->toBeNull()->and($user->dorm_id)->toBeNull()
        ->and($user->google_id)->toBeNull()->and(AuditLog::count())->toBe(1)
        ->and(AcademicYear::count())->toBe(1);
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertViewIs('dashboards.superadmin');
    $this->get('/admin/dorms/create')->assertOk();
    $this->post('/admin/dorms', ['code' => 'H8', 'name' => 'หอพัก 8', 'is_active' => true])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(Dorm::count())->toBe(1);
});
