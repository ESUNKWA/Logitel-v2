<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#15264a">
        <title>Pas de connexion — {{ $hotelInfo->name() }}</title>
        <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
        <link rel="stylesheet" href="/app/css/admin.css">
    </head>
    <body class="pwa-offline">
        {{-- Shown by the service worker when the network is unreachable (no data on this page). --}}
        <main class="pwa-offline__card">
            <img src="/app/images/pwa/icon-192.png" alt="" width="72" height="72">
            <h1>Pas de connexion</h1>
            <p>L'application a besoin d'Internet pour afficher les chambres, les clients et les encaissements. Vérifiez le Wi-Fi ou les données mobiles, puis réessayez.</p>
            <button type="button" class="btn btn-primary" onclick="location.reload()">Réessayer</button>
        </main>
    </body>
</html>
