<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $rnc->code }} — {{ $revision->label }}</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#1b1f23;line-height:1.5">
    <h2 style="margin:0 0 4px">{{ $rnc->code }} — {{ $revision->label }}</h2>
    <p style="margin:0 0 16px;color:#5b6b73;font-size:14px">{{ $rnc->titulo }}</p>

    <p>Prezado(a),</p>

    <p>
        Segue o Relatório de Não Conformidade <strong>{{ $rnc->code }}</strong>
        ({{ $revision->label }}), emitido em
        {{ $revision->published_at?->format('d/m/Y') }}.
    </p>

    <table cellpadding="0" cellspacing="0" border="0" style="margin:16px 0">
        <tr>
            <td style="padding-right:24px">
                <strong style="font-size:12px;color:#5b6b73">CLIENTE</strong><br>
                {{ $revision->snapshot['cliente'] ?? '—' }}
            </td>
            <td>
                <strong style="font-size:12px;color:#5b6b73">NÃO CONFORMIDADES</strong><br>
                {{ count($revision->snapshot['itens'] ?? []) }}
            </td>
        </tr>
    </table>

    @if($publicUrl)
        <p>
            <a href="{{ $publicUrl }}" style="display:inline-block;background:#1f7a4d;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">
                Abrir o relatório
            </a>
        </p>
        <p style="font-size:13px;color:#5b6b73">
            Este link fica disponível até <strong>{{ $validade }}</strong>.
        </p>
    @endif

    <p style="margin-top:24px;font-size:13px;color:#5b6b73">
        Dúvidas ou correções podem ser solicitadas ao responsável técnico pela emissão do relatório.
    </p>

    <p style="font-size:13px;color:#5b6b73">
        --<br>
        {{ config('app.name') }} — Gestão de Conformidades NR-10
    </p>
</body>
</html>