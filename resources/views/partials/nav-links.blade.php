@php
    // Lieferanten-Portal (M14): nur die eigenen Bestellungen — der Rest
    // der Navigation existiert für diese Rolle nicht.
    $gruppen = auth()->user()?->istLieferant() ? [
        'Portal' => [
            ['bestellungen', 'Meine Bestellungen'],
        ],
    ] : [
        'Vertrieb' => [
            ['dashboard', 'Dashboard'],
            ['kunden', 'Kunden'],
            ['anfragen', 'Anfragen'],
            ['angebote', 'Angebote'],
        ],
        'Abwicklung' => [
            ['projekte', 'Projekte'],
            ['bestellungen', 'Bestellungen'],
            ['logistik', 'Logistik'],
            ['kalender', 'Kalender'],
        ],
        'Lager' => [
            ['lager', 'Lager'],
            ['material-katalog', 'Material-Katalog'],
            ['lieferanten', 'Lieferanten'],
        ],
        'System' => [
            ['einstellungen', 'Einstellungen'],
        ],
    ];
@endphp
@foreach ($gruppen as $gruppe => $links)
    {{-- Einstellungen sind role:projektleiter-gegated — Link nur zeigen, wer ihn öffnen darf --}}
    @continue($gruppe === 'System' && ! in_array(auth()->user()?->role, [\App\Enums\Rolle::Admin, \App\Enums\Rolle::Projektleiter], true))
    <div class="ngl">{{ $gruppe }}</div>
    @foreach ($links as [$route, $label])
        <a class="navlink {{ request()->routeIs($route, $route.'.*') ? 'active' : '' }}" href="{{ route($route) }}">
            <span class="nvt">{{ $label }}</span>
        </a>
    @endforeach
@endforeach
