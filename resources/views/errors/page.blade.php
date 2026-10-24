<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $status }} — {{ $title }} | {{ config('app.name', 'quickFolio') }}</title>
        <link rel="icon" type="image/svg+xml" href="/logo.svg">
        <style>
            :root {
                color-scheme: light;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #172033;
                background: #f8fafc;
                font-synthesis: none;
                text-rendering: optimizeLegibility;
            }

            * { box-sizing: border-box; }
            body { display: grid; min-height: 100vh; margin: 0; padding: 24px; place-items: center; }
            main { width: min(100%, 560px); padding: clamp(32px, 8vw, 64px); border: 1px solid #e2e8f0; border-radius: 24px; background: #fff; box-shadow: 0 24px 70px -44px #334155; }
            .brand { display: inline-flex; align-items: center; gap: 10px; color: inherit; font-size: 15px; font-weight: 700; text-decoration: none; }
            .brand img { width: 32px; height: 32px; object-fit: contain; }
            .code { margin: 48px 0 12px; color: #64748b; font-size: 13px; font-weight: 700; letter-spacing: .16em; }
            h1 { margin: 0; font-size: clamp(32px, 7vw, 48px); letter-spacing: -.045em; line-height: 1.08; }
            .message { margin: 16px 0 32px; color: #64748b; font-size: 16px; line-height: 1.7; }
            .home { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; border-radius: 10px; background: #171923; padding: 0 18px; color: #fff; font-size: 14px; font-weight: 600; text-decoration: none; transition: background .2s ease; }
            .home:hover { background: #334155; }
            .home:focus-visible { outline: 3px solid #94a3b8; outline-offset: 3px; }
            @media (prefers-reduced-motion: reduce) { .home { transition: none; } }
        </style>
    </head>
    <body>
        <main>
            <a class="brand" href="{{ url('/') }}" aria-label="{{ config('app.name', 'quickFolio') }} home">
                <img src="/logo.svg" alt="">
                <span>{{ config('app.name', 'quickFolio') }}</span>
            </a>
            <p class="code">ERROR {{ $status }}</p>
            <h1>{{ $title }}</h1>
            <p class="message">{{ $message }}</p>
            <a class="home" href="{{ url('/') }}">Back to home</a>
        </main>
    </body>
</html>
