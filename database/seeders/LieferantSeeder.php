<?php

namespace Database\Seeders;

use App\Models\Lieferant;
use Illuminate\Database\Seeder;

class LieferantSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Sunshine', 'Solarlux', 'Würth'] as $name) {
            Lieferant::query()->firstOrCreate(['name' => $name]);
        }
    }
}
