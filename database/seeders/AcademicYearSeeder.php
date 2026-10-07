<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::firstOrNew(['year' => config('dorm.academic_year')]);
        if (! $year->exists) {
            $year->is_active = ! AcademicYear::where('is_active', true)->exists();
            $year->save();
        }
    }
}
