<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Dorm;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TemporaryMemberSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Temporary members can only be configured locally.');
        }
        DB::transaction(function (): void {
            if (Dorm::count() > 1) {
                throw new RuntimeException('This installation must contain one dorm only.');
            }
            $dorm = Dorm::first() ?? Dorm::create(['code' => 'H8', 'name' => 'หอพัก 8']);
            $building = Building::firstOrCreate(['dorm_id' => $dorm->id, 'code' => 'H8'], ['name' => 'อาคารหอพัก 8']);
            foreach (config('dorm.temporary_members') as $member) {
                $rooms = Room::where('number', $member['room'])->whereHas('floor.building', fn ($query) => $query->where('dorm_id', $dorm->id))->get();
                if ($rooms->count() > 1) {
                    throw new RuntimeException('Ambiguous room number: '.$member['room']);
                }
                $room = $rooms->first();
                if (! $room) {
                    $floor = Floor::firstOrCreate(['building_id' => $building->id, 'number' => (int) substr($member['room'], 0, 1)]);
                    $room = Room::create(['floor_id' => $floor->id, 'number' => $member['room'], 'capacity' => Room::CAPACITY]);
                }
                $user = User::firstOrNew(['email' => $member['email']]);
                if (User::where('student_id', $member['student_id'])->where('email', '!=', $member['email'])->exists()) {
                    throw new RuntimeException('Student ID belongs to another account.');
                }
                if (User::where('room_id', $room->id)->where('is_active', true)->where('email', '!=', $member['email'])->count() >= Room::CAPACITY) {
                    throw new RuntimeException('Room is full: '.$member['room']);
                }
                $oldValues = $user->exists ? $user->only(['name', 'role', 'room_id', 'dorm_id', 'is_active']) : null;
                $user->first_name = $member['first_name'];
                $user->last_name = $member['last_name'];
                $user->name = $member['first_name'].' '.$member['last_name'];
                $user->student_id = $member['student_id'];
                $user->faculty = 'วิทยาลัยการคอมพิวเตอร์';
                $user->role = $member['role'];
                $user->dorm_id = $dorm->id;
                $user->room_id = $room->id;
                $user->is_active = true;
                if (! $user->exists || $user->isDirty()) {
                    $user->save();
                    AuditLog::create([
                        'action' => 'setup.temporary_member', 'target_type' => User::class, 'target_id' => $user->id,
                        'old_values' => $oldValues,
                        'new_values' => $user->only(['name', 'role', 'room_id', 'dorm_id', 'is_active']),
                    ]);
                }
            }
        });
    }
}
