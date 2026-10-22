<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system or stored dark mode preference and apply it immediately without flash --}}
        <script>
            (function() {
                try {
                    const stored = localStorage.getItem('appearance');
                    const appearance = stored || '{{ $appearance ?? "system" }}';

                    if (appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (e) {
                    // Fallback
                }
            })();
        </script>

        {{-- Inline script to apply custom institutional branding colors immediately before render --}}
        <script>
            (function() {
                try {
                    const raw = localStorage.getItem('sigc_branding');
                    if (raw) {
                        const b = JSON.parse(raw);
                        if (b.primaryColor) {
                            const root = document.documentElement;
                            root.style.setProperty('--institutional', b.primaryColor);
                            root.style.setProperty('--color-institutional', b.primaryColor);
                            root.style.setProperty('--primary', b.primaryColor);
                            root.style.setProperty('--ring', b.primaryColor);
                        }
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
                overflow-x: hidden;
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            body {
                overflow-x: hidden;
                max-width: 100vw;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'SIGC-MDSJ') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased overflow-x-hidden min-h-screen">
        <x-inertia::app />
    </body>
</html>
