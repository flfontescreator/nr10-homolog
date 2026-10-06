{{-- PDF do RNC via dompdf. @page controla margens e paginação. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $snapshot['codigo'] ?? $rnc->code }} — {{ $revisionLabel }}</title>
    <style>
        {{-- A margem do @page é o recuo do papel. NÃO escrever "body { margin: 0 }"
             aqui: no dompdf isso zera a margem do @page e o relatório sai colado
             na borda da folha (o impressor corta). --}}
        @page { margin: 26mm 14mm 20mm 14mm; }
    </style>
</head>
<body>
@include('rnc._document', [
    'rnc' => $rnc,
    'snapshot' => $snapshot,
    'revisionLabel' => $revisionLabel,
    'pdfMode' => true,
])
</body>
</html>
