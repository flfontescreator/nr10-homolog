{{-- Link público do RNC: sem login, apenas token + validade de 7 dias. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $rnc->code }} — {{ $revision->label }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .folha { max-width: 900px; margin: 20px auto; padding: 24px; background: #fff; }
        .barra-publica {
            position: sticky; top: 0; z-index: 10;
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            padding: 10px 16px; background: #fff; border-bottom: 1px solid #d9d9d9;
        }
        @media print { @page { margin: 22mm 14mm; } .barra-publica { display: none !important; } .folha { max-width: none; margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <div class="barra-publica">
        <button class="btn" type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
        <a class="btn btn-secondary" href="{{ route('rnc.public.pdf', $revision->public_token) }}">Baixar PDF</a>
        <span class="muted small">
            Link válido até {{ $revision->public_expires_at->format('d/m/Y') }}
        </span>
    </div>

    <div class="folha">
        @include('rnc._document', [
            'rnc' => $rnc,
            'snapshot' => $snapshot,
            'revisionLabel' => $revision->label,
        ])
    </div>
</body>
</html>
