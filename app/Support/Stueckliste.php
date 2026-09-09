<?php

namespace App\Support;

/**
 * Stückliste des Dachbausatzes nach der KD-Montageanleitung (Kap. 3):
 * Hauptpositionen — Profile, Eindeckung, Wasserablauf, Kappen und
 * Halterungen. Kleinteile (Schrauben, Silikon, Compriband, Gummis,
 * Dübel) ergänzt der Verkäufer im Bestell-Entwurf selbst.
 */
final class Stueckliste
{
    /**
     * @param  array  $kalk  Ergebnis von KonfiguratorRechner::berechne()
     * @return list<array{name: string, menge: int, einheit: string, typ: string, breite_mm?: int, hoehe_mm?: int, laenge_mm?: int}>
     */
    public static function dach(array $kalk): array
    {
        $p = $kalk['pcfg'];
        $W = (int) $p['width'];
        $D = (int) $p['depth'];
        $zeilen = [];
        $zeile = function (string $name, int $menge, string $einheit = 'Stück', array $extra = []) use (&$zeilen) {
            $zeilen[] = ['name' => $name, 'menge' => $menge, 'einheit' => $einheit, 'typ' => 'material'] + $extra;
        };

        // Eindeckung zuerst — die Glas-/Plattenposition trägt die Maße.
        // Polycarbonat im Automatikmodus: volle 980er-Platten + ein
        // geschnittenes Restfeld (v3.2-Regel «целые + остаток»).
        if (($kalk['polyRestPlatte'] ?? null) !== null && $kalk['fields'] > 1) {
            $zeilen[] = [
                'name' => 'Dachfeld '.$p['covering'].' '.$p['thickness'],
                'menge' => $kalk['fields'] - 1, 'einheit' => 'Feld', 'typ' => 'glas',
                'breite_mm' => $kalk['glasB'], 'hoehe_mm' => $kalk['glasT'],
            ];
            $zeilen[] = [
                'name' => 'Dachfeld '.$p['covering'].' '.$p['thickness'].' (Restfeld, Zuschnitt)',
                'menge' => 1, 'einheit' => 'Feld', 'typ' => 'glas',
                'breite_mm' => $kalk['polyRestPlatte'], 'hoehe_mm' => $kalk['glasT'],
            ];
        } else {
            $zeilen[] = [
                'name' => 'Dachfeld '.$p['covering'].' '.$p['thickness'],
                'menge' => $kalk['fields'], 'einheit' => 'Feld', 'typ' => 'glas',
                'breite_mm' => $kalk['glasB'], 'hoehe_mm' => $kalk['glasT'],
            ];
        }

        // Profile (Länge = Dachbreite bzw. Dachtiefe).
        $zeile('Gigarinne (Profil 35732)', 1, 'Stück', ['laenge_mm' => $W]);
        $zeile('Wandprofil (Profil 35721)', 1, 'Stück', ['laenge_mm' => $W]);
        $zeile('Sparren/Träger (Profil 47047)', $kalk['rafters'], 'Stück', ['laenge_mm' => $D, 'such' => 'Dachsparren 80×60 mm']);
        $zeile('Alu-Pfosten 110×110 (Profil 35722) · '.$p['color'], $kalk['pn'], 'Stück', ['such' => 'Pfosten 110×110']);
        $zeile('Wandblende (Profil 35715)', 1, 'Stück', ['laenge_mm' => $W]);
        $zeile('Seitenabdeckprofil / Eckleiste (Profil 35720)', 2, 'Stück', ['laenge_mm' => $D]);
        if ($kalk['rafters'] > 2) {
            $zeile('Abdeckprofil Rundleiste (Profil 35720)', $kalk['rafters'] - 2, 'Stück', ['laenge_mm' => $D]);
        }

        // Kappen, Halterungen, Wasserablauf.
        $zeile('Endkappe Rinne', 2);
        $zeile('Endkappe Wand', 2);
        $zeile('Bodenprofil (U-Halterung)', $kalk['pn']);
        $zeile('Endstopp / Stoppwinkel', $kalk['rafters'], 'Stück', ['such' => 'Endstopp / Stoppwinkel']);
        $zeile('DN75 HT Rohr (Wasserablauf)', 1);
        $zeile('DN75 HT Rohrbogen', 1);
        $zeile('Laubfänger DN75', 1, 'Stück', ['such' => 'Laubfänger DN75']);

        // Unterzug bei Pflicht (freistehend, Tiefe > 4000, Überstand …).
        if ($kalk['unterzug']['erforderlich'] ?? false) {
            $zeile('Unterzug '.$kalk['unterzug']['groesse'], 1, 'Stück', ['laenge_mm' => $W]);
        }

        // Polycarbonat: Tropfkante je Feld (KD Kap. 7.1).
        if ($kalk['poly'] ?? false) {
            $zeile('Alu-Abschlussprofil / Tropfkante (Profil 35906)', $kalk['fields'], 'Stück', ['laenge_mm' => $kalk['glasB']]);
        }

        if (($p['led']['on'] ?? true) && $kalk['ledTot'] > 0) {
            $zeile('LED-Set '.$kalk['ledTot'].' Spots · '.($p['led']['color'] ?? ''), 1, 'Set');
        }

        return $zeilen;
    }
}
