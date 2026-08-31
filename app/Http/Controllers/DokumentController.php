<?php

namespace App\Http\Controllers;

use App\Models\Dokument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumentController extends Controller
{
    public function download(Dokument $dokument): StreamedResponse
    {
        abort_unless($dokument->pfad && Storage::exists($dokument->pfad), 404);

        return Storage::download($dokument->pfad, $dokument->dateiname);
    }
}
