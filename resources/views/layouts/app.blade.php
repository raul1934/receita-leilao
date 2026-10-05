<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Editais') · Receita Leilão</title>
    <style>
        :root {
            --bg: #f6f7f9; --surface: #fff; --text: #1d2330; --muted: #5d6677; --border: #dfe3ea;
            --accent: #1f5fae; --accent-text: #fff; --ok-bg: #e6f4ea; --ok-text: #1e6b34;
            --err-bg: #fdecec; --err-text: #9b1c1c; --badge-bg: #eef2f8; --row-alt: #fafbfc;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #14171c; --surface: #1c2027; --text: #e6e9ef; --muted: #9aa3b2; --border: #2e343e;
                --accent: #6ea8ff; --accent-text: #0d1117; --ok-bg: #173323; --ok-text: #8fd6a5;
                --err-bg: #3a1a1a; --err-text: #f2a3a3; --badge-bg: #262c36; --row-alt: #20252d;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
        .topo { background: var(--surface); border-bottom: 1px solid var(--border); margin-bottom: 24px; }
        .topo .container { display: flex; align-items: baseline; gap: 12px; padding-top: 14px; padding-bottom: 14px; flex-wrap: wrap; }
        .marca { font-weight: 700; font-size: 18px; color: var(--text); }
        .sub, .muted { color: var(--muted); }
        h1 { font-size: 24px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 28px 0 12px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .alerta { border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
        .sucesso { background: var(--ok-bg); color: var(--ok-text); }
        .erro { background: var(--err-bg); color: var(--err-text); }
        form.linha { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        input[type=text], input[type=search], select {
            font: inherit; color: var(--text); background: var(--surface); border: 1px solid var(--border);
            border-radius: 6px; padding: 8px 10px; min-width: 0; max-width: 100%;
        }
        input.largo { flex: 1 1 320px; }
        button, .botao {
            font: inherit; border: 1px solid var(--accent); background: var(--accent); color: var(--accent-text);
            border-radius: 6px; padding: 8px 14px; cursor: pointer; display: inline-block;
        }
        .botao.secundario, button.secundario { background: transparent; color: var(--accent); }
        .tabela { overflow-x: auto; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: 13px; color: var(--muted); font-weight: 600; white-space: nowrap; }
        tbody tr:nth-child(even) { background: var(--row-alt); }
        tbody tr:last-child td { border-bottom: 0; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .badge { display: inline-block; background: var(--badge-bg); border-radius: 999px; padding: 2px 10px; font-size: 13px; white-space: nowrap; }
        .thumb { width: 64px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); display: block; }
        .grade { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px 24px; }
        .grade dt { font-size: 13px; color: var(--muted); }
        .grade dd { margin: 0; }
        dl { margin: 0; }
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
        .galeria img { width: 100%; height: 140px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); display: block; }
        .cabecalho { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .acoes { display: flex; gap: 8px; flex-wrap: wrap; }
        .vazio { padding: 32px; text-align: center; color: var(--muted); }
        .pagination { display: flex; gap: 4px; list-style: none; padding: 0; margin: 16px 0; flex-wrap: wrap; }
        .page-link { display: block; padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); }
        .page-item.active .page-link { background: var(--accent); color: var(--accent-text); border-color: var(--accent); }
        .page-item.disabled .page-link { color: var(--muted); }
        footer { color: var(--muted); font-size: 13px; padding: 32px 0; }
        .foto { display: inline-block; position: relative; }
        .foto .qtd { position: absolute; right: 3px; bottom: 3px; background: rgba(0,0,0,.65); color: #fff; font-size: 11px; border-radius: 4px; padding: 0 4px; }
        .galeria a { cursor: zoom-in; }
        .mudou { font-weight: 700; }
        .badge.mudou { background: var(--accent); color: var(--accent-text); }
        .lightbox { position: fixed; inset: 0; z-index: 100; background: rgba(0, 0, 0, .92); display: flex; align-items: center; justify-content: center; }
        .lightbox[hidden], .lightbox button[hidden] { display: none; }
        .lightbox img { max-width: calc(100vw - 32px); max-height: calc(100vh - 120px); object-fit: contain; border-radius: 4px; user-select: none; }
        .lightbox button {
            position: absolute; display: flex; align-items: center; justify-content: center; padding: 0;
            width: 48px; height: 48px; border: 0; border-radius: 999px; background: rgba(255, 255, 255, .14);
            color: #fff; font-size: 30px; line-height: 1; cursor: pointer;
        }
        .lightbox button:hover, .lightbox button:focus-visible { background: rgba(255, 255, 255, .28); outline: none; }
        .lightbox .fechar { top: 16px; right: 16px; }
        .lightbox .anterior { left: 16px; top: 50%; transform: translateY(-50%); }
        .lightbox .proxima { right: 16px; top: 50%; transform: translateY(-50%); }
        .lightbox .legenda { position: absolute; left: 16px; right: 16px; bottom: 16px; text-align: center; color: #ddd; font-size: 14px; }
        .lightbox .legenda a { color: #fff; text-decoration: underline; margin-left: 12px; }
    </style>
</head>
<body>
    <header class="topo">
        <div class="container">
            <a href="{{ route('editais.index') }}" class="marca">Receita Leilão</a>
            <span class="sub">Lotes dos leilões eletrônicos da Receita Federal (SLE)</span>
        </div>
    </header>

    <main class="container">
        @if (session('status'))
            <div class="alerta sucesso">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="container">
        Dados obtidos do portal público do <a href="{{ config('sle.base_url') }}/portal" target="_blank" rel="noopener">Sistema de Leilão Eletrônico</a>.
    </footer>

    @include('partials.galeria')
</body>
</html>
