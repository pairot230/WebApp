<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Dorm;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PDO;
use RuntimeException;

/** Import sample data without coupling application models to the source schema. */
class DemoMemberSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo import is restricted to local/testing environments.');
        }
        $sourcePath = realpath((string) config('dorm.demo_database'));
        $mappingPath = realpath((string) config('dorm.demo_room_mapping'));
        if (! $sourcePath || ! $mappingPath || ! is_file($sourcePath) || ! is_file($mappingPath)) {
            throw new RuntimeException('Set DORM_DEMO_DATABASE and DORM_DEMO_ROOM_MAPPING to existing files.');
        }
        if ($sourcePath === realpath((string) config('database.connections.sqlite.database'))) {
            throw new RuntimeException('Demo source must be separate from the application database.');
        }
        $mapping = json_decode(file_get_contents($mappingPath), true, 512, JSON_THROW_ON_ERROR);
        Validator::make($mapping, [
            'dorm_code' => ['required', 'string', 'max:255'],
            'dorm_name' => ['required', 'string', 'max:255'],
            'building_code' => ['required', 'string', 'max:255'],
            'building_name' => ['required', 'string', 'max:255'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.number' => ['required', 'string', 'max:255', 'distinct'],
            'rooms.*.floor' => ['required', 'integer', 'min:0', 'max:65535'],
            'rooms.*.capacity' => ['required', 'integer', Rule::in([Room::CAPACITY])],
        ])->validate();

        $roomMapping = collect($mapping['rooms'])->keyBy('number');
        $source = new PDO('sqlite:file:'.str_replace('\\', '/', $sourcePath).'?mode=ro');
        $source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $source->exec('PRAGMA query_only = ON');
        $students = $source->query('SELECT room, student_id, first_name, last_name, faculty, kku_mail FROM students')->fetchAll(PDO::FETCH_ASSOC);
        $emails = [];
        $studentIds = [];
        foreach ($students as $index => &$student) {
            $student['kku_mail'] = strtolower(trim($student['kku_mail']));
            Validator::make($student, [
                'room' => ['required', 'string'],
                'student_id' => ['required', 'string', 'max:255'],
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'faculty' => ['required', 'string', 'max:255'],
                'kku_mail' => ['required', 'email', 'max:255'],
            ])->validate();
            if (! $roomMapping->has($student['room'])) {
                throw new RuntimeException('Missing explicit room mapping at sample row '.($index + 1));
            }
            if (isset($emails[$student['kku_mail']]) || isset($studentIds[$student['student_id']])) {
                throw new RuntimeException('Duplicate email/student ID in sample source.');
            }
            $emails[$student['kku_mail']] = true;
            $studentIds[$student['student_id']] = true;
        }
        unset($student);

        DB::transaction(function () use ($mapping, $students): void {
            if (Dorm::where('code', '!=', $mapping['dorm_code'])->exists()) {
                throw new RuntimeException('ระบบนี้ใช้สำหรับหอพักเดียว กรุณาใช้ mapping ของหอที่ตั้งค่าไว้');
            }
            $dorm = Dorm::firstOrNew(['code' => $mapping['dorm_code']]);
            if (! $dorm->exists) {
                $dorm->name = $mapping['dorm_name'];
                $dorm->save();
            }
            $building = Building::firstOrNew(['dorm_id' => $dorm->id, 'code' => $mapping['building_code']]);
            if (! $building->exists) {
                $building->name = $mapping['building_name'];
                $building->save();
            }
            $rooms = [];
            foreach ($mapping['rooms'] as $item) {
                $floor = Floor::firstOrNew(['building_id' => $building->id, 'number' => $item['floor']]);
                $floor->save();
                $room = Room::firstOrNew(['floor_id' => $floor->id, 'number' => $item['number']]);
                if (! $room->exists) {
                    $room->capacity = $item['capacity'];
                    $room->save();
                }
                $rooms[$item['number']] = $room;
            }
            foreach ($students as $student) {
                $byId = User::where('student_id', $student['student_id'])->first();
                $byEmail = User::where('email', $student['kku_mail'])->first();
                if (($byId && $byId->email !== $student['kku_mail']) || ($byEmail && $byEmail->student_id !== $student['student_id'])) {
                    throw new RuntimeException('Existing member identity conflict; no data imported.');
                }
                if ($byId || $byEmail) {
                    continue;
                }
                $user = new User;
                $user->student_id = $student['student_id'];
                $user->email = $student['kku_mail'];
                $user->first_name = $student['first_name'];
                $user->last_name = $student['last_name'];
                $user->name = $student['first_name'].' '.$student['last_name'];
                $user->faculty = $student['faculty'];
                $user->dorm_id = $dorm->id;
                $user->room_id = $rooms[$student['room']]->id;
                $user->role = 'user';
                $user->qr_token = (string) Str::uuid();
                $user->save();
            }
        });
    }
}
