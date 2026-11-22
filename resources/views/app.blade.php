@use('App\Enums\Appearance')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? Appearance::SYSTEM->value) === Appearance::DARK->value])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = @json($appearance ?? Appearance::SYSTEM->value);

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
                background-color: #F6F6EF;
            }

            html.dark {
                background-color: #17191B;
            }
        </style>

        <title data-inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @routes
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
