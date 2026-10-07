<?php

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Dorm;
use App\Models\ScoreHistory;
use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function activityFixture(): array
{
    $dorm = Dorm::create(['code' => 'ACT', 'name' => 'Activity Dorm']);
    $year = AcademicYear::create(['year' => 2569, 'is_active' => true]);
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $member = User::factory()->create([
        'dorm_id' => $dorm->id, 'student_id' => '673380534-7', 'qr_token' => (string) Str::uuid(),
    ]);
    $activity = Activity::create([
        'dorm_id' => $dorm->id, 'academic_year_id' => $year->id, 'title' => 'Dorm cleaning',
        'description' => 'Cleaning day', 'location' => 'Hall', 'score' => 10, 'status' => 'open',
        'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'created_by' => $admin->id,
    ]);

    return compact('dorm', 'year', 'admin', 'member', 'activity');
}

function activityPayload(Activity $activity): array
{
    return [
        'dorm_id' => $activity->dorm_id, 'academic_year_id' => $activity->academic_year_id,
        'title' => $activity->title, 'description' => $activity->description, 'location' => $activity->location,
        'starts_at' => $activity->starts_at->timezone('Asia/Bangkok')->format('Y-m-d\TH:i'),
        'ends_at' => $activity->ends_at->timezone('Asia/Bangkok')->format('Y-m-d\TH:i'),
        'score' => $activity->score, 'status' => $activity->status,
    ];
}

test('admin activity CRUD renders forms validates fields and converts Bangkok time to UTC', function () {
    $f = activityFixture();
    $this->actingAs($f['admin']);
    $this->get('/admin/activities')->assertOk()->assertSee('Dorm cleaning');
    $this->get('/admin/activities/create')->assertOk();
    $data = [...activityPayload($f['activity']), 'title' => 'Created activity', 'starts_at' => '2026-10-07T14:30', 'ends_at' => '2026-10-07T15:30'];
    $this->post('/admin/activities', $data)->assertRedirect();
    $created = Activity::latest('id')->first();
    expect($created->starts_at->format('Y-m-d H:i'))->toBe('2026-10-07 07:30');
    expect((int) $created->created_by)->toBe($f['admin']->id);
    $this->get('/admin/activities/'.$created->id)->assertOk()->assertSee('14:30');
    $this->get('/admin/activities/'.$created->id.'/edit')->assertOk();
    $this->put('/admin/activities/'.$created->id, [...$data, 'status' => 'closed'])->assertRedirect();
    expect($created->fresh()->status)->toBe('closed');
    $this->delete('/admin/activities/'.$created->id)->assertRedirect('/admin/activities');
    expect($created->fresh())->toBeNull();
    expect(AuditLog::where('action', 'like', 'activity.%')->count())->toBe(3);
    foreach ([['ends_at' => $data['starts_at']], ['score' => -1], ['status' => 'unknown'], ['created_by' => $f['member']->id], ['dorm_id' => 99999], ['academic_year_id' => 99999]] as $invalid) {
        $this->postJson('/admin/activities', [...$data, ...$invalid])->assertUnprocessable();
    }
});

test('activity management and scanning enforce roles dorm scopes and direct URL permissions', function () {
    $f = activityFixture();
    $other = Dorm::create(['code' => 'OTHER', 'name' => 'Other Dorm']);
    $outsider = User::factory()->create(['role' => 'admin', 'dorm_id' => $other->id]);
    $activityUrl = '/admin/activities/'.$f['activity']->id;
    $this->get($activityUrl)->assertRedirect(route('login'));
    foreach ([$f['member'], $outsider] as $actor) {
        $this->actingAs($actor)->get($activityUrl)->assertForbidden();
        $this->putJson($activityUrl, activityPayload($f['activity']))->assertForbidden();
        $this->delete($activityUrl)->assertForbidden();
        $this->get($activityUrl.'/attendances')->assertForbidden();
        $this->postJson($activityUrl.'/attendances', ['qr_token' => $f['member']->qr_token])->assertForbidden();
        $this->postJson($activityUrl.'/attendances/preview', ['qr_token' => $f['member']->qr_token])->assertForbidden();
    }
    $this->actingAs($outsider)->get('/admin/activities')->assertOk()->assertDontSee('Dorm cleaning');
    $this->postJson('/admin/activities', activityPayload($f['activity']))->assertForbidden();
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin)->get($activityUrl)->assertOk();
    $this->get($activityUrl.'/attendances')->assertOk();
});

test('personal QR is stable private and only contains the opaque token', function () {
    $f = activityFixture();
    $this->get('/me/qr/image')->assertRedirect(route('login'));
    $this->actingAs($f['member'])->get('/me/qr')->assertOk();
    $response = $this->get('/me/qr/image')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    $expected = (new SvgWriter)->write(new QrCode(data: $f['member']->qr_token, size: 300, margin: 16))->getString();
    expect($response->getContent())->toBe($expected)->not->toContain($f['member']->email);
    expect($f['member']->toArray())->not->toHaveKey('qr_token');
    $this->get('/me/qr/image')->assertContent($expected);
    $this->get('/me/qr/image?user_id='.$f['admin']->id)->assertContent($expected);
    $this->actingAs($f['admin'])->get('/me/qr/image')->assertOk();
    expect($f['admin']->fresh()->qr_token)->not->toBeNull()->not->toBe($f['member']->qr_token);
    $this->get('/me/qr/image')->assertOk();
    expect($f['admin']->fresh()->qr_token)->not->toBeNull();
});

test('QR preview verifies identity without recording then attendance awards backend score exactly once', function () {
    $f = activityFixture();
    $url = '/admin/activities/'.$f['activity']->id.'/attendances';
    $this->actingAs($f['admin'])->postJson($url.'/preview', ['qr_token' => $f['member']->qr_token])
        ->assertOk()->assertJsonPath('student_id', $f['member']->student_id);
    expect(Attendance::count())->toBe(0)->and(ScoreHistory::count())->toBe(0);
    $this->postJson($url, ['qr_token' => $f['member']->qr_token])->assertCreated()->assertJsonPath('score', 10);
    $attendance = Attendance::first();
    $history = ScoreHistory::first();
    expect((int) $attendance->user_id)->toBe($f['member']->id)
        ->and((int) $attendance->checked_in_by)->toBe($f['admin']->id)
        ->and((int) $history->attendance_id)->toBe($attendance->id)
        ->and((int) $history->activity_id)->toBe($f['activity']->id)
        ->and((int) $history->academic_year_id)->toBe($f['year']->id)
        ->and($history->score)->toBe(10);
    $this->postJson($url, ['qr_token' => $f['member']->qr_token])->assertConflict();
    expect(Attendance::count())->toBe(1)->and(ScoreHistory::count())->toBe(1)->and(AuditLog::count())->toBe(1);
    $this->get($url)->assertOk()->assertSee($f['member']->name);
});

test('check-in rejects malformed forged inactive and out-of-dorm identities', function () {
    $f = activityFixture();
    $url = '/admin/activities/'.$f['activity']->id.'/attendances';
    $this->actingAs($f['admin']);
    foreach ([[], ['qr_token' => '673380534-7'], ['qr_token' => (string) Str::uuid()], ['qr_token' => $f['member']->qr_token, 'score' => 999], ['qr_token' => $f['member']->qr_token, 'user_id' => $f['admin']->id], ['qr_token' => $f['member']->qr_token, 'activity_id' => $f['activity']->id], ['qr_token' => $f['member']->qr_token, 'checked_in_by' => $f['member']->id]] as $payload) {
        $this->postJson($url, $payload)->assertUnprocessable();
    }
    $f['member']->is_active = false;
    $f['member']->save();
    $this->postJson($url, ['qr_token' => $f['member']->qr_token])->assertUnprocessable();
    $this->postJson($url.'/preview', ['qr_token' => $f['member']->qr_token])->assertUnprocessable();
    $f['member']->is_active = true;
    $f['member']->dorm_id = Dorm::create(['code' => 'SECOND', 'name' => 'Second'])->id;
    $f['member']->save();
    $this->postJson($url, ['qr_token' => $f['member']->qr_token])->assertUnprocessable();
    $this->postJson('/admin/activities/99999/attendances', ['qr_token' => $f['member']->qr_token])->assertNotFound();
    expect(Attendance::count())->toBe(0)->and(ScoreHistory::count())->toBe(0);
});

test('check-in requires open activity within time window and active dorm', function (string $condition) {
    $f = activityFixture();
    if (in_array($condition, ['draft', 'closed', 'cancelled'])) {
        $f['activity']->update(['status' => $condition]);
    } elseif ($condition === 'early') {
        $f['activity']->update(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    } elseif ($condition === 'late') {
        $f['activity']->update(['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
    } else {
        $f['dorm']->update(['is_active' => false]);
    }
    $this->actingAs($f['admin'])->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertUnprocessable();
    expect(Attendance::count())->toBe(0)->and(ScoreHistory::count())->toBe(0);
})->with(['draft', 'closed', 'cancelled', 'early', 'late', 'inactive dorm']);

test('activity history cannot be deleted or have score year or dorm changed after attendance', function () {
    $f = activityFixture();
    $url = '/admin/activities/'.$f['activity']->id;
    $this->actingAs($f['admin'])->postJson($url.'/attendances', ['qr_token' => $f['member']->qr_token])->assertCreated();
    $data = activityPayload($f['activity']);
    $this->putJson($url, [...$data, 'score' => 999])->assertUnprocessable();
    $year = AcademicYear::create(['year' => 2570]);
    $this->putJson($url, [...$data, 'academic_year_id' => $year->id])->assertUnprocessable();
    $this->deleteJson($url)->assertUnprocessable();
    $this->put($url, [...$data, 'status' => 'closed'])->assertRedirect();
    $this->postJson($url.'/attendances', ['qr_token' => $f['member']->qr_token])->assertUnprocessable();
    expect($f['activity']->fresh()->score)->toBe(10)->and(ScoreHistory::sum('score'))->toBe(10);
});

test('attendance and score rollback together when score or audit creation fails', function (string $model) {
    $f = activityFixture();
    $model::creating(function (): void {
        throw new RuntimeException('Test write failure');
    });
    try {
        $this->actingAs($f['admin'])->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertStatus(500);
        expect(Attendance::count())->toBe(0)->and(ScoreHistory::count())->toBe(0)->and(AuditLog::count())->toBe(0);
    } finally {
        $model::flushEventListeners();
    }
})->with([[ScoreHistory::class], [AuditLog::class]]);

test('score adjustment appends signed history with reason audit and year-scoped totals', function () {
    $f = activityFixture();
    $url = '/admin/users/'.$f['member']->id.'/scores';
    $this->actingAs($f['admin'])->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertCreated();
    $this->get($url)->assertOk();
    $this->post($url, ['academic_year_id' => $f['year']->id, 'score' => -3, 'reason' => 'Correction'])->assertRedirect($url);
    $next = AcademicYear::create(['year' => 2570]);
    $this->post($url, ['academic_year_id' => $next->id, 'score' => 2, 'reason' => 'Next year'])->assertRedirect();
    expect(ScoreHistory::count())->toBe(3)->and(ScoreHistory::sum('score'))->toBe(9);
    expect(AuditLog::where('action', 'score.adjusted')->count())->toBe(2);
    $this->actingAs($f['member'])->get('/me/scores?academic_year_id='.$f['year']->id)->assertOk()->assertSee('7')->assertSee('Correction')->assertDontSee('Next year');
    foreach ([['score' => 0], ['reason' => ''], ['score' => '2.5'], ['created_by' => $f['member']->id], ['attendance_id' => 1], ['activity_id' => 1], ['user_id' => $f['member']->id]] as $invalid) {
        $this->actingAs($f['admin'])->postJson($url, ['academic_year_id' => $f['year']->id, 'score' => 1, 'reason' => 'Valid', ...$invalid])->assertUnprocessable();
    }
    $this->putJson('/admin/scores/1', ['score' => 999])->assertNotFound();
    $this->deleteJson('/admin/scores/1')->assertNotFound();
});

test('member pages expose only own attendance score and QR despite forged query parameters', function () {
    $f = activityFixture();
    $other = User::factory()->create(['dorm_id' => $f['dorm']->id]);
    ScoreHistory::create(['user_id' => $other->id, 'academic_year_id' => $f['year']->id, 'score' => 99, 'reason' => 'OTHER SECRET', 'created_by' => $f['admin']->id]);
    $draft = $f['activity']->replicate();
    $draft->title = 'HIDDEN DRAFT';
    $draft->status = 'draft';
    $draft->save();
    $otherDorm = Dorm::create(['code' => 'PRIVATE', 'name' => 'Private']);
    $private = $f['activity']->replicate();
    $private->dorm_id = $otherDorm->id;
    $private->title = 'OTHER DORM SECRET';
    $private->save();
    $this->actingAs($f['member'])->get('/activities')->assertOk()->assertSee('Dorm cleaning')->assertDontSee('HIDDEN DRAFT')->assertDontSee('OTHER DORM SECRET');
    $this->get('/me/scores?user_id='.$other->id)->assertOk()->assertDontSee('OTHER SECRET');
    $this->get('/admin/users/'.$other->id.'/scores')->assertForbidden();
    $this->postJson('/admin/users/'.$f['member']->id.'/scores', ['score' => 999])->assertForbidden();
    $this->actingAs($f['admin'])->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertCreated();
    $this->actingAs($f['member'])->get('/activities')->assertSee('เช็กชื่อแล้ว');
});

test('admin cannot adjust protected accounts or members of other dorms but superadmin can', function () {
    $f = activityFixture();
    $superadmin = User::factory()->create(['role' => 'superadmin', 'student_id' => 'SUPER', 'dorm_id' => $f['dorm']->id, 'qr_token' => (string) Str::uuid()]);
    $otherAdmin = User::factory()->create(['role' => 'admin', 'dorm_id' => $f['dorm']->id]);
    $otherDorm = Dorm::create(['code' => 'OUT', 'name' => 'Outside']);
    $outsider = User::factory()->create(['dorm_id' => $otherDorm->id]);
    $payload = ['academic_year_id' => $f['year']->id, 'score' => 1, 'reason' => 'Award'];
    foreach ([$superadmin, $otherAdmin, $outsider] as $target) {
        $this->actingAs($f['admin'])->get('/admin/users/'.$target->id.'/scores')->assertForbidden();
        $this->postJson('/admin/users/'.$target->id.'/scores', $payload)->assertForbidden();
    }
    $this->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $superadmin->qr_token])->assertForbidden();
    $this->actingAs($superadmin)->post('/admin/users/'.$outsider->id.'/scores', $payload)->assertRedirect();
    $this->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $superadmin->qr_token])->assertCreated();
});

test('score adjustments rollback on audit failure and all writes require CSRF', function () {
    $f = activityFixture();
    $this->actingAs($f['admin']);
    AuditLog::creating(function (): void {
        throw new RuntimeException('Test audit failure');
    });
    try {
        $this->postJson('/admin/users/'.$f['member']->id.'/scores', ['academic_year_id' => $f['year']->id, 'score' => -1, 'reason' => 'Correction'])->assertStatus(500);
        expect(ScoreHistory::count())->toBe(0);
    } finally {
        AuditLog::flushEventListeners();
    }
    $this->app['env'] = 'production';
    $this->postJson('/admin/activities', activityPayload($f['activity']))->assertStatus(419);
    $this->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertStatus(419);
    $this->postJson('/admin/activities/'.$f['activity']->id.'/attendances/preview', ['qr_token' => $f['member']->qr_token])->assertStatus(419);
    $this->postJson('/admin/users/'.$f['member']->id.'/scores', ['academic_year_id' => $f['year']->id, 'score' => 1, 'reason' => 'Award'])->assertStatus(419);
});

test('one member may attend different activities including zero score without duplicate awards', function () {
    $f = activityFixture();
    $next = $f['activity']->replicate();
    $next->title = 'Zero score activity';
    $next->score = 0;
    $next->save();
    $this->actingAs($f['admin']);
    foreach ([$f['activity'], $next] as $activity) {
        $this->postJson('/admin/activities/'.$activity->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertCreated();
        $this->postJson('/admin/activities/'.$activity->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertConflict();
    }
    expect(Attendance::count())->toBe(2)->and(ScoreHistory::count())->toBe(2)->and(ScoreHistory::sum('score'))->toBe(10);
});

test('own participation history survives dorm changes and later activity drafts', function () {
    $f = activityFixture();
    $this->actingAs($f['admin'])->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertCreated();
    $f['activity']->update(['status' => 'draft']);
    $f['member']->dorm_id = Dorm::create(['code' => 'MOVE', 'name' => 'Moved Dorm'])->id;
    $f['member']->save();
    $this->actingAs($f['member'])->get('/activities')->assertOk()->assertSee('Dorm cleaning')->assertSee('เช็กชื่อแล้ว');
    $this->get('/me/scores')->assertOk()->assertSee('Dorm cleaning');
});

test('activity filters are scoped and superadmin cannot move attended activity to another dorm', function () {
    $f = activityFixture();
    $other = $f['activity']->replicate();
    $other->title = 'Closed event';
    $other->status = 'closed';
    $other->save();
    $this->actingAs($f['admin'])->get('/admin/activities?status=open&search=cleaning&academic_year_id='.$f['year']->id)
        ->assertOk()->assertSee('Dorm cleaning')->assertDontSee('Closed event');
    $this->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $f['member']->qr_token])->assertCreated();
    $dorm = Dorm::create(['code' => 'MOVE', 'name' => 'Moved Dorm']);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))
        ->putJson('/admin/activities/'.$f['activity']->id, [...activityPayload($f['activity']), 'dorm_id' => $dorm->id])
        ->assertUnprocessable()->assertJsonValidationErrors('dorm_id');
    expect((int) $f['activity']->fresh()->dorm_id)->toBe($f['dorm']->id);
});

test('QR check-in rejects unsupported member roles and members without student identity', function () {
    $f = activityFixture();
    $this->actingAs($f['admin']);
    $url = '/admin/activities/'.$f['activity']->id.'/attendances';
    $f['member']->role = 'invalid';
    $f['member']->save();
    $this->postJson($url, ['qr_token' => $f['member']->qr_token])->assertUnprocessable();
    $f['member']->role = 'user';
    $f['member']->student_id = null;
    $f['member']->save();
    $this->postJson($url, ['qr_token' => $f['member']->qr_token])->assertUnprocessable();
    expect(Attendance::count())->toBe(0);
});
