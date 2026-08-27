<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'LEA CRM') · LEA CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:opsz,wght@9..40,400..700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app" id="app">
    @include('partials.sidebar')
    <div class="main">
        <header class="topbar">
            <div class="navdrop-wrap">
                <button class="iconbtn" id="navBtn" type="button" aria-label="Navigation" aria-expanded="false">
                    <svg class="i" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <nav class="navdrop" id="navDrop" hidden>
                    @include('partials.nav-links')
                </nav>
            </div>
            <h1 class="pagetitle">@yield('title', 'LEA CRM')</h1>
            <div class="spacer"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn" type="submit">Abmelden</button>
            </form>
        </header>
        <main class="content">
            @yield('content')
        </main>
    </div>
</div>
<div class="nav-backdrop" id="navBackdrop" hidden></div>
</body>
</html>
