<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin User',
                'email' => 'admin@school.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'must_change_password' => false,
                'active' => true,
            ]
        );

        $teacher = User::updateOrCreate(
            ['username' => 'teacher'],
            [
                'name' => 'Demo Teacher',
                'email' => 'teacher@school.local',
                'password' => Hash::make('password'),
                'role' => 'teacher',
                'must_change_password' => false,
                'active' => true,
            ]
        );

        $class = ClassRoom::updateOrCreate(
            ['code' => 'G6DEMO'],
            [
                'teacher_id' => $teacher->id,
                'name' => 'Grade 6 - Demo',
                'section' => 'Demo',
                'school_year' => '2026-2027',
                'leaderboard_visible' => true,
            ]
        );

        foreach ([
            ['Demo Student Juan', 'student'],
            ['Demo Student Maria', 'student.maria'],
            ['Demo Student Jose', 'student.jose'],
        ] as [$name, $username]) {
            $student = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username.'@school.local',
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'must_change_password' => false,
                    'active' => true,
                ]
            );
            $class->students()->syncWithoutDetaching([$student->id]);
        }
    }
}
