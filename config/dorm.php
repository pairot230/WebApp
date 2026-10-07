<?php

return [
    'temporary_login' => (bool) env('DORM_TEMPORARY_LOGIN', false),
    'temporary_members' => [
        ['room' => '304', 'student_id' => '673380520-8', 'first_name' => 'นายธนชัย', 'last_name' => 'ทองใบ', 'email' => 'thanachai.th@kkumail.com', 'role' => 'admin'],
        ['room' => '305', 'student_id' => '673380534-7', 'first_name' => 'นายไพโรจน์', 'last_name' => 'ฉ่องสวนอ้อย', 'email' => 'pairoj.c@kkumail.com', 'role' => 'superadmin'],
        ['room' => '310', 'student_id' => '673380335-3', 'first_name' => 'นายพุฒิพงศ์', 'last_name' => 'พานิชพันธุ์', 'email' => 'pudtipong.p@kkumail.com', 'role' => 'admin'],
        ['room' => '316', 'student_id' => '673380334-5', 'first_name' => 'นายพีรพงษ์', 'last_name' => 'ราษีทอง', 'email' => 'pheeraphong.r@kkumail.com', 'role' => 'user'],
        ['room' => '414', 'student_id' => '673380322-2', 'first_name' => 'นายธาม', 'last_name' => 'อะทอยรัมย์', 'email' => 'tharm.a@kkumail.com', 'role' => 'user'],
    ],
    'academic_year' => (int) env('DORM_ACADEMIC_YEAR', 2569),
    'superadmin_email' => env('SUPERADMIN_EMAIL'),
    'demo_database' => env('DORM_DEMO_DATABASE'),
    'demo_room_mapping' => env('DORM_DEMO_ROOM_MAPPING'),
];
