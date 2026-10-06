{{--
    Fallback de impressão: mesma página do PDF, porém no navegador. Usado quando
    o dompdf não está disponível no servidor (ex.: restrição de extensão na
    hospedagem). O botão abaixo chama window.print().
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $rnc->code }} — {{ $revision->label }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .barra-impressao {
            position: sticky; top: 0; z-index: 10;
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            padding: 10px 16px; background: #fff; border-bottom: 1px solid #d9d9d9;
        }
        .folha { max-width: 900px; margin: 20px auto; padding: 24px; background: #fff; }
        @media print {
            @page { size: A4; margin: 22mm 14mm; }
            .barra-impressao { display: none !important; }
            .folha { max-width: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="barra-impressao">
        <button class="btn" type="button" onclick="window.print()">Imprimir / Salvar como PDF</button>
        <span class="muted small">
            {{ $rnc->code }} · {{ $revision->label }}
            — no PDF use “Salvar como PDF” e desmarque “Cabeçalhos e rodapés”.
        </span>
        <a class="btn btn-secondary" href="{{ route('rnc.show', $rnc) }}">Voltar ao RNC</a>
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
