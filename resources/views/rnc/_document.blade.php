{{--
    Corpo do relatório RNC, compartilhado entre o PDF (dompdf) e as páginas de
    impressão/link público. CSS próprio, sem dependência de Tailwind.

    $pdfMode = true  -> rodapé com "Página X de Y" (via script PHP do dompdf) e
                        banner no topo da primeira página (nunca position: fixed).
    $embedImages     -> imagens embutidas em base64 (funciona no PDF e no HTML).
--}}
@php
    $itens = $snapshot['itens'] ?? [];
    $fotografica = ($snapshot['modelo'] ?? 'tecnica') === 'fotografica';
    $pdfMode = $pdfMode ?? false;
    $data = fn ($valor) => empty($valor) ? null : \Illuminate\Support\Carbon::parse((string) $valor)->format('d/m/Y');

    $imgSrc = function (array $evidencia) {
        if (empty($evidencia['path'])) {
            return null;
        }

        try {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($evidencia['path'])) {
                $conteudo = \Illuminate\Support\Facades\Storage::disk('local')->get($evidencia['path']);

                return 'data:'.($evidencia['mime'] ?? 'image/jpeg').';base64,'.base64_encode($conteudo);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    };

    // Logomarca GreenJob: embutida em base64 (PDF e HTML não dependem de URL).
    $logoSrc = null;
    $logoArquivo = public_path('img/logo-greenjob.png');

    if (is_file($logoArquivo)) {
        $logoSrc = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoArquivo));
    }

    // Registros fotográficos: uma linha por evidência, numeradas em sequência.
    $fotos = [];
    $sequencia = 0;

    if ($fotografica) {
        foreach ($itens as $item) {
            $evidencias = $item['evidencias'] ?? [];

            if ($evidencias === []) {
                $sequencia++;
                $fotos[] = ['numero' => $sequencia, 'evidencia' => null, 'descricao' => $item['titulo'] ?? ''];

                continue;
            }

            foreach ($evidencias as $evidencia) {
                $sequencia++;
                $fotos[] = [
                    'numero' => $sequencia,
                    'evidencia' => $evidencia,
                    'descricao' => trim((string) ($evidencia['descricao'] ?? '')) ?: (string) ($item['descricao'] ?? $item['titulo'] ?? ''),
                ];
            }
        }
    }
@endphp
<style>
    .doc { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1b1f23; line-height: 1.45; }
    .doc h1 { font-size: 15pt; margin: 0 0 2mm; }
    .doc h2 { font-size: 11.5pt; margin: 7mm 0 2.5mm; padding-bottom: 1.2mm; border-bottom: 1px solid #c9d1d6; text-transform: uppercase; letter-spacing: .4px; }
    .doc h3 { font-size: 10.5pt; margin: 5mm 0 2mm; }
    .doc p { margin: 0 0 2mm; text-align: justify; }
    .doc ul, .doc ol { margin: 0 0 2mm 5mm; padding: 0; }
    .doc table { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
    .doc th, .doc td { border: 1px solid #d5dbdf; padding: 1.8mm 2.2mm; text-align: left; vertical-align: top; font-size: 9pt; }
    .doc th { background: #f2f5f4; font-weight: bold; }
    .doc .doc-topo { margin-bottom: 4mm; }
    .doc .doc-logo { text-align: right; margin-bottom: 2.5mm; }
    .doc .doc-logo img { height: 12mm; width: auto; }
    .doc .doc-banner { text-align: center; font-size: 12pt; font-weight: bold; letter-spacing: 1px; border-bottom: 2px solid #1b1f23; padding-bottom: 2mm; }

    .doc .ident { display: table; width: 100%; margin-bottom: 3mm; }
    .doc .ident > div { display: table-cell; width: 33.33%; font-size: 9pt; padding-right: 3mm; }
    .doc .ident strong { display: block; font-size: 7.5pt; text-transform: uppercase; color: #5b6b73; letter-spacing: .4px; }
    .doc .ident-table th { text-align: center; font-size: 8pt; text-transform: uppercase; }
    .doc .ident-table td { text-align: center; font-weight: bold; }
    .doc .rotulo { width: 40mm; }
    .doc .muted { color: #5b6b73; }
    .doc .vazio { color: #5b6b73; font-style: italic; }
    .doc .descricao { text-align: justify; margin-bottom: 4mm; }
    .doc .descricao-word { margin-bottom: 2mm; }
    .doc .nc { page-break-inside: avoid; margin-bottom: 3mm; }
    .doc .referencias { margin: 0 0 2mm; }
    .doc .referencias span { display: inline-block; border: 1px solid #9aa7ad; border-radius: 3px; padding: .4mm 1.6mm; font-size: 8pt; margin: 0 1mm 1mm 0; }
    .doc td .referencias { margin: 0; }
    .doc table.evid-grid { border-collapse: separate; border-spacing: 2mm; margin-bottom: 2mm; }
    .doc table.evid-grid td { border: 0; padding: 0; width: 33.33%; vertical-align: top; text-align: center; }
    .doc table.evid-grid img { max-width: 100%; max-height: 52mm; }
    .doc table.evid-grid .evid-desc { font-size: 8pt; color: #5b6b73; text-align: left; line-height: 1.3; margin-top: 1mm; }
    .doc table.evid-grid .evid-vazia { text-align: left; }
    .doc table.fotos th { text-align: center; font-size: 8pt; text-transform: uppercase; }
    .doc table.fotos td.foto-num { width: 12mm; text-align: center; font-weight: bold; }
    .doc table.fotos td.foto-cel { width: 50%; text-align: center; }
    .doc table.fotos img { max-width: 100%; max-height: 82mm; }
    .doc .assinatura { margin-top: 16mm; page-break-inside: avoid; }
    .doc .assinatura .linha { border-bottom: 1px solid #1b1f23; width: 90mm; margin-bottom: 1.5mm; }
    .doc .rodape { margin-top: 10mm; padding-top: 2mm; border-top: 1px solid #d5dbdf; font-size: 7.5pt; color: #7b8b93; text-align: center; }
</style>

<div class="doc">
    <div class="doc-topo">
        @if($logoSrc)
            <div class="doc-logo"><img src="{{ $logoSrc }}" alt="GreenJob"></div>
        @endif
        <div class="doc-banner">RELATÓRIO DE NÃO CONFORMIDADE</div>
    </div>

    <div class="ident">
        <div>
            <strong>Cliente</strong>
            {{ $snapshot['cliente'] ?? '—' }}
        </div>
        <div>
            <strong>CNPJ</strong>
            {{ $snapshot['cliente_documento'] ?? '—' }}
        </div>
        <div>
            <strong>Endereço</strong>
            {{ $snapshot['cliente_endereco'] ?? '—' }}
        </div>
    </div>

    @unless($fotografica)
        <h1>{{ $snapshot['titulo'] ?? $rnc->titulo }}</h1>
        @if(! empty($snapshot['descricao']))
            <p class="descricao">{!! nl2br(e($snapshot['descricao'])) !!}</p>
        @endif
    @else
        <p class="descricao-word"><strong>Descrição</strong></p>
    @endunless

    <table class="ident-table">
        <tr>
            <th>Data de Inspeção</th>
            <th>Projeto</th>
            <th>Responsável</th>
            <th>Número</th>
        </tr>
        <tr>
            <td>{{ $data($snapshot['data_inspecao'] ?? null) ?? '—' }}</td>
            <td>{{ $snapshot['projeto'] ?? '—' }}</td>
            <td>{{ $snapshot['responsavel_nome'] ?? '—' }}</td>
            <td>{{ $snapshot['codigo'] ?? $rnc->code }}</td>
        </tr>
    </table>

    @if($fotografica)
        <h2>Resumo</h2>
        {!! \App\Support\Rnc\MarkdownLite::toHtml($snapshot['resumo'] ?? null) ?: '<p class="vazio">Sem conteúdo.</p>' !!}

        <h2>Registro fotográfico</h2>
        @if($fotos === [])
            <p class="vazio">Nenhum registro fotográfico.</p>
        @else
            <table class="fotos">
                <tr>
                    <th class="foto-num">#</th>
                    <th>Registro fotográfico</th>
                    <th>Descrição de não conformidade</th>
                </tr>
                @foreach($fotos as $foto)
                    @php($evidencia = $foto['evidencia'])
                    @php($src = $evidencia ? $imgSrc($evidencia) : null)
                    @php($ehImagem = $evidencia && str_starts_with((string) ($evidencia['mime'] ?? ''), 'image/'))
                    <tr>
                        <td class="foto-num">{{ $foto['numero'] }}</td>
                        <td class="foto-cel">
                            @if($ehImagem && $src)
                                <img src="{{ $src }}" alt="Registro {{ $foto['numero'] }}">
                            @elseif($evidencia)
                                <span class="muted small">{{ $evidencia['nome'] ?? '—' }}</span>
                            @else
                                <span class="muted small">—</span>
                            @endif
                        </td>
                        <td>{!! nl2br(e($foto['descricao'])) !!}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <h2>Conclusão</h2>
        {!! \App\Support\Rnc\MarkdownLite::toHtml($snapshot['conclusao'] ?? null) ?: '<p class="vazio">Sem conteúdo.</p>' !!}
    @else
        <h2>Não conformidades ({{ count($itens) }})</h2>

        @forelse($itens as $item)
            <div class="nc">
                <h3>{{ $item['numero'] }}. {{ $item['titulo'] }}</h3>

                <table>
                    @if(! empty($item['criticidade']))
                        <tr><th class="rotulo">Criticidade</th><td>{{ $item['criticidade'] }}</td></tr>
                    @endif
                    @if(! empty($item['situacao']))
                        <tr><th class="rotulo">Situação</th><td>{{ $item['situacao'] }}</td></tr>
                    @endif
                    @if(! empty($item['descricao']))
                        <tr><th class="rotulo">Descrição</th><td>{!! nl2br(e($item['descricao'])) !!}</td></tr>
                    @endif
                    @if(! empty($item['prazo_adequacao']))
                        <tr><th class="rotulo">Prazo de adequação</th><td>{{ $data($item['prazo_adequacao']) }}</td></tr>
                    @endif
                    @if(! empty($item['data_adequacao']))
                        <tr><th class="rotulo">Data de adequação</th><td>{{ $data($item['data_adequacao']) }}</td></tr>
                    @endif
                    <tr>
                        <th class="rotulo">Referências normativas</th>
                        <td>
                            <div class="referencias">
                                @forelse($item['referencias'] ?? [] as $referencia)
                                    <span>{{ $referencia['codigo'] }}</span>
                                @empty
                                    <span>-</span>
                                @endforelse
                            </div>
                        </td>
                    </tr>
                </table>

                @if(! empty($item['evidencias']))
                    <table class="evid-grid">
                        @foreach(array_chunk($item['evidencias'], 3) as $linha)
                            <tr>
                                @foreach($linha as $evidencia)
                                    @php($src = $imgSrc($evidencia))
                                    <td class="evid-cel">
                                        @if($src && str_starts_with((string) ($evidencia['mime'] ?? ''), 'image/'))
                                            <img src="{{ $src }}" alt="">
                                        @else
                                            <span class="muted">{{ $evidencia['nome'] ?? '—' }}</span>
                                        @endif
                                        @if(! empty($evidencia['descricao']) || ! empty($evidencia['validade']))
                                            <div class="evid-desc">
                                                {!! nl2br(e($evidencia['descricao'] ?? '')) !!}
                                                @if(! empty($evidencia['validade']))
                                                    <span class="muted">— validade {{ $data($evidencia['validade']) }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                @for($coluna = count($linha); $coluna < 3; $coluna++)
                                    <td class="evid-cel evid-vazia"></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                @endif

                @php($referenciasAbaixo = \App\Support\Rnc\ReferenciasNormativas::texto($item['referencias'] ?? []))
                @if(! empty($item['recomendacao']) || ! empty($item['classificacao_risco']) || $referenciasAbaixo)
                    <table>
                        @if(! empty($item['recomendacao']))
                            <tr><th class="rotulo">Recomendação</th><td>{!! nl2br(e($item['recomendacao'])) !!}</td></tr>
                        @endif
                        @if(! empty($item['classificacao_risco']))
                            <tr><th class="rotulo">Classificação de risco</th><td>{{ $item['classificacao_risco'] }}</td></tr>
                        @endif
                        @if($referenciasAbaixo)
                            <tr><th class="rotulo">Referências normativas</th><td>{!! nl2br(e($referenciasAbaixo)) !!}</td></tr>
                        @endif
                    </table>
                @endif
            </div>
        @empty
            <p class="vazio">Nenhuma não conformidade registrada.</p>
        @endforelse

        <h2>Recomendações de boas práticas da NR-10</h2>
        {!! \App\Support\Rnc\MarkdownLite::toHtml($snapshot['recomendacoes'] ?? null) ?: '<p class="vazio">Sem conteúdo.</p>' !!}
    @endif

    <div class="assinatura">
        <div class="linha"></div>
        <div>
            <strong>{{ $snapshot['responsavel_nome'] ?? '—' }}</strong>
            @if(! empty($snapshot['responsavel_cargo']))
                <div class="muted">{{ $snapshot['responsavel_cargo'] }}</div>
            @endif
        </div>
    </div>

    <div class="rodape">
        {{ $snapshot['codigo'] ?? $rnc->code }} · {{ $revisionLabel }} · documento gerado por Gestão de Conformidades NR-10
    </div>
</div>

@if($pdfMode)
    <script type="text/php">
        if (isset($pdf, $fontMetrics)) {
            try {
                $fonte = $fontMetrics->getFont("helvetica", "normal");
                $pdf->page_text(400, 805, "Página {PAGE_NUM} de {PAGE_COUNT}", $fonte, 8, array(0.36, 0.42, 0.45));
            } catch (\Throwable $e) {
            }
        }
    </script>
@endif

