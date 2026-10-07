<?php

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Building;
use App\Models\Complaint;
use App\Models\Dorm;
use App\Models\FinancialTransaction;
use App\Models\Floor;
use App\Models\RepairRequest;
use App\Models\Room;
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardFixture(): array
{
    $dorm = Dorm::create(['code' => 'H8', 'name' => 'Dorm 8']);
    $other = Dorm::create(['code' => 'OTHER', 'name' => 'Other Dorm']);
    $year = AcademicYear::create(['year' => 2569, 'is_active' => true]);
    $next = AcademicYear::create(['year' => 2570]);
    $building = Building::create(['dorm_id' => $dorm->id, 'code' => 'A', 'name' => 'Building A']);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3]);
    $room = Room::create(['floor_id' => $floor->id, 'number' => '304', 'capacity' => 2]);
    $member = User::factory()->create(['name' => 'SELF MEMBER', 'dorm_id' => $dorm->id, 'room_id' => $room->id]);
    $peer = User::factory()->create(['name' => 'PRIVATE PEER NAME', 'dorm_id' => $dorm->id, 'room_id' => $room->id]);
    $admin = User::factory()->create(['name' => 'PRIVATE ADMIN NAME', 'role' => 'admin', 'dorm_id' => $dorm->id]);
    $superadmin = User::factory()->create(['role' => 'superadmin', 'dorm_id' => $dorm->id]);
    $outsider = User::factory()->create(['dorm_id' => $other->id]);
    $otherAdmin = User::factory()->create(['role' => 'admin', 'dorm_id' => $other->id]);
    $activities = [];
    foreach ([[$dorm, 'open', 'OWN OPEN'], [$dorm, 'draft', 'HIDDEN DRAFT'], [$other, 'open', 'OTHER SECRET']] as [$activityDorm, $status, $title]) {
        $activities[] = Activity::create([
            'dorm_id' => $activityDorm->id, 'academic_year_id' => $year->id, 'title' => $title, 'location' => 'Hall',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'score' => 10, 'status' => $status, 'created_by' => $admin->id,
        ]);
    }
    foreach ([$member, $peer, $superadmin, $outsider] as $person) {
        Attendance::create(['user_id' => $person->id, 'activity_id' => $person->id === $outsider->id ? $activities[2]->id : $activities[0]->id, 'checked_in_by' => $admin->id, 'checked_in_at' => now()]);
    }
    foreach ([[$member, 7], [$member, -2], [$peer, 50], [$superadmin, 100], [$outsider, 5000]] as [$person, $score]) {
        ScoreHistory::create(['user_id' => $person->id, 'academic_year_id' => $year->id, 'score' => $score, 'reason' => 'Adjustment', 'created_by' => $admin->id]);
    }
    foreach ([[$member, 'pending', 'OWN COMPLAINT'], [$member, 'completed', 'OWN COMPLETED'], [$peer, 'pending', 'PRIVATE PEER COMPLAINT'], [$superadmin, 'pending', 'SUPER COMPLAINT'], [$outsider, 'pending', 'OTHER COMPLAINT']] as [$person, $status, $title]) {
        Complaint::create(['user_id' => $person->id, 'dorm_id' => $person->dorm_id, 'title' => $title, 'description' => 'Details', 'status' => $status]);
    }
    foreach ([[$member, 'new'], [$peer, 'completed'], [$superadmin, 'new'], [$outsider, 'new']] as [$person, $status]) {
        RepairRequest::create([
            'user_id' => $person->id, 'dorm_id' => $person->dorm_id, 'reporter_name' => $person->name, 'reporter_type' => 'student',
            'room_label' => $person->id === $member->id ? 'OWN REPAIR ROOM' : 'PRIVATE REPAIR ROOM', 'category' => 'electrical',
            'description' => 'Broken light', 'phone' => '0800000000', 'email' => $person->email,
            'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '10:00', 'status' => $status,
        ]);
    }
    foreach ([[$member, 'income', '100.10', true, 'posted'], [$peer, 'income', '200.20', false, 'posted'], [$member, 'expense', '20.05', true, 'posted'], [$superadmin, 'income', '50.00', false, 'posted'], [$outsider, 'income', '1000.00', false, 'posted'], [$member, 'income', '900.00', true, 'pending']] as [$person, $type, $amount, $public, $status]) {
        FinancialTransaction::create([
            'dorm_id' => $person->dorm_id, 'academic_year_id' => $year->id, 'user_id' => $type === 'income' ? $person->id : null,
            'type' => $type, 'category' => 'other', 'title' => 'Transaction', 'amount' => $amount,
            'transaction_date' => '2026-10-07', 'status' => $status, 'is_public' => $public, 'created_by' => $admin->id,
        ]);
    }

    return compact('dorm', 'other', 'year', 'next', 'member', 'peer', 'admin', 'superadmin', 'outsider', 'otherAdmin', 'room', 'activities');
}

test('superadmin dashboard includes all required statistics and posted financial totals', function () {
    $f = dashboardFixture();
    $response = $this->actingAs($f['superadmin'])->get('/dashboard')->assertOk()->assertViewIs('dashboards.superadmin');
    $cards = collect($response->viewData('cards'))->pluck('value', 'label')->all();
    expect($cards)->toBe(['สมาชิก' => 6, 'Admin' => 2, 'กิจกรรม' => 3, 'Attendance' => 4, 'คะแนนรวม' => 5155, 'เรื่องร้องเรียน' => 5, 'งานแจ้งซ่อม' => 4]);
    $response->assertViewHas('totals', ['income' => '1350.30', 'expense' => '20.05', 'balance' => '1330.25'])
        ->assertViewHas('openComplaints', 4)->assertViewHas('openRepairs', 3)->assertSee('จัดการสิทธิ์ Admin');
});

test('admin dashboard summaries match backend management scope and exclude protected records', function () {
    $f = dashboardFixture();
    $response = $this->actingAs($f['admin'])->get('/dashboard')->assertOk()->assertViewIs('dashboards.admin');
    $cards = collect($response->viewData('cards'))->pluck('value', 'label')->all();
    expect($cards)->toBe(['สมาชิก' => 2, 'กิจกรรม' => 2, 'Attendance' => 2, 'คะแนนรวม' => 55, 'เรื่องร้องเรียน' => 3, 'งานแจ้งซ่อม' => 2, 'ห้องพัก' => 1]);
    $response->assertViewHas('totals', ['income' => '300.30', 'expense' => '20.05', 'balance' => '280.25'])
        ->assertViewHas('openComplaints', 2)->assertViewHas('openRepairs', 1)->assertDontSee('จัดการสิทธิ์ Admin')->assertDontSee('Other Dorm');
});

test('user dashboard shows own score room QR requests and only public finance', function () {
    $f = dashboardFixture();
    $this->actingAs($f['member'])->get('/dashboard?role=superadmin&user_id='.$f['peer']->id.'&dorm_id='.$f['other']->id)
        ->assertOk()->assertViewIs('dashboards.user')->assertViewHas('score', 5)->assertViewHas('attendanceCount', 1)
        ->assertViewHas('complaintCount', 2)->assertViewHas('repairCount', 1)
        ->assertViewHas('totals', ['income' => '100.10', 'expense' => '20.05', 'balance' => '80.05'])
        ->assertSee('304')->assertSee('ห้องละ 2 คน')->assertSee(route('member.qr.image'), false)
        ->assertSee('OWN OPEN')->assertSee('OWN COMPLAINT')->assertSee('OWN REPAIR ROOM')
        ->assertDontSee('PRIVATE PEER NAME')->assertDontSee('PRIVATE ADMIN NAME')->assertDontSee('PRIVATE PEER COMPLAINT')
        ->assertDontSee('PRIVATE REPAIR ROOM')->assertDontSee('SUPER COMPLAINT')->assertDontSee('OTHER SECRET')
        ->assertDontSee('HIDDEN DRAFT')->assertDontSee('จัดการการเงิน')->assertDontSee('จัดการสิทธิ์ Admin');
});

test('academic year dashboard filter scopes score activity attendance and finances without losing request history', function () {
    $f = dashboardFixture();
    ScoreHistory::create(['user_id' => $f['member']->id, 'academic_year_id' => $f['next']->id, 'score' => 3, 'reason' => 'Next', 'created_by' => $f['admin']->id]);
    $response = $this->actingAs($f['member'])->get('/dashboard?academic_year_id='.$f['next']->id)->assertOk()
        ->assertViewHas('score', 3)->assertViewHas('attendanceCount', 0)->assertViewHas('complaintCount', 2)
        ->assertViewHas('totals', ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00'])->assertDontSee('OWN OPEN');
    $response->assertSee(route('member.scores', ['academic_year_id' => $f['next']->id]), false);
    $adminResponse = $this->actingAs($f['admin'])->get('/dashboard?academic_year_id='.$f['next']->id)->assertOk();
    $cards = collect($adminResponse->viewData('cards'))->pluck('value', 'label');
    expect($cards['กิจกรรม'])->toBe(0)->and($cards['Attendance'])->toBe(0)->and($cards['คะแนนรวม'])->toBe(3)->and($cards['เรื่องร้องเรียน'])->toBe(3);
    $this->getJson('/dashboard?academic_year_id=99999')->assertUnprocessable();
});

test('dashboard handles empty installation unassigned member and superadmin without dorm', function () {
    $member = User::factory()->create();
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertViewIs('dashboards.user')
        ->assertViewHas('score', 0)->assertViewHas('attendanceCount', 0)->assertSee('ยังไม่กำหนดห้องพัก')
        ->assertSee('ยังไม่มีเรื่องร้องเรียน')->assertSee('ยังไม่มีงานแจ้งซ่อม')
        ->assertViewHas('totals', ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00']);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/dashboard')->assertOk()->assertSee('ยังไม่ตั้งค่าหอพัก');
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/dashboard')->assertForbidden();
});

test('dashboard enforces active known roles and refreshes revoked privileges', function () {
    $f = dashboardFixture();
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->getJson('/dashboard')->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'unknown']))->get('/dashboard')->assertForbidden();
    $this->actingAs($f['admin'])->get('/dashboard')->assertViewIs('dashboards.admin');
    User::whereKey($f['admin']->id)->update(['role' => 'user']);
    $this->get('/dashboard')->assertOk()->assertViewIs('dashboards.user')->assertDontSee('ข้อมูลบริหารหอพัก');
    User::whereKey($f['admin']->id)->update(['is_active' => false]);
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('dashboard recent user lists are limited to own records and activity availability excludes expired entries', function () {
    $f = dashboardFixture();
    $expired = $f['activities'][0]->replicate();
    $expired->title = 'EXPIRED ACTIVITY';
    $expired->starts_at = now()->subHours(2);
    $expired->ends_at = now()->subHour();
    $expired->save();
    for ($index = 0; $index < 4; $index++) {
        Complaint::create(['user_id' => $f['member']->id, 'dorm_id' => $f['dorm']->id, 'title' => 'Recent '.$index, 'description' => 'Details', 'status' => 'pending']);
    }
    $this->actingAs($f['member'])->get('/dashboard')->assertOk()->assertViewHas('complaintCount', 6)
        ->assertViewHas('recentComplaints', fn ($records) => $records->count() === 3 && $records->every(fn ($record) => $record->user_id === $f['member']->id))
        ->assertSee('Recent 3')->assertDontSee('Recent 0')->assertDontSee('EXPIRED ACTIVITY');
});

test('dashboard escapes user submitted content and follows financial visibility immediately', function () {
    $f = dashboardFixture();
    $f['activities'][0]->update(['title' => '<script>alert(1)</script>']);
    FinancialTransaction::where('dorm_id', $f['dorm']->id)->update(['is_public' => false]);
    $this->actingAs($f['member'])->get('/dashboard')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertViewHas('totals', ['income' => '0.00', 'expense' => '0.00', 'balance' => '0.00']);
});
