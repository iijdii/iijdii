<?php

namespace Database\Seeders;

use App\Enums\Rolle;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $benutzer = [
            [Rolle::Admin, 'Administrator', 'admin@lea.test'],
            [Rolle::Verkaeufer, 'Max Schneider', 'verkauf@lea.test'],
            [Rolle::Projektleiter, 'Anna Vogt', 'projekt@lea.test'],
            [Rolle::Lager, 'S. Krüger', 'lager@lea.test'],
            [Rolle::Monteur, 'Team Berlin K1', 'monteur@lea.test'],
        ];

        foreach ($benutzer as [$rolle, $name, $email]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'role' => $rolle],
            );
        }
    }
}
