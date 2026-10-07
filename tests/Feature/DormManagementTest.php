<?php

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Dorm;
use App\Models\Floor;
use App\Models\Room;
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function managementGraph(string $code = 'A'): array
{
    $dorm = Dorm::create(['code' => $code, 'name' => 'Dorm '.$code, 'is_active' => true]);
    $building = Building::create(['dorm_id' => $dorm->id, 'code' => 'B', 'name' => 'Building '.$code, 'is_active' => true]);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 1]);
    $room = Room::create(['floor_id' => $floor->id, 'number' => '101', 'capacity' => 2, 'is_active' => true]);

    return compact('dorm', 'building', 'floor', 'room');
}

function managementUserData(array $graph, string $number = '001'): array
{
    return [
        'student_id' => $number.'-0', 'name' => 'Member '.$number,
        'email' => 'member'.$number.'@kkumail.com', 'phone' => '0800000000',
        'dorm_id' => $graph['dorm']->id, 'room_id' => $graph['room']->id, 'is_active' => 1,
    ];
}

test('superadmin can perform hierarchy CRUD and render every form', function (string $resource, string $model) {
    $graph = $resource === 'dorms'
        ? ['dorm' => new Dorm, 'building' => new Building, 'floor' => new Floor]
        : managementGraph();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $payloads = [
        'dorms' => ['code' => 'NEW', 'name' => 'New Dorm', 'description' => 'Sample', 'is_active' => 1],
        'buildings' => ['dorm_id' => $graph['dorm']->id, 'code' => 'NEW', 'name' => 'New Building', 'is_active' => 1],
        'floors' => ['building_id' => $graph['building']->id, 'number' => 2, 'name' => 'Second floor'],
        'rooms' => ['floor_id' => $graph['floor']->id, 'number' => '102', 'capacity' => 2, 'is_active' => 1],
    ];
    $data = $payloads[$resource];
    $this->get('/admin/'.$resource)->assertOk();
    $this->get('/admin/'.$resource.'/create')->assertOk()->assertSee('บันทึกข้อมูล');
    $this->post('/admin/'.$resource, $data)->assertRedirect();
    $record = $model::latest('id')->firstOrFail();
    $this->get('/admin/'.$resource.'/'.$record->id)->assertOk();
    $this->get('/admin/'.$resource.'/'.$record->id.'/edit')->assertOk();
    $data[$resource === 'rooms' ? 'number' : 'name'] = $resource === 'rooms' ? '103' : 'Updated';
    $this->put('/admin/'.$resource.'/'.$record->id, $data)->assertRedirect();
    expect($record->fresh()->{$resource === 'rooms' ? 'number' : 'name'})->toBe($resource === 'rooms' ? '103' : 'Updated');
    $this->delete('/admin/'.$resource.'/'.$record->id)->assertRedirect('/admin/'.$resource);
    expect($model::find($record->id))->toBeNull();
})->with([
    ['dorms', Dorm::class], ['buildings', Building::class],
    ['floors', Floor::class], ['rooms', Room::class],
]);

test('admin hierarchy requests are limited to their dorm including forged parents', function () {
    $own = managementGraph('A');
    $other = managementGraph('B');
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $own['dorm']->id]);
    $this->actingAs($admin);
    foreach (['dorms' => 'dorm', 'buildings' => 'building', 'floors' => 'floor', 'rooms' => 'room'] as $resource => $key) {
        $this->get('/admin/'.$resource)->assertOk();
        $this->get('/admin/'.$resource.'/'.$other[$key]->id)->assertForbidden();
        $this->get('/admin/'.$resource.'/'.$other[$key]->id.'/edit')->assertForbidden();
        $this->putJson('/admin/'.$resource.'/'.$other[$key]->id, [])->assertForbidden();
        $this->delete('/admin/'.$resource.'/'.$other[$key]->id)->assertForbidden();
    }
    $this->postJson('/admin/dorms', ['code' => 'C', 'name' => 'Forbidden', 'is_active' => 1])->assertForbidden();
    $this->postJson('/admin/buildings', ['dorm_id' => $other['dorm']->id, 'code' => 'C', 'name' => 'Forbidden', 'is_active' => 1])->assertForbidden();
    $this->postJson('/admin/floors', ['building_id' => $other['building']->id, 'number' => 9])->assertForbidden();
    $this->postJson('/admin/rooms', ['floor_id' => $other['floor']->id, 'number' => '999', 'capacity' => 2, 'is_active' => 1])->assertForbidden();
    $this->post('/admin/rooms', ['floor_id' => $own['floor']->id, 'number' => '102', 'capacity' => 2, 'is_active' => 1])->assertRedirect();
});

test('users and guests cannot access or mutate management resources directly', function () {
    $graph = managementGraph();
    foreach (['dorms', 'buildings', 'floors', 'rooms', 'users'] as $resource) {
        $this->get('/admin/'.$resource)->assertRedirect(route('login'));
    }
    $this->actingAs(User::factory()->create());
    foreach (['dorms', 'buildings', 'floors', 'rooms', 'users'] as $resource) {
        $this->get('/admin/'.$resource)->assertForbidden();
        $this->get('/admin/'.$resource.'/create')->assertForbidden();
        $this->postJson('/admin/'.$resource, [])->assertForbidden();
        $this->putJson('/admin/'.$resource.'/1', [])->assertForbidden();
        $this->delete('/admin/'.$resource.'/1')->assertForbidden();
    }
});

test('referenced structures cannot be deleted and hierarchy cannot be reparented', function () {
    $graph = managementGraph();
    $other = managementGraph('B');
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    foreach (['dorms' => 'dorm', 'buildings' => 'building', 'floors' => 'floor'] as $resource => $key) {
        $this->delete('/admin/'.$resource.'/'.$graph[$key]->id)->assertRedirect()->assertSessionHas('error');
        expect($graph[$key]->fresh())->not->toBeNull();
    }
    $this->putJson('/admin/buildings/'.$graph['building']->id, [
        'dorm_id' => $other['dorm']->id, 'code' => 'OTHER', 'name' => 'Moved', 'is_active' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('dorm_id');
    $this->putJson('/admin/rooms/'.$graph['room']->id, [
        'floor_id' => $other['floor']->id, 'number' => '999', 'capacity' => 2, 'is_active' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('floor_id');
});

test('hierarchy unique validation and fixed room capacity reject invalid requests', function () {
    $graph = managementGraph();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->postJson('/admin/dorms', ['code' => 'A', 'name' => 'Duplicate', 'is_active' => 1])->assertForbidden();
    $this->postJson('/admin/buildings', ['dorm_id' => $graph['dorm']->id, 'code' => 'B', 'name' => 'Duplicate', 'is_active' => 1])->assertUnprocessable();
    $this->postJson('/admin/floors', ['building_id' => $graph['building']->id, 'number' => 1])->assertUnprocessable();
    $this->postJson('/admin/rooms', ['floor_id' => $graph['floor']->id, 'number' => '101', 'capacity' => 2, 'is_active' => 1])->assertUnprocessable();
    $this->postJson('/admin/rooms', ['floor_id' => $graph['floor']->id, 'number' => '102', 'capacity' => 3, 'is_active' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('capacity');
    $this->putJson('/admin/rooms/'.$graph['room']->id, ['floor_id' => $graph['floor']->id, 'number' => '101', 'capacity' => 1, 'is_active' => 1])->assertUnprocessable();
});

test('member CRUD supports editing status and preserves roles and Google identifiers', function () {
    $graph = managementGraph();
    $actor = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($actor)->get('/admin/users/create')->assertOk();
    $data = managementUserData($graph);
    $this->post('/admin/users', $data)->assertRedirect();
    $member = User::where('student_id', '001-0')->firstOrFail();
    expect($member->role)->toBe('user')->and($member->password)->toBeNull()->and($member->qr_token)->not->toBeNull();
    $this->get('/admin/users/'.$member->id)->assertOk()->assertSee('0800000000');
    $this->get('/admin/users/'.$member->id.'/edit')->assertOk();
    $data['name'] = 'Edited Member';
    $data['phone'] = '0812345678';
    $data['is_active'] = 0;
    $this->put('/admin/users/'.$member->id, $data)->assertRedirect();
    expect($member->fresh()->name)->toBe('Edited Member')->and($member->fresh()->is_active)->toBeFalse()
        ->and($member->fresh()->role)->toBe('user');
    $this->delete('/admin/users/'.$member->id)->assertRedirect(route('admin.users.index'));
    expect(User::find($member->id))->toBeNull()->and(AuditLog::count())->toBe(3);
});

test('third active occupant is blocked but deactivation frees room space', function () {
    $graph = managementGraph();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    foreach (['001', '002'] as $number) {
        $this->post('/admin/users', managementUserData($graph, $number))->assertRedirect();
    }
    $this->postJson('/admin/users', managementUserData($graph, '003'))->assertUnprocessable()->assertJsonValidationErrors('room_id');
    expect(User::where('room_id', $graph['room']->id)->where('is_active', true)->count())->toBe(2);
    $member = User::where('student_id', '001-0')->firstOrFail();
    $data = managementUserData($graph, '001');
    $data['is_active'] = 0;
    $this->put('/admin/users/'.$member->id, $data)->assertRedirect();
    $this->post('/admin/users', managementUserData($graph, '003'))->assertRedirect();
    $data['is_active'] = 1;
    $this->putJson('/admin/users/'.$member->id, $data)->assertUnprocessable()->assertJsonValidationErrors('room_id');
});

test('member room must match dorm and active members cannot use closed rooms', function () {
    $graph = managementGraph();
    $other = managementGraph('B');
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $data = managementUserData($graph);
    $data['room_id'] = $other['room']->id;
    $this->postJson('/admin/users', $data)->assertUnprocessable()->assertJsonValidationErrors('room_id');
    $graph['room']->is_active = false;
    $graph['room']->save();
    $this->postJson('/admin/users', managementUserData($graph))->assertUnprocessable();
});

test('admin cannot move members across dorms or change protected accounts', function () {
    $graph = managementGraph();
    $other = managementGraph('B');
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $graph['dorm']->id]);
    $member = User::factory()->create(managementUserData($graph));
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->postJson('/admin/users', managementUserData($other, '002'))->assertForbidden();
    $this->putJson('/admin/users/'.$member->id, managementUserData($other))->assertForbidden();
    foreach ([$admin, $superadmin] as $target) {
        $this->get('/admin/users/'.$target->id.'/edit')->assertForbidden();
        $this->putJson('/admin/users/'.$target->id, [])->assertForbidden();
        $this->delete('/admin/users/'.$target->id)->assertForbidden();
    }
});

test('user forms reject role password and identifier escalation and malformed email', function () {
    $graph = managementGraph();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    foreach (['role' => 'superadmin', 'password' => 'secret', 'google_id' => 'fake', 'qr_token' => 'fake'] as $field => $value) {
        $this->postJson('/admin/users', array_merge(managementUserData($graph), [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->postJson('/admin/users', array_merge(managementUserData($graph), ['email' => ['bad']]))->assertUnprocessable();
});

test('linked Google email is immutable and superadmin cannot deactivate or delete self', function () {
    $graph = managementGraph();
    $actor = User::factory()->create(['role' => 'superadmin']);
    $member = User::factory()->create(array_merge(managementUserData($graph), ['google_id' => 'verified-subject']));
    $this->actingAs($actor);
    $data = managementUserData($graph);
    $data['email'] = 'new@kkumail.com';
    $this->putJson('/admin/users/'.$member->id, $data)->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->putJson('/admin/users/'.$actor->id, ['name' => $actor->name, 'email' => $actor->email, 'is_active' => 0])->assertUnprocessable();
    $this->delete('/admin/users/'.$actor->id)->assertForbidden();
});

test('member history blocks deletion and rejected deletion leaves no audit', function () {
    $graph = managementGraph();
    $actor = User::factory()->create(['role' => 'superadmin']);
    $member = User::factory()->create(managementUserData($graph));
    $year = AcademicYear::create(['year' => 2569]);
    ScoreHistory::create(['user_id' => $member->id, 'academic_year_id' => $year->id, 'score' => 5, 'reason' => 'Sample', 'created_by' => $actor->id]);
    $this->actingAs($actor)->delete('/admin/users/'.$member->id)->assertRedirect()->assertSessionHas('error');
    expect($member->fresh())->not->toBeNull()->and(AuditLog::count())->toBe(0);
    $this->delete('/admin/rooms/'.$graph['room']->id)->assertRedirect()->assertSessionHas('error');
});

test('search and filters stay within admin scope even with grouped OR matches', function () {
    $graph = managementGraph();
    $other = managementGraph('B');
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $graph['dorm']->id]);
    $member = User::factory()->create(array_merge(managementUserData($graph), ['name' => 'Unique Own', 'is_active' => false]));
    $outsider = User::factory()->create(array_merge(managementUserData($other, '002'), ['name' => 'Unique Outsider']));
    $this->actingAs($admin)->get('/admin/users?search=Unique')->assertOk()->assertSee('Unique Own')->assertDontSee('Unique Outsider');
    $this->get('/admin/users?search=Unique&status=active')->assertOk()->assertDontSee('Unique Own');
    $this->get('/admin/users?dorm_id='.$other['dorm']->id)->assertOk()->assertDontSee('Unique Outsider');
    $this->get('/admin/buildings?search=Building')->assertOk()->assertSee('Building A')->assertDontSee('Building B');
    $this->get('/admin/rooms?floor_id='.$other['floor']->id)->assertOk()->assertSee('ไม่พบข้อมูล');
    $this->get('/admin/floors?building_id='.$graph['building']->id)->assertOk();
});

test('management forms validate CSRF for mutations', function () {
    $actor = User::factory()->create(['role' => 'superadmin']);
    $this->app['env'] = 'production';
    $this->actingAs($actor)->postJson('/admin/dorms', ['code' => 'A', 'name' => 'Dorm', 'is_active' => 1])->assertStatus(419);
    $this->postJson('/admin/users', [])->assertStatus(419);
});

test('room view counts occupants without leaking protected account information to admin', function () {
    $graph = managementGraph();
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $graph['dorm']->id]);
    User::factory()->create(array_merge(managementUserData($graph), ['name' => 'Visible Member']));
    User::factory()->create(['role' => 'superadmin', 'dorm_id' => $graph['dorm']->id, 'room_id' => $graph['room']->id, 'name' => 'Protected Superadmin']);
    $this->actingAs($admin)->get('/admin/rooms/'.$graph['room']->id)->assertOk()
        ->assertSee('Visible Member')->assertDontSee('Protected Superadmin')->assertSee('2 / 2 คน');
});

test('hierarchy rejects malformed parent ids without executing an invalid uniqueness query', function (string $resource, string $parentField) {
    $graph = managementGraph();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $payload = ['code' => 'NEW', 'name' => 'New record', 'number' => '2', 'capacity' => 2, 'is_active' => true];
    $before = [Building::count(), Floor::count(), Room::count()];
    foreach ([['invalid'], [['invalid']], null, 'not-an-id'] as $parentId) {
        $this->postJson('/admin/'.$resource, [...$payload, $parentField => $parentId])
            ->assertUnprocessable()->assertJsonValidationErrors($parentField);
    }
    expect([Building::count(), Floor::count(), Room::count()])->toBe($before);
})->with([['buildings', 'dorm_id'], ['floors', 'building_id'], ['rooms', 'floor_id']]);
