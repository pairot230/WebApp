<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('dorm.superadmin_email')));
        Validator::make(['email' => $email], ['email' => ['required', 'email']])->validate();
        if (! in_array(substr(strrchr($email, '@'), 1), ['kkumail.com', 'kku.ac.th'], true)) {
            throw new RuntimeException('SuperAdmin must use a KKU email.');
        }

        DB::transaction(function () use ($email): void {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || ! $user->is_active) {
                throw new RuntimeException('Import an active member before assigning SuperAdmin.');
            }
            if ($user->role === 'superadmin') {
                return;
            }
            $oldRole = $user->role;
            $user->role = 'superadmin';
            $user->save();

            $audit = new AuditLog;
            $audit->action = 'setup.superadmin';
            $audit->target_type = User::class;
            $audit->target_id = $user->id;
            $audit->old_values = ['role' => $oldRole];
            $audit->new_values = ['role' => 'superadmin', 'source' => 'controlled_seeder'];
            $audit->save();
        });
    }
}
