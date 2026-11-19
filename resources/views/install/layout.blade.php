{{-- Instalador web (ADR 0030). Sin Vite ni base de datos: funciona en un servidor recién subido. --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Instalación · Clave de Alma</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        :root { color-scheme: dark; --bg:#070707; --card:#121110; --line:#2a2826; --text:#e8e4de; --muted:#9a948d; --red:#d4141c; --ok:#3fb950; --warn:#e3a008; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 680px; margin: 0 auto; padding: 40px 16px 64px; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; }
        .brand img { width:40px; height:40px; border-radius:9px; }
        .brand strong { font-size:1.15rem; letter-spacing:.04em; text-transform:uppercase; }
        .steps { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 24px; padding:0; list-style:none; font-size:.85rem; color:var(--muted); }
        .steps li { padding:4px 10px; border:1px solid var(--line); border-radius:999px; }
        .steps li.on { color:var(--text); border-color:var(--red); }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:24px; }
        h1 { font-size:1.5rem; margin:0 0 8px; }
        p { margin:0 0 14px; }
        .muted { color:var(--muted); font-size:.9rem; }
        label { display:block; font-weight:600; margin:16px 0 6px; }
        input { width:100%; padding:11px 12px; border-radius:8px; border:1px solid var(--line); background:#0b0b0a; color:var(--text); font:inherit; }
        input:focus { outline:2px solid var(--red); outline-offset:1px; }
        .row { display:grid; grid-template-columns: 1fr 120px; gap:12px; }
        .btn { display:inline-block; margin-top:22px; padding:12px 20px; border:0; border-radius:8px; background:var(--red); color:#fff; font:inherit; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn[disabled] { opacity:.4; cursor:not-allowed; }
        .alert { padding:12px 14px; border-radius:8px; margin:0 0 16px; border:1px solid; }
        .alert.err { border-color:#7a1d20; background:#2a0709; }
        .alert.ok { border-color:#1f5f2c; background:#0b2412; }
        ul.checks { list-style:none; margin:0; padding:0; }
        ul.checks li { padding:8px 0; border-bottom:1px solid var(--line); display:flex; gap:10px; }
        ul.checks li:last-child { border:0; }
        .dot { flex:none; width:22px; text-align:center; font-weight:700; }
        .yes { color:var(--ok); } .no { color:var(--red); } .opt { color:var(--warn); }
        code, pre { font-family: ui-monospace, Menlo, Consolas, monospace; font-size:.85rem; background:#0b0b0a; border:1px solid var(--line); border-radius:6px; }
        code { padding:1px 5px; }
        pre { padding:12px; overflow-x:auto; white-space:pre-wrap; word-break:break-all; }
        .error { color:#ff8a8f; font-size:.88rem; margin-top:4px; }
        @media (max-width: 520px) { .row { grid-template-columns: 1fr; } .card { padding:18px; } }
    </style>
</head>
<body>
<main>
    <div class="brand"><img src="/favicon-96x96.png" alt=""><strong>Clave de Alma</strong></div>
    <ol class="steps" aria-label="Pasos">
        @foreach (['Servidor', 'Base de datos', 'Administrador', 'Listo'] as $i => $label)
            <li class="{{ $step === $i + 1 ? 'on' : '' }}">{{ $i + 1 }}. {{ $label }}</li>
        @endforeach
    </ol>
    <div class="card">@yield('content')</div>
</main>
</body>
</html>
