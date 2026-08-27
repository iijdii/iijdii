@php
    $gruppen = [
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
    <div class="ngl">{{ $gruppe }}</div>
    @foreach ($links as [$route, $label])
        <a class="navlink {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}">
            <span class="nvt">{{ $label }}</span>
        </a>
    @endforeach
@endforeach
