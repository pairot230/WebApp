<?php

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Dorm;
use App\Models\FinancialTransaction;
use App\Models\RepairRequest;
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function auditRecord(User $actor, string $action = 'score.adjusted', array $extra = []): AuditLog
{
    $log = new AuditLog;
    $log->fill(['actor_id' => $actor->id, 'action' => $action, 'target_type' => User::class, 'target_id' => $actor->id, 'old_values' => ['value' => 1], 'new_values' => ['value' => 2], ...$extra]);
    $log->save();

    return $log;
}

test('only active superadmin can view audit lists details and policy even through direct URL', function () {
    $super = User::factory()->create(['role' => 'superadmin']);
    $log = auditRecord($super);
    $this->get('/superadmin/audit-logs')->assertRedirect(route('login'));
    $this->getJson('/superadmin/audit-logs/'.$log->id)->assertUnauthorized();
    foreach (['admin', 'user', 'unknown'] as $role) {
        $actor = User::factory()->create(['role' => $role]);
        $this->actingAs($actor)->withSession(['role' => 'superadmin'])->get('/superadmin/audit-logs')->assertForbidden();
        $this->get('/superadmin/audit-logs/'.$log->id)->assertForbidden();
        expect(Gate::forUser($actor)->allows('view', $log))->toBeFalse();
    }
    $this->actingAs($super)->get('/superadmin/audit-logs')->assertOk()->assertSee('Audit Log');
    $this->get('/superadmin/audit-logs/'.$log->id)->assertOk()->assertSee('Old Data')->assertSee('New Data');
    $this->get('/superadmin/audit-logs/99999')->assertNotFound();
    User::whereKey($super->id)->update(['role' => 'admin']);
    $this->get('/superadmin/audit-logs')->assertForbidden();
    User::whereKey($super->id)->update(['is_active' => false]);
    $this->get('/superadmin/audit-logs')->assertRedirect(route('login'));
});

test('audit retains actor name and identity after rename and deletion', function () {
    $viewer = User::factory()->create(['role' => 'superadmin']);
    $actor = User::factory()->create(['name' => 'Historical Actor']);
    $log = auditRecord($actor);
    $id = $actor->id;
    $actor->update(['name' => 'Renamed Actor']);
    expect($log->fresh()->actor_snapshot)->toBe(['id' => $id, 'name' => 'Historical Actor']);
    $actor->delete();
    expect($log->fresh()->actor_id)->toBeNull()->and($log->fresh()->actor_snapshot['id'])->toBe($id);
    $this->actingAs($viewer)->get('/superadmin/audit-logs/'.$log->id)->assertOk()->assertSee('Historical Actor')->assertDontSee('Renamed Actor');
});

test('audit records cannot be modified or deleted via model or HTTP', function () {
    $super = User::factory()->create(['role' => 'superadmin']);
    $log = auditRecord($super);
    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class);
    expect(fn () => $log->fresh()->delete())->toThrow(LogicException::class);
    $this->actingAs($super)->patchJson('/superadmin/audit-logs/'.$log->id, ['action' => 'tampered'])->assertStatus(405);
    $this->deleteJson('/superadmin/audit-logs/'.$log->id)->assertStatus(405);
    $this->postJson('/superadmin/audit-logs', ['action' => 'forged'])->assertStatus(405);
    expect($log->fresh()->action)->toBe('score.adjusted');
});

test('audit sanitizes credentials recursively and escapes legacy or submitted text', function () {
    $super = User::factory()->create(['role' => 'superadmin']);
    $log = auditRecord($super, '<script>alert(1)</script>', [
        'old_values' => ['password' => 'secret-password', 'nested' => ['qr_token' => 'private-qr']],
        'new_values' => ['credential' => 'google-token', 'text' => '<img src=x onerror=alert(2)>'],
    ]);
    expect($log->fresh()->old_values['password'])->toBe('[REDACTED]')->and($log->fresh()->old_values['nested']['qr_token'])->toBe('[REDACTED]');
    $this->actingAs($super)->get('/superadmin/audit-logs/'.$log->id)->assertOk()
        ->assertDontSee('secret-password')->assertDontSee('private-qr')->assertDontSee('google-token')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror=alert(2)>', false);
});

test('audit filters actor action target and inclusive Bangkok dates with pagination', function () {
    $super = User::factory()->create(['role' => 'superadmin']);
    $peer = User::factory()->create();
    $log = new AuditLog;
    $log->fill(['actor_id' => $super->id, 'action' => 'finance.updated', 'target_type' => FinancialTransaction::class, 'target_id' => 99]);
    $log->created_at = '2026-10-07 17:30:00';
    $log->save();
    auditRecord($peer, 'complaint.updated');
    $query = http_build_query(['actor_id' => $super->id, 'action' => 'finance.updated', 'target_type' => FinancialTransaction::class, 'target_id' => 99, 'date_from' => '2026-10-08', 'date_to' => '2026-10-08']);
    $this->actingAs($super)->get('/superadmin/audit-logs?'.$query)->assertOk()
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 1 && $logs->first()->id === $log->id)->assertSee('08/10/2026 00:30:00');
    $this->get('/superadmin/audit-logs?date_to=2026-10-07')->assertOk()
        ->assertViewHas('logs', fn ($logs) => ! $logs->contains('id', $log->id));
    $this->getJson('/superadmin/audit-logs?date_from=2026-10-08&date_to=2026-10-07')->assertUnprocessable();
    $this->getJson('/superadmin/audit-logs?target_type=Unknown')->assertUnprocessable();
    for ($index = 0; $index < 26; $index++) {
        auditRecord($super, 'finance.updated');
    }
    $this->get('/superadmin/audit-logs?action=finance.updated&page=2')->assertOk()
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 2);
});

test('all required business changes log actor target time and old new data atomically', function () {
    $dorm = Dorm::create(['code' => 'H8', 'name' => 'Dorm 8']);
    $year = AcademicYear::create(['year' => 2569]);
    $super = User::factory()->create(['role' => 'superadmin']);
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $member = User::factory()->create(['dorm_id' => $dorm->id]);
    $complaint = Complaint::create(['user_id' => $member->id, 'dorm_id' => $dorm->id, 'title' => 'Issue', 'description' => 'Details', 'status' => 'pending']);
    $repair = RepairRequest::create([
        'user_id' => $member->id, 'dorm_id' => $dorm->id, 'reporter_name' => $member->name, 'reporter_type' => 'student',
        'room_label' => '304', 'category' => 'electrical', 'description' => 'Broken light', 'phone' => '0800000000',
        'email' => $member->email, 'appointment_date' => '2026-10-08', 'appointment_time' => '10:00', 'status' => 'new',
    ]);
    $data = ['type' => 'expense', 'academic_year_id' => $year->id, 'category' => 'Maintenance', 'title' => 'Repair cost', 'amount' => '10.00', 'transaction_date' => '2026-10-07', 'status' => 'posted', 'is_public' => 0];
    $finance = FinancialTransaction::create([...$data, 'dorm_id' => $dorm->id, 'created_by' => $admin->id]);
    $this->actingAs($super)->post('/admin/users/'.$member->id.'/scores', ['academic_year_id' => $year->id, 'score' => 7, 'reason' => 'Correction'])->assertRedirect();
    $scoreLog = AuditLog::where('action', 'score.adjusted')->first();
    expect($scoreLog->old_values['total_score'])->toBe(0)->and($scoreLog->new_values['total_score'])->toBe(7)->and($scoreLog->target_type)->toBe(ScoreHistory::class);
    $this->patch('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertRedirect();
    $this->patch('/superadmin/users/'.$member->id.'/role', ['role' => 'user'])->assertRedirect();
    $roles = AuditLog::where('action', 'user.role_changed')->orderBy('id')->get();
    expect($roles[0]->actionLabel())->toBe('แต่งตั้ง Admin')->and($roles[1]->actionLabel())->toBe('ถอดถอน Admin')
        ->and($roles[0]->old_values['role'])->toBe('user')->and($roles[1]->new_values['role'])->toBe('user');
    $this->actingAs($admin)->put('/admin/finance/'.$finance->id, [...$data, 'amount' => '15.25'])->assertRedirect();
    $this->patch('/admin/repairs/'.$repair->id, ['status' => 'received'])->assertRedirect();
    $this->patch('/admin/complaints/'.$complaint->id, ['status' => 'reviewing', 'assigned_to' => $admin->id, 'note' => 'Review'])->assertRedirect();
    foreach (['finance.updated', 'repair.status_updated', 'complaint.updated'] as $action) {
        $log = AuditLog::where('action', $action)->firstOrFail();
        expect((int) $log->actor_id)->toBe($admin->id)->and($log->old_values)->not->toBeNull()
            ->and($log->new_values)->not->toBeNull()->and($log->created_at)->not->toBeNull()->and($log->actor_snapshot['name'])->toBe($admin->name);
        $this->actingAs($super)->get('/superadmin/audit-logs/'.$log->id)->assertOk()->assertSee($admin->name);
    }
    expect(AuditLog::where('action', 'finance.updated')->first()->old_values['amount'])->toBe('10.00')
        ->and(AuditLog::where('action', 'finance.updated')->first()->new_values['amount'])->toBe('15.25');
});

test('audit empty page and system records with missing actor remain readable', function () {
    $super = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($super)->get('/superadmin/audit-logs')->assertOk()->assertSee('ยังไม่มี Audit Log');
    $log = AuditLog::create(['action' => 'setup.superadmin', 'target_type' => User::class, 'target_id' => 99999, 'new_values' => ['role' => 'superadmin']]);
    $this->get('/superadmin/audit-logs/'.$log->id)->assertOk()->assertSee('ระบบ / ไม่พบบัญชีเดิม')->assertSee('ไม่มีข้อมูลเดิม');
});

test('attendance award audit records before after score within correct academic year', function () {
    $dorm = Dorm::create(['code' => 'H8', 'name' => 'Dorm 8']);
    $year = AcademicYear::create(['year' => 2569]);
    $otherYear = AcademicYear::create(['year' => 2568]);
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $member = User::factory()->create(['dorm_id' => $dorm->id, 'student_id' => '673380534-7', 'qr_token' => (string) Str::uuid()]);
    foreach ([[$year, 5], [$otherYear, 500]] as [$academicYear, $score]) {
        ScoreHistory::create(['user_id' => $member->id, 'academic_year_id' => $academicYear->id, 'score' => $score, 'reason' => 'Existing score', 'created_by' => $admin->id]);
    }
    $activity = Activity::create([
        'dorm_id' => $dorm->id, 'academic_year_id' => $year->id, 'title' => 'Activity', 'location' => 'Hall', 'score' => 10,
        'status' => 'open', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'created_by' => $admin->id,
    ]);
    $this->actingAs($admin)->postJson('/admin/activities/'.$activity->id.'/attendances', ['qr_token' => $member->qr_token])->assertCreated();
    $log = AuditLog::where('action', 'attendance.created')->firstOrFail();
    expect($log->old_values['total_score'])->toBe(5)->and($log->new_values['total_score'])->toBe(15)
        ->and($log->new_values['academic_year_id'])->toBe($year->id)->and($log->actor_snapshot['id'])->toBe($admin->id);
});
