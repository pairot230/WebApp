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
use App\Models\MembershipPayment;
use App\Models\RepairRequest;
use App\Models\Room;
use App\Models\ScoreHistory;
use App\Models\User;
use Database\Seeders\AcademicYearSeeder;
use Database\Seeders\DemoMemberSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function dormDatabaseGraph(): array
{
    $year = AcademicYear::create(['year' => 2569]);
    $dorm = Dorm::create(['code' => 'TEST', 'name' => 'Test dorm']);
    $building = Building::create(['dorm_id' => $dorm->id, 'code' => 'A', 'name' => 'Building A']);
    $floor = Floor::create(['building_id' => $building->id, 'number' => 1]);
    $room = Room::create(['floor_id' => $floor->id, 'number' => '001', 'capacity' => 2]);
    $user = User::factory()->create(['dorm_id' => $dorm->id, 'room_id' => $room->id]);
    $activity = Activity::create([
        'dorm_id' => $dorm->id, 'academic_year_id' => $year->id,
        'title' => 'Test activity', 'starts_at' => '2026-10-07 01:00:00',
        'ends_at' => '2026-10-07 02:00:00', 'location' => 'Hall',
        'score' => 5, 'created_by' => $user->id,
    ]);
    $attendance = Attendance::create([
        'user_id' => $user->id, 'activity_id' => $activity->id,
        'checked_in_by' => $user->id, 'checked_in_at' => now(),
    ]);
    $history = ScoreHistory::create([
        'user_id' => $user->id, 'academic_year_id' => $year->id,
        'activity_id' => $activity->id, 'attendance_id' => $attendance->id,
        'score' => 5, 'reason' => 'Attendance', 'created_by' => $user->id,
    ]);
    $complaint = Complaint::create([
        'user_id' => $user->id, 'dorm_id' => $dorm->id,
        'title' => 'Noise', 'description' => 'Test', 'assigned_to' => $user->id,
    ]);
    $repair = RepairRequest::create([
        'user_id' => $user->id, 'dorm_id' => $dorm->id, 'room_id' => $room->id,
        'reporter_name' => 'Test', 'reporter_type' => 'student', 'room_label' => '001',
        'category' => 'electrical', 'description' => 'Light', 'phone' => '0800000000',
        'email' => $user->email, 'appointment_date' => '2026-10-08',
        'appointment_time' => '09:00', 'assigned_to' => $user->id,
    ]);
    $transaction = FinancialTransaction::create([
        'dorm_id' => $dorm->id, 'academic_year_id' => $year->id,
        'user_id' => $user->id, 'type' => 'income', 'category' => 'membership',
        'title' => 'Fee', 'amount' => '200.25', 'transaction_date' => '2026-10-07',
        'created_by' => $user->id, 'voided_by' => $user->id,
    ]);
    $payment = MembershipPayment::create([
        'user_id' => $user->id, 'dorm_id' => $dorm->id, 'academic_year_id' => $year->id,
        'financial_transaction_id' => $transaction->id, 'amount' => '200.25',
    ]);
    $audit = AuditLog::create([
        'actor_id' => $user->id, 'action' => 'test', 'target_type' => User::class,
        'target_id' => $user->id, 'old_values' => ['role' => 'user'],
        'new_values' => ['role' => 'admin'],
    ]);

    return compact('year', 'dorm', 'building', 'floor', 'room', 'user', 'activity', 'attendance', 'history', 'complaint', 'repair', 'transaction', 'payment', 'audit');
}

test('all model relationships resolve populated records and casts preserve data', function () {
    $graph = dormDatabaseGraph();
    foreach ($graph as $model) {
        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== get_class($model)) {
                continue;
            }
            $type = $method->getReturnType();
            if ($type && is_subclass_of((string) $type, Relation::class)) {
                $result = $model->{$method->getName()}()->getResults();
                expect($result)->not->toBeNull();
                if ($result instanceof Collection) {
                    expect($result)->not->toBeEmpty();
                }
            }
        }
    }
    expect($graph['user']->room->floor->building->dorm->id)->toBe($graph['dorm']->id)
        ->and($graph['transaction']->fresh()->amount)->toBe('200.25')
        ->and($graph['audit']->fresh()->old_values)->toBe(['role' => 'user'])
        ->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});

test('database rejects duplicate attendance and duplicate attendance score', function () {
    $graph = dormDatabaseGraph();
    expect(fn () => Attendance::create([
        'user_id' => $graph['user']->id, 'activity_id' => $graph['activity']->id,
        'checked_in_by' => $graph['user']->id, 'checked_in_at' => now(),
    ]))->toThrow(QueryException::class);
    expect(fn () => ScoreHistory::create([
        'user_id' => $graph['user']->id, 'academic_year_id' => $graph['year']->id,
        'attendance_id' => $graph['attendance']->id, 'score' => 5,
        'reason' => 'Duplicate', 'created_by' => $graph['user']->id,
    ]))->toThrow(QueryException::class);
});

test('database rejects orphan foreign keys and deletion of referenced rooms', function () {
    $graph = dormDatabaseGraph();
    expect(fn () => Room::create(['floor_id' => 999999, 'number' => '002']))->toThrow(QueryException::class);
    expect(fn () => $graph['room']->delete())->toThrow(QueryException::class);
});

test('yearly membership and finance link cannot be duplicated', function () {
    $graph = dormDatabaseGraph();
    expect(fn () => MembershipPayment::create([
        'user_id' => $graph['user']->id, 'dorm_id' => $graph['dorm']->id,
        'academic_year_id' => $graph['year']->id, 'amount' => '200.25',
    ]))->toThrow(QueryException::class);
    $other = User::factory()->create();
    expect(fn () => MembershipPayment::create([
        'user_id' => $other->id, 'dorm_id' => $graph['dorm']->id,
        'academic_year_id' => $graph['year']->id, 'amount' => '200.25',
        'financial_transaction_id' => $graph['transaction']->id,
    ]))->toThrow(QueryException::class);
});

test('room identifiers are scoped and nullable manual history supports negative adjustment', function () {
    $graph = dormDatabaseGraph();
    expect(fn () => Room::create(['floor_id' => $graph['floor']->id, 'number' => '001']))->toThrow(QueryException::class);
    $floor = Floor::create(['building_id' => $graph['building']->id, 'number' => 2]);
    $room = Room::create(['floor_id' => $floor->id, 'number' => '001']);
    expect($room->number)->toBe('001');
    for ($i = 0; $i < 2; $i++) {
        ScoreHistory::create([
            'user_id' => $graph['user']->id, 'academic_year_id' => $graph['year']->id,
            'score' => -1, 'reason' => 'Adjustment', 'created_by' => $graph['user']->id,
        ]);
    }
    expect($graph['user']->scoreHistories()->sum('score'))->toBe(3);
});

test('transaction rolls back attendance when score insert fails', function () {
    $graph = dormDatabaseGraph();
    $other = User::factory()->create();
    expect(fn () => DB::transaction(function () use ($graph, $other): void {
        Attendance::create([
            'user_id' => $other->id, 'activity_id' => $graph['activity']->id,
            'checked_in_by' => $graph['user']->id, 'checked_in_at' => now(),
        ]);
        ScoreHistory::create([
            'user_id' => $other->id, 'academic_year_id' => 999999,
            'score' => 5, 'reason' => 'Invalid year', 'created_by' => $graph['user']->id,
        ]);
    }))->toThrow(QueryException::class);
    expect(Attendance::where('user_id', $other->id)->count())->toBe(0);
});

test('baseline and controlled SuperAdmin seeders are repeatable and audited', function () {
    $this->seed(AcademicYearSeeder::class);
    $this->seed(AcademicYearSeeder::class);
    expect(AcademicYear::count())->toBe(1);
    config(['dorm.superadmin_email' => 'pairoj.c@kkumail.com']);
    $user = User::factory()->create(['email' => 'pairoj.c@kkumail.com']);
    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);
    expect($user->fresh()->role)->toBe('superadmin')->and(AuditLog::count())->toBe(1);
});

test('audit survives actor deletion and sensitive identifiers stay hidden', function () {
    $user = User::factory()->create(['google_id' => 'private-google-id', 'qr_token' => 'private-qr']);
    $audit = AuditLog::create([
        'actor_id' => $user->id, 'action' => 'test',
        'target_type' => User::class, 'target_id' => $user->id,
    ]);
    expect($user->toArray())->not->toHaveKeys(['google_id', 'qr_token', 'password']);
    $user->delete();
    expect($audit->fresh()->actor_id)->toBeNull();
});

test('demo import requires mapping preserves source and existing privileged members', function () {
    $sourcePath = tempnam(sys_get_temp_dir(), 'dorm-source-');
    $mappingPath = tempnam(sys_get_temp_dir(), 'dorm-map-');
    try {
        $source = new PDO('sqlite:'.$sourcePath);
        $source->exec('CREATE TABLE students (room TEXT, student_id TEXT, first_name TEXT, last_name TEXT, faculty TEXT, kku_mail TEXT)');
        $statement = $source->prepare('INSERT INTO students VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute(['001', '001-0', 'Sample', 'Member', 'Faculty', 'sample@kkumail.com']);
        $source = null;
        $hash = hash_file('sha256', $sourcePath);
        file_put_contents($mappingPath, json_encode([
            'dorm_code' => 'SAMPLE', 'dorm_name' => 'Sample dorm',
            'building_code' => 'A', 'building_name' => 'Sample building',
            'rooms' => [['number' => '001', 'floor' => 7, 'capacity' => 2]],
        ]));
        config(['dorm.demo_database' => $sourcePath, 'dorm.demo_room_mapping' => $mappingPath]);
        $this->seed(DemoMemberSeeder::class);
        $user = User::firstOrFail();
        $user->role = 'superadmin';
        $user->save();
        $this->seed(DemoMemberSeeder::class);
        expect(User::count())->toBe(1)->and($user->fresh()->role)->toBe('superadmin')
            ->and($user->room->floor->number)->toBe(7)
            ->and(hash_file('sha256', $sourcePath))->toBe($hash);

        $user->email = 'changed@kkumail.com';
        $user->save();
        expect(fn () => $this->seed(DemoMemberSeeder::class))->toThrow(RuntimeException::class);
        expect(User::count())->toBe(1);
    } finally {
        @unlink($sourcePath);
        @unlink($mappingPath);
    }
});
