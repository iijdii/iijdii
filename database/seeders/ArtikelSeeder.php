<?php

namespace Database\Seeders;

use App\Models\Artikel;
use App\Models\Lieferant;
use Illuminate\Database\Seeder;

/**
 * 41 Artikel aus dem Prototyp (_lagerMaster) — initialer Katalogimport.
 * Katalog und Lager sind dieselbe Tabelle (Single Source of Truth).
 */
class ArtikelSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(__DIR__.'/data/artikel.json'), true);
        $lieferanten = Lieferant::query()->pluck('id', 'name');

        foreach ($rows as $row) {
            $artikel = Artikel::query()->updateOrCreate(
                ['art_nr' => $row['art']],
                [
                    'name' => $row['name'],
                    'kategorie' => $row['kat'],
                    'bild' => $row['img'] ?: null,
                    'einheit' => $row['einheit'],
                    'lagerort' => $row['ort'],
                    'bestand' => $row['bestand'],
                    'min_bestand' => $row['min'],
                    'ek_preis' => $row['ek'],
                    'lieferant_id' => $lieferanten[$row['lief']],
                ],
            );

            foreach ($row['alias'] as $alias) {
                $artikel->aliase()->firstOrCreate(['alias' => $alias]);
            }
        }
    }
}
