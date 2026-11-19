<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') · {{ config('app.name') }}</title>
        <meta name="robots" content="index, follow">
        <style>
            :root { --bg: #ffffff; --text: #1f2933; --muted: #5f6b7a; --line: #e4e7eb; --brand: #fc5f02; }
            @media (prefers-color-scheme: dark) {
                :root { --bg: #14171a; --text: #e8eaed; --muted: #a0a8b3; --line: #2b3036; --brand: #ff8a3d; }
            }
            * { box-sizing: border-box; }
            body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.65 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
            main { max-width: 42rem; margin: 0 auto; padding: 2rem 1rem 4rem; }
            h1 { font-size: 1.8rem; line-height: 1.25; margin: 0 0 .25rem; }
            h2 { font-size: 1.15rem; margin: 2rem 0 .5rem; }
            p, li { margin: .5rem 0; }
            ul { padding-left: 1.25rem; }
            a { color: var(--brand); }
            .muted { color: var(--muted); font-size: .9rem; }
            nav { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 2.5rem; padding-top: 1rem; border-top: 1px solid var(--line); font-size: .95rem; }
            table { width: 100%; border-collapse: collapse; margin: .75rem 0; font-size: .95rem; }
            th, td { text-align: left; vertical-align: top; padding: .5rem .5rem .5rem 0; border-bottom: 1px solid var(--line); }
        </style>
    </head>
    <body>
        <main>
            <h1>@yield('title')</h1>
            <p class="muted">{{ config('app.name') }} · Last updated {{ config('legal.updated') }}</p>

            @yield('content')

            <h2>Contact</h2>
            <p>
                @if (config('legal.contact_email'))
                    Write to <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.
                @else
                    Write to us through the Google Play page of the {{ config('app.name') }} app.
                @endif
            </p>

            <nav>
                <a href="{{ route('legal.privacy') }}">Privacy policy</a>
                <a href="{{ route('legal.terms') }}">Terms of use</a>
                <a href="{{ route('legal.account-deletion') }}">Delete your account</a>
            </nav>
        </main>
    </body>
</html>
