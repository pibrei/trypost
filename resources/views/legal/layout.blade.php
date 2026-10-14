<!DOCTYPE html>
<html lang="@yield('lang', 'pt-BR')">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="@yield('robots', 'index, follow')">
        <title>@yield('title') · {{ $product }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <style>
            :root {
                color-scheme: light dark;
                --background: #f7f7f8;
                --card: #ffffff;
                --foreground: #18181b;
                --muted: #52525b;
                --border: #e4e4e7;
                --link: #4f46e5;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --background: #111111;
                    --card: #1a1a1a;
                    --foreground: #f4f4f5;
                    --muted: #a1a1aa;
                    --border: #2a2a2e;
                    --link: #a5b4fc;
                }
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                background: var(--background);
                color: var(--foreground);
                font: 16px/1.65 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            }

            main {
                max-width: 760px;
                margin: 0 auto;
                padding: 48px 16px 64px;
            }

            article {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 32px clamp(16px, 5vw, 48px);
            }

            h1 { font-size: 1.75rem; line-height: 1.25; margin: 0 0 4px; }
            h2 { font-size: 1.2rem; margin: 32px 0 8px; }
            h3 { font-size: 1rem; margin: 24px 0 4px; }
            p, li { color: var(--foreground); }
            ul, ol { padding-inline-start: 22px; }
            a { color: var(--link); text-underline-offset: 3px; }
            hr { border: 0; border-top: 1px solid var(--border); margin: 40px 0; }
            .meta { color: var(--muted); font-size: 0.9rem; margin: 0 0 24px; }
            .status { font-size: 1.1rem; font-weight: 600; }

            nav, footer {
                display: flex;
                flex-wrap: wrap;
                gap: 8px 20px;
                justify-content: center;
                color: var(--muted);
                font-size: 0.9rem;
                margin: 24px 0 0;
            }

            nav { margin: 0 0 24px; }
            .brand { font-weight: 700; color: var(--foreground); text-decoration: none; }
        </style>
    </head>
    <body>
        <main>
            <nav>
                <a class="brand" href="{{ url('/') }}">{{ $product }}</a>
                <a href="{{ route('legal.privacy') }}">Privacidade / Privacy</a>
                <a href="{{ route('legal.terms') }}">Termos / Terms</a>
                <a href="{{ route('legal.data-deletion') }}">Exclusão de dados / Data deletion</a>
            </nav>

            <article>
                @yield('content')
            </article>

            <footer>
                <span>{{ $company }}@if ($cnpj !== '') · CNPJ {{ $cnpj }}@endif</span>
                <a href="mailto:{{ $email }}">{{ $email }}</a>
            </footer>
        </main>
    </body>
</html>
