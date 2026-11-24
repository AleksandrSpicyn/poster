@use('App\Enums\Appearance')
<!DOCTYPE html>
<html lang="ru" @class(['dark' => ($page['props']['appearance'] ?? Appearance::SYSTEM->value) === Appearance::DARK->value])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = @json($page['props']['appearance'] ?? Appearance::SYSTEM->value);

                if (appearance === @json(Appearance::SYSTEM->value)) {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #F7F6F0;
            }

            html.dark {
                background-color: #17191A;
            }
        </style>

        <title data-inertia>{{ config('app.name', 'Poster') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @routes
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
