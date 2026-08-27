<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Toleranzschwellen für Aufmaß-Abweichungen (Soll/Ist/Δ):
 * ≤ grün mm grün, ≤ gelb mm gelb, darüber rot. Geschäftsregel —
 * bewusst konfigurierbar statt hartkodiert.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->updateOrCreate(['key' => 'toleranz_gruen_mm'], ['value' => 5]);
        Setting::query()->updateOrCreate(['key' => 'toleranz_gelb_mm'], ['value' => 15]);
    }
}
