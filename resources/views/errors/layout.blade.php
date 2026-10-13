{{-- T1.12, BR-SEC-16: plain error pages. No scripts, no logo, no technical details. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('title') – ZB Poultry Protein Mill</title>
        <style>
            body { margin: 0; font-family: system-ui, sans-serif; background: #ffffff; color: #04291A; }
            main { max-width: 32rem; margin: 15vh auto 0; padding: 0 1.5rem; }
            h1 { font-size: 1.5rem; margin: 0 0 0.75rem; }
            p { line-height: 1.5; margin: 0 0 1rem; }
            a { color: #0A5223; }
        </style>
    </head>
    <body>
        <main>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <p><a href="/dashboard">{{ __('Go to the dashboard') }}</a></p>
        </main>
    </body>
</html>
