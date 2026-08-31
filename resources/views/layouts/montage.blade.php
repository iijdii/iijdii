<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Montage-Modus') · LEA CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:opsz,wght@9..40,400..700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/montage.js'])
<script src="{{ asset('js/anfahrt-map.js') }}" defer></script>
</head>
<body>
@include('partials.icons')
@yield('content')
@if (session('toast'))
    <div class="toast" data-autotoast>{{ session('toast') }}</div>
@endif
</body>
</html>
