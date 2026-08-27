<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anmelden · LEA CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:opsz,wght@9..40,400..700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
</head>
<body class="login-body">
<main class="login-card">
    <div class="brand login-brand">
        <span class="logo" aria-hidden="true"></span>
        <span class="brandtx"><b>LEA CRM</b><span>Terrassendach</span></span>
    </div>
    <h1 class="login-title">Anmelden</h1>

    <form method="POST" action="{{ route('login.attempt') }}" novalidate>
        @csrf
        <label class="field">
            <span class="field-label">E-Mail</span>
            <input class="input @error('email') input-error @enderror" type="email" name="email"
                   value="{{ old('email') }}" required autofocus autocomplete="username">
        </label>
        @error('email')<p class="field-error">{{ $message }}</p>@enderror

        <label class="field">
            <span class="field-label">Passwort</span>
            <input class="input" type="password" name="password" required autocomplete="current-password">
        </label>

        <label class="check">
            <input type="checkbox" name="remember" value="1">
            <span>Angemeldet bleiben</span>
        </label>

        <button class="btn btn-primary btn-block" type="submit">Anmelden</button>
    </form>
</main>
</body>
</html>
