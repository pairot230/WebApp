<?php

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Dorm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('single dorm installation rejects creating a second dorm even for superadmin', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->post('/admin/dorms', ['code' => 'H8', 'name' => 'Dorm 8', 'is_active' => 1])->assertRedirect();
    $this->get('/admin/dorms/create')->assertForbidden();
    $this->postJson('/admin/dorms', ['code' => 'H9', 'name' => 'Dorm 9', 'is_active' => 1])->assertForbidden();
    $this->get('/admin/dorms')->assertOk()->assertDontSee('เพิ่มหอพัก');
    expect(Dorm::count())->toBe(1);
});

function complaintFixture(): array
{
    $dorm = Dorm::create(['code' => 'CP', 'name' => 'Complaint Dorm']);
    $otherDorm = Dorm::create(['code' => 'OTHER', 'name' => 'Other Dorm']);
    $member = User::factory()->create(['dorm_id' => $dorm->id]);
    $peer = User::factory()->create(['dorm_id' => $dorm->id]);
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $outsider = User::factory()->create(['role' => 'admin', 'dorm_id' => $otherDorm->id]);
    $superadmin = User::factory()->create(['role' => 'superadmin', 'dorm_id' => $dorm->id]);
    $complaint = Complaint::create([
        'user_id' => $member->id, 'dorm_id' => $dorm->id, 'title' => 'Noisy hallway',
        'description' => 'Noise at night', 'status' => 'pending',
    ]);

    return compact('dorm', 'otherDorm', 'member', 'peer', 'admin', 'outsider', 'superadmin', 'complaint');
}

test('member creates complaint using backend owner dorm and pending status', function () {
    $f = complaintFixture();
    $this->actingAs($f['member'])->get('/complaints/create')->assertOk()->assertSee('Complaint Dorm');
    $this->post('/complaints', ['title' => 'Water issue', 'description' => 'No water'])->assertRedirect();
    $created = Complaint::latest('id')->first();
    expect($created->user_id)->toBe($f['member']->id)->and($created->dorm_id)->toBe($f['dorm']->id)
        ->and($created->status)->toBe('pending')->and($created->assigned_to)->toBeNull()->and($created->note)->toBeNull();
    $this->get('/complaints/'.$created->id)->assertOk()->assertSee('No water')->assertSee('รอดำเนินการ');
    expect(AuditLog::where('action', 'complaint.created')->count())->toBe(1);
});

test('complaint validation rejects empty oversized malformed and privilege fields', function () {
    $f = complaintFixture();
    $this->actingAs($f['member']);
    foreach ([[], ['title' => ['bad'], 'description' => 'Text'], ['title' => str_repeat('x', 256), 'description' => 'Text'], ['title' => 'Title', 'description' => str_repeat('x', 10001)]] as $invalid) {
        $this->postJson('/complaints', $invalid)->assertUnprocessable();
    }
    foreach (['user_id' => $f['peer']->id, 'dorm_id' => $f['otherDorm']->id, 'status' => 'completed', 'assigned_to' => $f['admin']->id, 'note' => 'Forged'] as $field => $value) {
        $this->postJson('/complaints', ['title' => 'Valid', 'description' => 'Valid', $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(Complaint::count())->toBe(1);
});

test('members only list and view their own complaints including forged owner filters', function () {
    $f = complaintFixture();
    $other = Complaint::create(['user_id' => $f['peer']->id, 'dorm_id' => $f['dorm']->id, 'title' => 'PRIVATE PEER', 'description' => 'Secret', 'status' => 'pending']);
    $this->actingAs($f['member'])->get('/complaints?user_id='.$f['peer']->id)->assertOk()->assertSee('Noisy hallway')->assertDontSee('PRIVATE PEER');
    $this->get('/complaints/'.$other->id)->assertForbidden();
    $this->get('/complaints/99999')->assertNotFound();
    $this->get('/admin/complaints')->assertForbidden();
    $this->get('/admin/complaints/'.$f['complaint']->id)->assertForbidden();
    $this->patchJson('/admin/complaints/'.$f['complaint']->id, ['status' => 'completed', 'assigned_to' => null, 'note' => 'Forgery'])->assertForbidden();
    $this->patchJson('/complaints/'.$f['complaint']->id, ['status' => 'completed'])->assertStatus(405);
    $this->deleteJson('/complaints/'.$f['complaint']->id)->assertStatus(405);
    expect($f['complaint']->fresh()->status)->toBe('pending');
});

test('admin manages only their dorm and cannot manage superadmin complaints', function () {
    $f = complaintFixture();
    $protected = Complaint::create(['user_id' => $f['superadmin']->id, 'dorm_id' => $f['dorm']->id, 'title' => 'SUPERADMIN PRIVATE', 'description' => 'Private', 'status' => 'pending']);
    $this->actingAs($f['admin'])->get('/admin/complaints')->assertOk()->assertSee('Noisy hallway')->assertDontSee('SUPERADMIN PRIVATE');
    $this->get('/admin/complaints/'.$protected->id)->assertForbidden();
    $this->patchJson('/admin/complaints/'.$protected->id, ['status' => 'completed', 'assigned_to' => null, 'note' => null])->assertForbidden();
    $this->get('/complaints/'.$f['complaint']->id)->assertForbidden();
    $this->actingAs($f['outsider'])->get('/admin/complaints')->assertOk()->assertDontSee('Noisy hallway');
    $this->get('/admin/complaints/'.$f['complaint']->id)->assertForbidden();
    $this->patchJson('/admin/complaints/'.$f['complaint']->id, ['status' => 'completed', 'assigned_to' => null, 'note' => null])->assertForbidden();
    $this->actingAs($f['superadmin'])->get('/admin/complaints')->assertOk()->assertSee('SUPERADMIN PRIVATE')->assertSee('Noisy hallway');
    $this->get('/admin/complaints/'.$protected->id)->assertOk();
    $this->patch('/admin/complaints/'.$protected->id, ['status' => 'reviewing', 'assigned_to' => $f['superadmin']->id, 'note' => 'Review'])->assertRedirect();
});

test('staff can set each supported status assign clear and note with audit', function (string $status, string $label) {
    $f = complaintFixture();
    $url = '/admin/complaints/'.$f['complaint']->id;
    $this->actingAs($f['admin'])->get($url)->assertOk()->assertSee('บันทึกการดำเนินการ');
    $this->patch($url, ['status' => $status, 'assigned_to' => $f['admin']->id, 'note' => 'Follow-up note'])->assertRedirect($url);
    $updated = $f['complaint']->fresh();
    expect($updated->status)->toBe($status)->and($updated->assigned_to)->toBe($f['admin']->id)->and($updated->note)->toBe('Follow-up note');
    $audit = AuditLog::where('action', 'complaint.updated')->first();
    expect($audit->old_values['status'])->toBe('pending')->and($audit->new_values['status'])->toBe($status);
    $this->actingAs($f['member'])->get('/complaints/'.$updated->id)->assertOk()->assertSee($label)->assertSee('Follow-up note')->assertSee($f['admin']->name);
    $this->actingAs($f['admin'])->patch($url, ['status' => $status, 'assigned_to' => null, 'note' => null])->assertRedirect();
    expect($updated->fresh()->assigned_to)->toBeNull()->and($updated->fresh()->note)->toBeNull();
})->with(array_map(fn ($status, $label) => [$status, $label], array_keys(Complaint::STATUS_LABELS), array_values(Complaint::STATUS_LABELS)));

test('assignment rejects ordinary users other dorm inactive staff and superadmin for admin', function () {
    $f = complaintFixture();
    $inactive = User::factory()->create(['role' => 'admin', 'dorm_id' => $f['dorm']->id, 'is_active' => false]);
    $url = '/admin/complaints/'.$f['complaint']->id;
    $this->actingAs($f['admin']);
    $this->get($url)->assertOk()->assertDontSee($f['outsider']->name)->assertDontSee($f['superadmin']->name)->assertDontSee($inactive->name);
    foreach ([$f['member']->id, $f['outsider']->id, $f['superadmin']->id, $inactive->id, 99999] as $id) {
        $this->patchJson($url, ['status' => 'reviewing', 'assigned_to' => $id, 'note' => null])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    }
    expect($f['complaint']->fresh()->assigned_to)->toBeNull()->and(AuditLog::count())->toBe(0);
    $this->actingAs($f['superadmin'])->patch($url, ['status' => 'reviewing', 'assigned_to' => $f['superadmin']->id, 'note' => 'Superadmin assigned'])->assertRedirect();
});

test('staff cannot replace original complaint fields and invalid status is rejected', function () {
    $f = complaintFixture();
    $this->actingAs($f['admin']);
    $url = '/admin/complaints/'.$f['complaint']->id;
    $valid = ['status' => 'reviewing', 'assigned_to' => null, 'note' => null];
    foreach (['user_id' => $f['peer']->id, 'dorm_id' => $f['otherDorm']->id, 'title' => 'Tampered', 'description' => 'Tampered'] as $field => $value) {
        $this->patchJson($url, [...$valid, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->patchJson($url, [...$valid, 'status' => 'unknown'])->assertUnprocessable();
    $this->patchJson($url, [...$valid, 'note' => str_repeat('x', 10001)])->assertUnprocessable();
    expect($f['complaint']->fresh()->title)->toBe('Noisy hallway')->and($f['complaint']->fresh()->user_id)->toBe($f['member']->id);
});

test('complaint dorm and owner history remain fixed after reporter moves', function () {
    $f = complaintFixture();
    $f['member']->dorm_id = $f['otherDorm']->id;
    $f['member']->save();
    $this->actingAs($f['member'])->get('/complaints/'.$f['complaint']->id)->assertOk()->assertSee('Complaint Dorm');
    $this->actingAs($f['admin'])->get('/admin/complaints/'.$f['complaint']->id)->assertOk();
    $this->actingAs($f['outsider'])->get('/admin/complaints/'.$f['complaint']->id)->assertForbidden();
});

test('complaint filters preserve owner and dorm isolation and escape user content', function () {
    $f = complaintFixture();
    $f['complaint']->update(['note' => '<script>alert(1)</script>']);
    Complaint::create(['user_id' => $f['peer']->id, 'dorm_id' => $f['dorm']->id, 'title' => 'Noisy PEER PRIVATE', 'description' => 'Secret', 'status' => 'pending']);
    $this->actingAs($f['member'])->get('/complaints?search=Noisy&status=pending')->assertOk()->assertSee('Noisy hallway')->assertDontSee('Noisy PEER PRIVATE');
    $this->get('/complaints?status=completed')->assertOk()->assertDontSee('Noisy hallway');
    $this->get('/complaints/'.$f['complaint']->id)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->actingAs($f['admin'])->get('/admin/complaints?status=completed')->assertOk()->assertDontSee('Noisy hallway');
    $this->get('/admin/complaints?status=invalid')->assertRedirect()->assertSessionHasErrors('status');
});

test('guests inactive and unknown roles cannot access complaint features and writes require CSRF', function () {
    $f = complaintFixture();
    foreach (['/complaints', '/complaints/create', '/complaints/'.$f['complaint']->id, '/admin/complaints'] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
    $this->actingAs(User::factory()->create(['is_active' => false]))->get('/complaints')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'unknown']))->get('/complaints')->assertForbidden();
    $unassigned = User::factory()->create();
    $this->actingAs($unassigned)->get('/complaints/create')->assertForbidden();
    $this->postJson('/complaints', ['title' => 'Invalid', 'description' => 'Invalid'])->assertForbidden();
    $f['dorm']->update(['is_active' => false]);
    $this->actingAs($f['member'])->postJson('/complaints', ['title' => 'Closed', 'description' => 'Closed'])->assertForbidden();
    $this->app['env'] = 'production';
    $this->actingAs($f['member'])->postJson('/complaints', ['title' => 'CSRF', 'description' => 'CSRF'])->assertStatus(419);
    $this->actingAs($f['admin'])->patchJson('/admin/complaints/'.$f['complaint']->id, ['status' => 'completed', 'assigned_to' => null, 'note' => null])->assertStatus(419);
});

test('complaint create and update rollback when audit fails and unchanged update adds no audit', function () {
    $f = complaintFixture();
    $this->actingAs($f['admin'])->patch('/admin/complaints/'.$f['complaint']->id, ['status' => 'pending', 'assigned_to' => null, 'note' => null])->assertRedirect();
    expect(AuditLog::count())->toBe(0);
    AuditLog::creating(function (): void {
        throw new RuntimeException('Audit failure');
    });
    try {
        $this->actingAs($f['member'])->post('/complaints', ['title' => 'Rollback', 'description' => 'Rollback'])->assertStatus(500);
        expect(Complaint::count())->toBe(1);
        $this->actingAs($f['admin'])->patch('/admin/complaints/'.$f['complaint']->id, ['status' => 'completed', 'assigned_to' => $f['admin']->id, 'note' => 'Rollback'])->assertStatus(500);
        expect($f['complaint']->fresh()->status)->toBe('pending')->and($f['complaint']->fresh()->assigned_to)->toBeNull()->and($f['complaint']->fresh()->note)->toBeNull();
    } finally {
        AuditLog::flushEventListeners();
    }
});
