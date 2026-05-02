<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#10b981">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/icons/apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('manifest.json') }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <style>
            @font-face {
                font-family: 'Cairo';
                font-style: normal;
                font-weight: 400 700;
                font-display: swap;
                src: url('{{ asset('fonts/cairo-arabic.woff2') }}') format('woff2');
            }
            @font-face {
                font-family: 'IBM Plex Sans Arabic';
                font-style: normal;
                font-weight: 400 700;
                font-display: swap;
                src: url('{{ asset('fonts/ibm-plex-sans-arabic.woff2') }}') format('woff2');
            }
            @font-face {
                font-family: 'Nunito';
                font-style: normal;
                font-weight: 400 700;
                font-display: swap;
                src: url('{{ asset('fonts/nunito-latin.woff2') }}') format('woff2');
            }
        </style>

        <!-- Scripts -->
        <script>
            window.lang = '{{ app()->getLocale() }}';
        </script>
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js');
            }
        </script>
    </body>
</html>
