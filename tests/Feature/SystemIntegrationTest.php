<?php

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Complaint;
use App\Models\Dorm;
use App\Models\FinancialTransaction;
use App\Models\Floor;
use App\Models\RepairRequest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function systemFixture(): array
{
    $dorm = Dorm::create(['code' => 'H8', 'name' => 'Dorm 8']);
    $year = AcademicYear::create(['year' => 2569, 'is_active' => true]);
    $building = Building::create(['dorm_id' => $dorm->id, 'code' => 'A', 'name' => 'Building A']);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3]);
    $room = Room::create(['floor_id' => $floor->id, 'number' => '304', 'capacity' => 2]);
    $member = User::factory()->create(['dorm_id' => $dorm->id, 'room_id' => $room->id, 'student_id' => 'TEST-1']);
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $superadmin = User::factory()->create(['role' => 'superadmin', 'dorm_id' => $dorm->id]);
    $activity = Activity::create([
        'dorm_id' => $dorm->id, 'academic_year_id' => $year->id, 'title' => 'System activity', 'location' => 'Hall',
        'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'score' => 10, 'status' => 'open', 'created_by' => $admin->id,
    ]);
    $complaint = Complaint::create(['user_id' => $member->id, 'dorm_id' => $dorm->id, 'title' => 'Noise', 'description' => 'Details']);
    $repair = RepairRequest::create([
        'user_id' => $member->id, 'dorm_id' => $dorm->id, 'room_id' => $room->id, 'reporter_name' => $member->name,
        'reporter_type' => 'student', 'room_label' => '304', 'category' => 'electrical', 'description' => 'Light',
        'phone' => '0800000000', 'email' => $member->email, 'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '10:00',
    ]);
    $finance = FinancialTransaction::create([
        'dorm_id' => $dorm->id, 'academic_year_id' => $year->id, 'user_id' => $member->id, 'type' => 'income', 'category' => 'other',
        'title' => 'System income', 'amount' => '100.00', 'transaction_date' => now()->toDateString(), 'status' => 'posted', 'is_public' => true, 'created_by' => $admin->id,
    ]);
    $audit = AuditLog::create(['actor_id' => $admin->id, 'action' => 'finance.created', 'target_type' => FinancialTransaction::class, 'target_id' => $finance->id]);

    return compact('dorm', 'year', 'building', 'floor', 'room', 'member', 'admin', 'superadmin', 'activity', 'complaint', 'repair', 'finance', 'audit');
}

function systemRouteParameters(array $fixture): array
{
    return ['user' => $fixture['member']->id, 'dorm' => $fixture['dorm']->id, 'building' => $fixture['building']->id,
        'floor' => $fixture['floor']->id, 'room' => $fixture['room']->id, 'activity' => $fixture['activity']->id,
        'complaint' => $fixture['complaint']->id, 'repair' => $fixture['repair']->id, 'finance' => $fixture['finance']->id,
        'auditLog' => $fixture['audit']->id];
}

test('every protected route rejects guests before reading or changing data', function () {
    $f = systemFixture();
    $parameters = systemRouteParameters($f);
    $count = 0;
    foreach (Route::getRoutes() as $route) {
        if (! $route->getName() || ! in_array('auth', $route->gatherMiddleware(), true)) {
            continue;
        }
        $url = route($route->getName(), array_intersect_key($parameters, array_flip($route->parameterNames())));
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $this->call($method, $url)->assertRedirect(route('login'));
            $count++;
        }
    }
    expect($count)->toBeGreaterThan(80)->and(AuditLog::count())->toBe(1)
        ->and(Attendance::count())->toBe(0)->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

test('every management route rejects ordinary users and every superadmin route rejects admin', function () {
    $f = systemFixture();
    $parameters = systemRouteParameters($f);
    foreach ([$f['member'], $f['admin']] as $actor) {
        $this->actingAs($actor);
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? '';
            if (! str_starts_with($name, 'superadmin.') && ! ($actor->role === 'user' && str_starts_with($name, 'admin.'))) {
                continue;
            }
            $url = route($name, array_intersect_key($parameters, array_flip($route->parameterNames())));
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->call($method, $url)->assertForbidden();
            }
        }
    }
    expect($f['member']->fresh()->role)->toBe('user')->and(AuditLog::count())->toBe(1)
        ->and(Attendance::count())->toBe(0)->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

test('admin can render all permitted management screens and missing records return 404', function () {
    $f = systemFixture();
    $parameters = systemRouteParameters($f);
    $this->actingAs($f['admin']);
    foreach (Route::getRoutes() as $route) {
        $name = $route->getName() ?? '';
        if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)) {
            continue;
        }
        if ($name === 'admin.dorms.create') {
            continue;
        }
        $url = route($name, array_intersect_key($parameters, array_flip($route->parameterNames())));
        if ($name === 'admin.finance.create') {
            $url .= '?type=income';
        }
        $this->get($url)->assertOk();
        if ($route->parameterNames() !== []) {
            $this->get(route($name, array_fill_keys($route->parameterNames(), 999999)))->assertNotFound();
        }
    }
    foreach (['/admin/users?search[]=invalid', '/admin/activities?academic_year_id[]=invalid', '/admin/finance?status=invalid', '/dashboard?academic_year_id=999999'] as $url) {
        $this->getJson($url)->assertUnprocessable();
    }
    $this->get('/missing-system-route')->assertNotFound();
    $this->post('/dashboard')->assertStatus(405);
});

test('staff and member workflow keeps attendance scores requests finance audit and dashboards consistent', function (string $role) {
    $f = systemFixture();
    $staff = $f[$role];
    $this->actingAs($staff)->post('/admin/users', [
        'student_id' => 'TEST-2', 'name' => 'Workflow Member', 'email' => 'workflow@kkumail.com',
        'phone' => '0800000000', 'dorm_id' => $f['dorm']->id, 'room_id' => $f['room']->id, 'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $member = User::where('email', 'workflow@kkumail.com')->firstOrFail();
    $this->actingAs($member)->get('/me/qr')->assertOk();
    $this->get('/me/qr/image')->assertOk();
    $token = $member->fresh()->qr_token;
    $this->post('/complaints', ['title' => 'Workflow complaint', 'description' => 'Noise'])->assertRedirect()->assertSessionHasNoErrors();
    $complaint = $member->complaints()->firstOrFail();
    $this->post('/repairs', [
        'reporter_name' => $member->name, 'reporter_type' => 'student', 'room_label' => '304', 'category' => 'electrical',
        'description' => 'Light failure', 'phone' => '0800000000', 'email' => $member->email,
        'appointment_date' => now('Asia/Bangkok')->addDay()->toDateString(), 'appointment_time' => '10:00',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $repair = $member->repairRequests()->firstOrFail();
    $this->actingAs($staff)->postJson('/admin/activities/'.$f['activity']->id.'/attendances/preview', ['qr_token' => $token])->assertOk();
    $this->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $token])->assertCreated();
    $this->postJson('/admin/activities/'.$f['activity']->id.'/attendances', ['qr_token' => $token])->assertConflict();
    $this->post('/admin/users/'.$member->id.'/scores', ['academic_year_id' => $f['year']->id, 'score' => -3, 'reason' => 'Correction'])->assertRedirect()->assertSessionHasNoErrors();
    $this->put('/admin/complaints/'.$complaint->id, ['status' => 'completed', 'assigned_to' => $f['admin']->id, 'note' => 'Resolved'])->assertRedirect()->assertSessionHasNoErrors();
    $this->put('/admin/repairs/'.$repair->id, ['status' => 'completed'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/admin/finance', [
        'type' => 'expense', 'category' => 'maintenance', 'academic_year_id' => $f['year']->id, 'title' => 'Repair cost',
        'amount' => '25.25', 'transaction_date' => now()->toDateString(), 'description' => 'Internal note', 'status' => 'posted', 'is_public' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/dashboard')->assertOk()->assertViewHas('totals', ['income' => '100.00', 'expense' => '25.25', 'balance' => '74.75']);
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertViewHas('score', 7)->assertViewHas('attendanceCount', 1);
    $this->get('/complaints/'.$complaint->id)->assertOk()->assertSee('Resolved');
    $this->get('/repairs/'.$repair->id)->assertOk()->assertSee('เสร็จสิ้น');
    $this->get('/finance')->assertOk()->assertViewHas('totals', ['income' => '100.00', 'expense' => '25.25', 'balance' => '74.75'])->assertDontSee('Internal note');
    $this->actingAs($f['superadmin'])->get('/superadmin/audit-logs')->assertOk();
    $this->patch('/superadmin/users/'.$member->id.'/role', ['role' => 'admin'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($member)->get('/admin/activities')->assertOk();
    $this->actingAs($f['superadmin'])->patch('/superadmin/users/'.$member->id.'/role', ['role' => 'user'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($member)->get('/admin/activities')->assertForbidden();
    expect($member->attendances()->count())->toBe(1)->and($member->scoreHistories()->count())->toBe(2)
        ->and($member->scoreHistories()->sum('score'))->toBe(7)->and($complaint->fresh()->status)->toBe('completed')
        ->and($repair->fresh()->status)->toBe('completed')->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    foreach (['user.created', 'complaint.created', 'repair.created', 'attendance.created', 'score.adjusted', 'complaint.updated', 'repair.status_updated', 'finance.created', 'user.role_changed'] as $action) {
        expect(AuditLog::where('action', $action)->exists())->toBeTrue();
    }
})->with(['admin', 'superadmin']);
