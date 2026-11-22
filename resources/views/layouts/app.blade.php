<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#15043b">
        <title>@yield('title', 'CSFEST2026') · CSFEST2026</title>
        <script>
            try {
                if (localStorage.getItem('csfest-theme') === 'night') {
                    document.documentElement.dataset.theme = 'night';
                }
            } catch {}
        </script>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @yield('content')
    </body>
</html>
