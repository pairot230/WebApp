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
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('all application screens render long content and pagination with role appropriate navigation', function () {
    $preview = getenv('UI_PREVIEW') === '1';
    $directory = storage_path('framework/testing/responsive');
    $screens = [];
    $capture = function (string $name, string $url) use ($preview, $directory, &$screens): void {
        $response = $this->get($url)->assertOk()
            ->assertSee('name="viewport"', false);
        if ($preview) {
            File::ensureDirectoryExists($directory);
            $html = str_replace([url('/css/'), url('/js/'), route('member.qr.image')], ['/css/', '/js/', '/qr.svg'], $response->getContent());
            File::put($directory.'/'.$name.'.html', $html);
            $screens[] = $name;
        }
    };
    $capture('login', '/login');
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin);
    $capture('dorm-create', '/admin/dorms/create');
    $long = str_repeat('รายละเอียดทดสอบ', 15).' '.str_repeat('LongTextWithoutSpaces', 12);
    $dorm = Dorm::create(['code' => 'H8', 'name' => 'หอพัก 8', 'description' => $long]);
    $year = AcademicYear::create(['year' => 2569, 'is_active' => true]);
    $building = Building::create(['dorm_id' => $dorm->id, 'code' => 'A', 'name' => 'อาคารทดสอบ']);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 3, 'name' => $long]);
    $room = Room::create(['floor_id' => $floor->id, 'number' => '304', 'capacity' => 2]);
    $superadmin->forceFill(['dorm_id' => $dorm->id])->save();
    $admin = User::factory()->create(['role' => 'admin', 'dorm_id' => $dorm->id]);
    $member = User::factory()->create([
        'name' => 'สมาชิกทดสอบ '.$long, 'email' => str_repeat('longemail', 15).'@kkumail.com',
        'student_id' => '673380520-8', 'phone' => '0800000000', 'dorm_id' => $dorm->id,
        'room_id' => $room->id, 'qr_token' => (string) Str::uuid(),
    ]);
    User::factory()->count(26)->create(['dorm_id' => $dorm->id]);
    $activity = Activity::create([
        'dorm_id' => $dorm->id, 'academic_year_id' => $year->id, 'title' => $long,
        'description' => $long, 'location' => $long, 'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(), 'score' => 10, 'status' => 'open', 'created_by' => $admin->id,
    ]);
    $attendance = Attendance::create(['user_id' => $member->id, 'activity_id' => $activity->id, 'checked_in_by' => $admin->id, 'checked_in_at' => now()]);
    ScoreHistory::create(['user_id' => $member->id, 'academic_year_id' => $year->id, 'activity_id' => $activity->id, 'attendance_id' => $attendance->id, 'score' => 10, 'reason' => $long, 'created_by' => $admin->id]);
    $complaint = Complaint::create(['user_id' => $member->id, 'dorm_id' => $dorm->id, 'title' => $long, 'description' => $long]);
    $repair = RepairRequest::create([
        'user_id' => $member->id, 'dorm_id' => $dorm->id, 'room_id' => $room->id,
        'reporter_name' => $member->name, 'reporter_type' => 'student', 'room_label' => '304',
        'category' => 'electrical', 'description' => $long, 'phone' => $member->phone, 'email' => $member->email,
        'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '10:00',
    ]);
    $finance = null;
    for ($i = 0; $i < 26; $i++) {
        $finance = FinancialTransaction::create([
            'dorm_id' => $dorm->id, 'academic_year_id' => $year->id, 'user_id' => $member->id,
            'type' => 'income', 'category' => 'other', 'title' => $long, 'description' => $long,
            'amount' => '9999999999.99', 'transaction_date' => now()->toDateString(),
            'status' => 'posted', 'is_public' => true, 'created_by' => $admin->id,
        ]);
    }
    $audit = AuditLog::create(['actor_id' => $admin->id, 'action' => 'finance.updated', 'target_type' => FinancialTransaction::class, 'target_id' => $finance->id, 'old_values' => ['title' => $long], 'new_values' => ['title' => $long.' changed']]);
    $this->actingAs($superadmin);
    $capture('superadmin-dashboard', '/dashboard');
    foreach (['users' => $member, 'dorms' => $dorm, 'buildings' => $building, 'floors' => $floor, 'rooms' => $room, 'activities' => $activity, 'finance' => $finance] as $resource => $record) {
        $capture($resource.'-index', '/admin/'.$resource);
        $capture($resource.'-show', '/admin/'.$resource.'/'.$record->id);
        $capture($resource.'-edit', '/admin/'.$resource.'/'.$record->id.'/edit');
        if ($resource !== 'dorms') {
            $capture($resource.'-create', '/admin/'.$resource.'/create'.($resource === 'finance' ? '?type=income' : ''));
        }
    }
    $capture('expense-create', '/admin/finance/create?type=expense');
    foreach (['complaints' => $complaint, 'repairs' => $repair] as $resource => $record) {
        $capture('admin-'.$resource.'-index', '/admin/'.$resource);
        $capture('admin-'.$resource.'-show', '/admin/'.$resource.'/'.$record->id);
    }
    $capture('score-adjustment', '/admin/users/'.$member->id.'/scores');
    $capture('scanner', '/admin/activities/'.$activity->id.'/attendances');
    $capture('admin-roles', '/superadmin/admins');
    $capture('audit-index', '/superadmin/audit-logs');
    $capture('audit-show', '/superadmin/audit-logs/'.$audit->id);
    $this->get('/admin/users')->assertSee('เปลี่ยนหน้ารายการ')->assertSee('rel="next"', false);
    $this->actingAs($admin);
    $capture('admin-dashboard', '/dashboard');
    $this->get('/dashboard')->assertDontSee('Audit Log')->assertDontSee('จัดการสิทธิ์ Admin');
    $this->actingAs($member);
    foreach (['dashboard' => '/dashboard', 'profile' => '/me', 'qr' => '/me/qr', 'scores' => '/me/scores', 'activities' => '/activities', 'finance' => '/finance'] as $name => $url) {
        $capture('member-'.$name, $url);
    }
    foreach (['complaints' => $complaint, 'repairs' => $repair] as $resource => $record) {
        $capture('member-'.$resource.'-index', '/'.$resource);
        $capture('member-'.$resource.'-create', '/'.$resource.'/create');
        $capture('member-'.$resource.'-show', '/'.$resource.'/'.$record->id);
    }
    $this->get('/dashboard')->assertDontSee('จัดการการเงิน')->assertDontSee('Audit Log');
    if ($preview) {
        File::put($directory.'/qr.svg', $this->get('/me/qr/image')->assertOk()->getContent());
        foreach (['css', 'js'] as $assetDirectory) {
            File::copyDirectory(public_path($assetDirectory), $directory.'/'.$assetDirectory);
        }
        File::put($directory.'/screens.json', json_encode($screens));
    }
});
