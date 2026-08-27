<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LieferantSeeder::class,
            ArtikelSeeder::class,
            SettingSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
