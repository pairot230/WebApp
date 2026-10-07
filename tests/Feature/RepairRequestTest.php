<?php

use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Dorm;
use App\Models\Floor;
use App\Models\RepairRequest;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function repairFixture(): array
{
    $dorm = Dorm::create(['code' => 'H8', 'name' => 'Dorm 8']);
    $building = Building::create(['dorm_id' => $dorm->id, 'code' => 'A', 'name' => 'Building A']);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3]);
    $room = Room::create(['floor_id' => $floor->id, 'number' => '304', 'capacity' => 2]);
    $member = User::factory()->create(['dorm_id' => $dorm->id, 'room_id' => $room->id, 'phone' => '0800000000']);
    $peer = User::factory()->create(['dorm_id' => $dorm->id]);
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $superadmin = User::factory()->create(['role' => 'superadmin', 'dorm_id' => $dorm->id]);
    $repair = RepairRequest::create([
        ...repairPayload(), 'user_id' => $member->id, 'dorm_id' => $dorm->id, 'room_id' => $room->id, 'status' => 'new',
    ]);

    return compact('dorm', 'room', 'member', 'peer', 'admin', 'superadmin', 'repair');
}

function repairPayload(): array
{
    return [
        'reporter_name' => 'Repair Member', 'reporter_type' => 'student', 'room_label' => '304',
        'category' => 'electrical', 'description' => 'Broken light near door',
        'phone' => '0801234567', 'email' => 'repair@kkumail.com',
        'appointment_date' => now('Asia/Bangkok')->addDay()->toDateString(), 'appointment_time' => '10:30',
    ];
}

test('repair form displays all fields and creates own request with backend identity', function () {
    $f = repairFixture();
    $this->actingAs($f['member'])->get('/repairs/create')->assertOk()
        ->assertSee('ชื่อ - สกุล')->assertSee('สถานะผู้แจ้งซ่อม')->assertSee('ห้องผู้แจ้งซ่อม')
        ->assertSee('ประเภทงานแจ้งซ่อม')->assertSee('ลักษณะการชำรุด / สถานที่ชำรุด')
        ->assertSee('เบอร์โทรศัพท์')->assertSee('Email')->assertSee('วันนัดหมาย')->assertSee('เวลานัดหมาย')
        ->assertSee($f['member']->name)->assertSee($f['member']->email)->assertSee('บันทึกข้อมูล');
    $this->post('/repairs', [...repairPayload(), 'email' => 'CONTACT@EXAMPLE.COM'])->assertRedirect();
    $repair = RepairRequest::latest('id')->first();
    expect($repair->user_id)->toBe($f['member']->id)->and($repair->dorm_id)->toBe($f['dorm']->id)
        ->and($repair->room_id)->toBe($f['room']->id)->and($repair->email)->toBe('contact@example.com')
        ->and($repair->status)->toBe('new')->and($repair->assigned_to)->toBeNull();
    $this->get('/repairs/'.$repair->id)->assertOk()->assertSee('Broken light near door')->assertSee('แจ้งใหม่');
    expect(AuditLog::where('action', 'repair.created')->count())->toBe(1);
});

test('repair supports every reporter type and work category without changing account roles', function () {
    $f = repairFixture();
    $this->actingAs($f['member']);
    foreach (array_keys(RepairRequest::REPORTER_TYPES) as $type) {
        foreach (array_keys(RepairRequest::CATEGORIES) as $category) {
            $this->post('/repairs', [...repairPayload(), 'reporter_type' => $type, 'category' => $category])->assertRedirect();
        }
    }
    expect($f['member']->fresh()->role)->toBe('user')->and(RepairRequest::count())->toBe(13);
});

test('repair requires all form fields and validates contact enums and appointment formats', function () {
    $f = repairFixture();
    $this->actingAs($f['member']);
    foreach (array_keys(repairPayload()) as $field) {
        $payload = repairPayload();
        unset($payload[$field]);
        $this->postJson('/repairs', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    foreach (['reporter_name' => ['bad'], 'reporter_type' => 'unknown', 'category' => 'unknown', 'email' => 'invalid', 'phone' => 'words', 'appointment_date' => '2026-02-30', 'appointment_time' => '25:00', 'description' => str_repeat('a', 10001)] as $field => $value) {
        $this->postJson('/repairs', [...repairPayload(), $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(RepairRequest::count())->toBe(1);
});

test('appointment validation uses Bangkok date and rejects past dates or elapsed time today', function () {
    $this->travelTo(Carbon::parse('2026-10-07 17:30:00', 'UTC'));
    $f = repairFixture();
    $this->actingAs($f['member']);
    $this->post('/repairs', [...repairPayload(), 'appointment_date' => '2026-10-08', 'appointment_time' => '01:00'])->assertRedirect();
    $repair = RepairRequest::latest('id')->first();
    expect($repair->appointment_date->format('Y-m-d'))->toBe('2026-10-08')->and($repair->appointment_time)->toBe('01:00');
    foreach ([['2026-10-07', '23:59'], ['2026-10-08', '00:15']] as [$date, $time]) {
        $this->postJson('/repairs', [...repairPayload(), 'appointment_date' => $date, 'appointment_time' => $time])->assertUnprocessable()->assertJsonValidationErrors('appointment_date');
    }
});

test('repair creation rejects forged owner dorm room status and management fields', function () {
    $f = repairFixture();
    $this->actingAs($f['member']);
    foreach (['user_id' => $f['peer']->id, 'dorm_id' => $f['dorm']->id, 'room_id' => $f['room']->id, 'status' => 'completed', 'assigned_to' => $f['admin']->id, 'note' => 'Forgery'] as $field => $value) {
        $this->postJson('/repairs', [...repairPayload(), $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(RepairRequest::count())->toBe(1);
});

test('members cannot view peers or update status even through direct URLs and forged filters', function () {
    $f = repairFixture();
    $other = RepairRequest::create([...repairPayload(), 'user_id' => $f['peer']->id, 'dorm_id' => $f['dorm']->id, 'description' => 'PRIVATE PEER REPAIR', 'status' => 'new']);
    $this->actingAs($f['member'])->get('/repairs?user_id='.$f['peer']->id)->assertOk()->assertSee('Broken light near door')->assertDontSee('PRIVATE PEER REPAIR');
    $this->get('/repairs/'.$other->id)->assertForbidden();
    $this->get('/admin/repairs')->assertForbidden();
    $this->get('/admin/repairs/'.$f['repair']->id)->assertForbidden();
    $this->patchJson('/admin/repairs/'.$f['repair']->id, ['status' => 'completed'])->assertForbidden();
    $this->patchJson('/repairs/'.$f['repair']->id, ['status' => 'completed'])->assertStatus(405);
    $this->deleteJson('/repairs/'.$f['repair']->id)->assertStatus(405);
    $this->get('/repairs/99999')->assertNotFound();
    expect($f['repair']->fresh()->status)->toBe('new');
});

test('repair staff authorization respects dorm and protects superadmin records', function () {
    $f = repairFixture();
    $protected = RepairRequest::create([...repairPayload(), 'user_id' => $f['superadmin']->id, 'dorm_id' => $f['dorm']->id, 'description' => 'SUPERADMIN SECRET', 'status' => 'new']);
    $otherDorm = Dorm::create(['code' => 'OTHER', 'name' => 'Other Dorm']);
    $outsider = User::factory()->create(['role' => 'admin', 'dorm_id' => $otherDorm->id]);
    $this->actingAs($f['admin'])->get('/admin/repairs')->assertOk()->assertSee('Broken light near door')->assertDontSee('SUPERADMIN SECRET');
    $this->get('/admin/repairs/'.$protected->id)->assertForbidden();
    $this->patchJson('/admin/repairs/'.$protected->id, ['status' => 'received'])->assertForbidden();
    $this->get('/repairs/'.$f['repair']->id)->assertForbidden();
    $this->actingAs($outsider)->get('/admin/repairs')->assertOk()->assertDontSee('Broken light near door');
    $this->get('/admin/repairs/'.$f['repair']->id)->assertForbidden();
    $this->patchJson('/admin/repairs/'.$f['repair']->id, ['status' => 'received'])->assertForbidden();
    $this->actingAs($f['superadmin'])->get('/admin/repairs/'.$protected->id)->assertOk();
    $this->patch('/admin/repairs/'.$protected->id, ['status' => 'received'])->assertRedirect();
});

test('staff can update all six statuses and owner sees resulting status with audit', function (string $status, string $label) {
    $f = repairFixture();
    $f['repair']->update(['status' => 'cancelled']);
    $url = '/admin/repairs/'.$f['repair']->id;
    $this->actingAs($f['admin'])->get($url)->assertOk();
    $this->patch($url, ['status' => $status])->assertRedirect($url);
    expect($f['repair']->fresh()->status)->toBe($status);
    $this->actingAs($f['member'])->get('/repairs/'.$f['repair']->id)->assertOk()->assertSee($label);
    if ($status !== 'cancelled') {
        $audit = AuditLog::where('action', 'repair.status_updated')->first();
        expect($audit->old_values['status'])->toBe('cancelled')->and($audit->new_values['status'])->toBe($status);
    } else {
        expect(AuditLog::count())->toBe(0);
    }
})->with(array_map(fn ($status, $label) => [$status, $label], array_keys(RepairRequest::STATUS_LABELS), array_values(RepairRequest::STATUS_LABELS)));

test('repair update rejects invalid statuses and tampering with original fields', function () {
    $f = repairFixture();
    $this->actingAs($f['admin']);
    $url = '/admin/repairs/'.$f['repair']->id;
    $this->patchJson($url, ['status' => 'unknown'])->assertUnprocessable();
    foreach ([...repairPayload(), 'user_id' => $f['peer']->id, 'dorm_id' => $f['dorm']->id, 'room_id' => $f['room']->id, 'assigned_to' => $f['admin']->id, 'note' => 'Forgery'] as $field => $value) {
        $this->patchJson($url, ['status' => 'completed', $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect($f['repair']->fresh()->status)->toBe('new');
});

test('repair search and status category filters remain scoped including OR search branches', function () {
    $f = repairFixture();
    RepairRequest::create([...repairPayload(), 'user_id' => $f['peer']->id, 'dorm_id' => $f['dorm']->id, 'description' => 'PRIVATE PEER REPAIR', 'status' => 'new']);
    $this->actingAs($f['member']);
    foreach (['Repair Member', '304', 'Broken light', '0801234567', 'repair@kkumail.com'] as $search) {
        $this->get('/repairs?'.http_build_query(['search' => $search, 'status' => 'new', 'category' => 'electrical']))
            ->assertOk()->assertSee('Broken light near door')->assertDontSee('PRIVATE PEER REPAIR');
    }
    $this->get('/repairs?category=plumbing')->assertOk()->assertDontSee('Broken light near door');
    $this->get('/repairs?status=completed')->assertOk()->assertDontSee('Broken light near door');
    $this->getJson('/repairs?category=invalid')->assertUnprocessable();
    $this->actingAs($f['admin'])->get('/admin/repairs?search=304&status=new&category=electrical')->assertOk()->assertSee('Broken light near door');
    $this->get('/admin/repairs?category=plumbing')->assertOk()->assertDontSee('Broken light near door');
});

test('repair history preserves original dorm room and contact after member moves', function () {
    $f = repairFixture();
    $otherDorm = Dorm::create(['code' => 'MOVE', 'name' => 'Moved Dorm']);
    $f['member']->update(['name' => 'Changed Name']);
    $f['member']->dorm_id = $otherDorm->id;
    $f['member']->room_id = null;
    $f['member']->save();
    $this->actingAs($f['member'])->get('/repairs/'.$f['repair']->id)->assertOk()->assertSee('Dorm 8')->assertSee('Repair Member')->assertSee('304');
    $this->actingAs($f['admin'])->get('/admin/repairs/'.$f['repair']->id)->assertOk();
    expect($f['repair']->fresh()->room_id)->toBe($f['room']->id);
});

test('repair escapes submitted content and guest inactive unassigned accounts are denied', function () {
    $f = repairFixture();
    $f['repair']->update(['description' => '<script>alert(1)</script>']);
    $this->get('/repairs')->assertRedirect(route('login'));
    $this->post('/repairs', repairPayload())->assertRedirect(route('login'));
    $this->actingAs($f['member'])->get('/repairs/'.$f['repair']->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->actingAs(User::factory()->create(['is_active' => false]))->get('/repairs')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'unknown']))->get('/repairs')->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson('/repairs', repairPayload())->assertForbidden();
    $f['dorm']->update(['is_active' => false]);
    $this->actingAs($f['member'])->postJson('/repairs', repairPayload())->assertForbidden();
});

test('repair creation and status changes rollback on audit failure and enforce CSRF', function () {
    $f = repairFixture();
    AuditLog::creating(function (): void {
        throw new RuntimeException('Audit failure');
    });
    try {
        $this->actingAs($f['member'])->post('/repairs', repairPayload())->assertStatus(500);
        expect(RepairRequest::count())->toBe(1);
        $this->actingAs($f['admin'])->patch('/admin/repairs/'.$f['repair']->id, ['status' => 'completed'])->assertStatus(500);
        expect($f['repair']->fresh()->status)->toBe('new');
    } finally {
        AuditLog::flushEventListeners();
    }
    $this->app['env'] = 'production';
    $this->actingAs($f['member'])->postJson('/repairs', repairPayload())->assertStatus(419);
    $this->actingAs($f['admin'])->patchJson('/admin/repairs/'.$f['repair']->id, ['status' => 'completed'])->assertStatus(419);
});

test('repair room relation must match own dorm while staff without room can report from office', function () {
    $f = repairFixture();
    $otherDorm = Dorm::create(['code' => 'MISMATCH', 'name' => 'Other']);
    $f['member']->dorm_id = $otherDorm->id;
    $f['member']->save();
    $this->actingAs($f['member'])->postJson('/repairs', repairPayload())->assertUnprocessable()->assertJsonValidationErrors('room_label');
    $this->actingAs($f['admin'])->post('/repairs', [...repairPayload(), 'reporter_type' => 'staff', 'room_label' => 'Dorm office'])->assertRedirect();
    $repair = RepairRequest::latest('id')->first();
    expect($repair->room_id)->toBeNull()->and($repair->user_id)->toBe($f['admin']->id)->and($repair->room_label)->toBe('Dorm office');
});
