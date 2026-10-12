<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Connexion — {{ $hotelInfo->name() }}</title>
        <link rel="shortcut icon" href="{{ asset('app/images/favicon.png') }}" type="image/png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gilda+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
        <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('app/css/admin.css') }}">
        @include('admin.pwa._head')
    </head>
    <body class="admin-auth-body has-photo" style="--auth-photo: url('{{ $hotelInfo->loginBackgroundUrl() }}')">
        <div class="admin-auth-card">
            <h1 data-initials="{{ $hotelInfo->initials() }}">{{ $hotelInfo->name() }}</h1>
            <p class="text-muted">Gestion hôtelière — accès personnel</p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">Se connecter</button>
            </form>
        </div>
        <script src="{{ asset('app/js/pwa.js') }}"></script>
    </body>
</html>
