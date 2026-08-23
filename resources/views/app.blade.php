<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Resolve the per-request CSP nonce once, use everywhere below.
             Vite::useCspNonce() was called in SecurityHeaders middleware before
             the response was built, so cspNonce() returns the correct value. --}}
        @php $nonce = \Illuminate\Support\Facades\Vite::cspNonce(); @endphp

        <!-- Fonts — whitelisted in CSP style-src / font-src -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Theme Initialization (Prevents FOUC).
             Nonce required: CSP script-src allows only nonce-stamped scripts. -->
        <script nonce="{{ $nonce }}">
            (function() {
                try {
                    const theme = localStorage.getItem('afyanova-theme') || 'system';
                    const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (isDark) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- @@routes stamps its <script> with nonce="$nonce".
             @@vite reads Vite::cspNonce() internally — no argument needed. -->
        @routes(nonce: $nonce)
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
