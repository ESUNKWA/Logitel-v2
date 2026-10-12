<?php

/*
 * The installable application (PWA) and its Android package (APK, generated with PWABuilder as a
 * Trusted Web Activity). Only the management application is installable — never the website.
 *
 * Android verifies that the APK and the site belong together through
 * /.well-known/assetlinks.json, built from these two values (given by PWABuilder when it signs
 * the APK). Leave them empty until an APK exists: the file then answers 404.
 */
return [

    // e.g. ci.hotelevasion.gestion
    'android_package' => env('PWA_ANDROID_PACKAGE') ?: null,

    // SHA-256 fingerprint(s) of the signing key, comma-separated (« AB:CD:… »).
    'android_fingerprints' => array_values(array_filter(array_map('trim', explode(',', (string) env('PWA_ANDROID_SHA256', ''))))),

];
