<?php

/*
 * Firmenstammdaten des Auftragnehmers — erscheinen in allen PDF-Dokumenten
 * (Briefkopf, Fußzeile) sowie im Abnahmeprotokoll. Quelle: Impressum
 * lea-ueberdachung.de. Bank/IBAN sind nicht veröffentlicht — sobald sie
 * hier eingetragen sind, zeigt die PDF-Fußzeile die Bankverbindung statt
 * der Registerangaben.
 */
return [
    'firma' => 'LEA Solar GmbH',
    'anschrift' => 'Innstraße 46, 12045 Berlin',
    'telefon' => '+49 171 5105962',
    'email' => 'vertrieb@leasolar.de',
    'web' => 'www.lea-ueberdachung.de',
    'geschaeftsfuehrer' => 'Mischa Raiser',
    'register' => 'Amtsgericht Berlin · HRB 28340',
    'ustid' => 'DE298167084',
    'bank' => null,
    'iban' => null,
    'bauleitung' => 'M. Schneider',
    'montageteam' => 'Team Berlin K1 · T. Wagner, R. Sobek',
    'monteur' => 'T. Wagner · Team Berlin K1',
];
