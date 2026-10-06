@extends('layouts.app')

@section('title', $rnc->code)

@section('content')
    @php($normaItensJson = $normas->mapWithKeys(fn ($norma) => [
        (string) $norma->id => $norma->itens->map(fn ($item) => [
            'id' => $item->id,
            'codigo' => $item->codigo,
            'descricao' => $item->descricao,
        ])->values(),
    ]))

    <div class="page-header">
        <div>
            <h1>
                {{ $rnc->code }}
                <span class="badge {{ $rnc->status->isPublished() ? 'badge-green' : 'badge-neutral' }}">
                    {{ $rnc->status->label() }}
                </span>
                <span class="badge badge-blue">{{ $rnc->modelo->label() }}</span>
                @if($rnc->current_revision > 0)
                    <span class="badge badge-blue">{{ $rnc->makeRevisionLabel($rnc->current_revision) }}</span>
                @endif
            </h1>
            <p class="subtitle">{{ $rnc->titulo }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-secondary" href="{{ route('rnc.index') }}">Voltar</a>
            <a class="btn btn-secondary" href="{{ route('rnc.edit', $rnc) }}">Editar cabeçalho</a>
        </div>
    </div>

    @if($publicacaoPendente && $latestRevision)
        <div class="alert alert-warning">
            O relatório publicado (<strong>{{ $latestRevision->label }}</strong>) está desatualizado:
            há alterações que ainda <strong>não estão</strong> no PDF e no link público. Clique em
            <strong>Atualizar publicação</strong> para reemitir
            <strong>{{ $latestRevision->label }}</strong> com o conteúdo atual — sem criar nova revisão.
        </div>
    @endif

    {{-- Cabeçalho --}}
    <div class="card">
        <h2 class="card-title">Cabeçalho</h2>
        <div class="table-wrap">
            <table class="grid">
                <tbody>
                    <tr><th>Cliente</th><td>{{ $rnc->tenant?->name ?: '—' }}</td></tr>
                    <tr><th>CNPJ</th><td>{{ $rnc->tenant?->cnpj ?: '—' }}</td></tr>
                    <tr><th>Endereço</th><td>{{ $rnc->tenant?->enderecoCompleto() ?: '—' }}</td></tr>
                    <tr><th>Projeto</th><td>{{ $rnc->projeto?->nome ?: '—' }}</td></tr>
                    <tr><th>Data de inspeção</th><td>{{ $rnc->data_inspecao?->format('d/m/Y') ?: '—' }}</td></tr>
                    <tr>
                        <th>Responsável</th>
                        <td>{{ $rnc->responsavel_nome ?: '—' }}@if($rnc->responsavel_cargo) — {{ $rnc->responsavel_cargo }}@endif</td>
                    </tr>
                    <tr>
                        <th>Criado por</th>
                        <td>{{ $rnc->creator?->name ?: '—' }} em {{ $rnc->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($rnc->descricao)
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                <strong>Descrição</strong>
                <p class="muted" style="margin:6px 0 0;white-space:pre-line">{{ $rnc->descricao }}</p>
            </div>
        @endif
    </div>

    {{-- Não conformidades --}}
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <h2 class="card-title" style="margin:0">Não conformidades ({{ $rnc->items->count() }})</h2>
            <button class="btn btn-sm" type="button" data-toggle="nova-nc">+ Nova não conformidade</button>
        </div>

        <div class="card" id="nova-nc" style="display:none;margin-top:14px">
            <h3 class="card-title" style="margin-top:0;font-size:15px">Adicionar não conformidade</h3>
            <form method="POST" action="{{ route('rnc.item.store', $rnc) }}">
                @csrf
                <div class="form-group">
                    <label>Título *</label>
                    <input type="text" name="titulo" required maxlength="255"
                           placeholder="Ex.: Ausência de proteção termométrica no painel de distribuição">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Criticidade</label>
                        <select name="criticidade_id">
                            <option value="">— selecione —</option>
                            @foreach($criticidades as $criticidade)
                                <option value="{{ $criticidade->id }}">{{ $criticidade->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    @unless($rnc->isFotografica())
                        <div class="form-group">
                            <label>Classificação de risco</label>
                            <select name="classificacao_risco_id">
                                <option value="">— selecione —</option>
                                @foreach($classificacoes as $classificacao)
                                    <option value="{{ $classificacao->id }}">{{ $classificacao->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endunless
                    <div class="form-group">
                        <label>Situação</label>
                        <select name="situacao_id">
                            <option value="">— não avaliado —</option>
                            @foreach($situacoes as $situacao)
                                <option value="{{ $situacao->id }}">{{ $situacao->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Descrição da NC</label>
                    <textarea name="descricao" rows="3" placeholder="O que foi encontrado, em que condição."></textarea>
                </div>

                <div class="form-group">
                    <label>Recomendação</label>
                    <textarea name="recomendacao" rows="3" placeholder="O que será feito para corrigir."></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Prazo de adequação</label>
                        <input type="date" name="prazo_adequacao">
                    </div>
                    <div class="form-group">
                        <label>Data de adequação</label>
                        <input type="date" name="data_adequacao">
                    </div>
                </div>

                @include('rnc._referencias', ['normas' => $normas, 'selected' => collect()])

                <button class="btn" type="submit" style="margin-top:12px">Adicionar</button>
            </form>
        </div>

        @if($rnc->items->isEmpty())
            <div class="docs-empty">Nenhuma não conformidade registrada. É preciso ao menos uma para publicar.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:12px;margin-top:14px">
                @foreach($rnc->items as $item)
                    <div style="border:1px solid var(--border);border-radius:8px;padding:12px">
                        <div style="display:flex;gap:10px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap">
                            <div style="flex:1;min-width:220px">
                                <strong>{{ $item->numero }}. {{ $item->titulo }}</strong>
                                <div class="muted small">
                                    @if($item->criticidade){{ $item->criticidade->nome }}@endif
                                    @if($item->classificacaoRisco) · risco {{ $item->classificacaoRisco->nome }}@endif
                                    @if($item->prazo_adequacao) · prazo {{ $item->prazo_adequacao->format('d/m/Y') }}@endif
                                </div>
                            </div>
                            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                <span class="badge badge-neutral">{{ $item->situacao?->nome ?: '—' }}</span>
                                @if($item->prazoVencido())
                                    <span class="badge badge-red">Prazo vencido</span>
                                @endif
                                <span class="badge badge-neutral">{{ $item->evidences->count() }} evid.</span>
                                <button class="btn btn-sm btn-secondary" type="button" data-toggle="editar-nc-{{ $item->id }}">Editar</button>
                            </div>
                        </div>

                        @if($item->descricao)
                            <p class="muted" style="margin:10px 0 0;white-space:pre-line">{{ $item->descricao }}</p>
                        @endif

                        @if($item->recomendacao)
                            <div style="margin-top:10px;padding:10px;background:var(--bg-soft,#f6f8f7);border-radius:6px">
                                <strong class="small">Recomendação</strong>
                                <div style="white-space:pre-line">{{ $item->recomendacao }}</div>
                            </div>
                        @endif

                        <div class="setores-list" style="margin-top:10px">
                            @forelse($item->normaItens as $referencia)
                                <span class="badge badge-setor">{{ $referencia->codigo }}</span>
                            @empty
                                <span class="badge badge-setor">-</span>
                            @endforelse
                        </div>

                        <div id="editar-nc-{{ $item->id }}" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                            <form method="POST" action="{{ route('rnc.item.update', [$rnc, $item]) }}">
                                @csrf
                                @method('PUT')

                <div class="form-group">
                    <label>Título da NC</label>
                    <input type="text" name="titulo" maxlength="255" value="{{ $item->titulo }}" placeholder="Ex.: Ausência de proteção termométrica">
                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Criticidade</label>
                                        <select name="criticidade_id">
                                            <option value="">— selecione —</option>
                                            @foreach($criticidades as $criticidade)
                                                <option value="{{ $criticidade->id }}" @selected($item->criticidade_id === $criticidade->id)>{{ $criticidade->nome }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @unless($rnc->isFotografica())
                                        <div class="form-group">
                                            <label>Classificação de risco</label>
                                            <select name="classificacao_risco_id">
                                                <option value="">— selecione —</option>
                                                @foreach($classificacoes as $classificacao)
                                                    <option value="{{ $classificacao->id }}" @selected($item->classificacao_risco_id === $classificacao->id)>{{ $classificacao->nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endunless
                                    <div class="form-group">
                                        <label>Situação</label>
                                        <select name="situacao_id">
                                            <option value="">— não avaliado —</option>
                                            @foreach($situacoes as $situacao)
                                                <option value="{{ $situacao->id }}" @selected($item->situacao_id === $situacao->id)>{{ $situacao->nome }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Descrição da NC</label>
                                    <textarea name="descricao" rows="3">{{ $item->descricao }}</textarea>
                                </div>

                                <div class="form-group">
                                    <label>Recomendação</label>
                                    <textarea name="recomendacao" rows="3">{{ $item->recomendacao }}</textarea>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Prazo de adequação</label>
                                        <input type="date" name="prazo_adequacao" value="{{ $item->prazo_adequacao?->format('Y-m-d') }}">
                                    </div>
                                    <div class="form-group">
                                        <label>Data de adequação</label>
                                        <input type="date" name="data_adequacao" value="{{ $item->data_adequacao?->format('Y-m-d') }}">
                                    </div>
                                </div>

                                @include('rnc._referencias', ['normas' => $normas, 'selected' => $item->normaItens])

                                <button class="btn" type="submit" style="margin-top:12px">Salvar</button>
                            </form>

                            <form method="POST" action="{{ route('rnc.item.destroy', [$rnc, $item]) }}"
                                  style="margin-top:10px"
                                  data-confirm="Remover a não conformidade {{ $item->numero }} e suas evidências?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Remover NC</button>
                            </form>

                            @include('partials.evidences', [
                                'evidences' => $item->evidences,
                                'canWrite' => true,
                                'canDeleteEvidence' => true,
                                'uploadRoute' => route('rnc.item.evidencia.upload', [$rnc, $item]),
                                'destroyRouteResolver' => fn ($evidence) => route('rnc.item.evidencia.destroy', [$rnc, $item, $evidence]),
                            ])
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Publicação --}}
    <div class="card">
        <h2 class="card-title">Publicar</h2>

        @if($rnc->items->isEmpty())
            <p class="muted">
                Adicione ao menos uma não conformidade para publicar. Uma publicação exige
                <strong>título</strong> e <strong>responsável pela emissão</strong> preenchidos,
                porque o relatório leva assinatura.
            </p>
        @else
            @if($rnc->current_revision > 0 && $latestRevision)
                <p class="muted">
                    Este RNC já está publicado como <strong>{{ $latestRevision->label }}</strong>.
                    A edição das NCs é livre; para o relatório público acompanhar, escolha:
                </p>
                <p class="muted small" style="margin-top:-4px">
                    <strong>Atualizar publicação</strong> — reemite
                    <strong>{{ $latestRevision->label }}</strong> com o conteúdo atual,
                    <strong>sem criar revisão nova</strong> e com o mesmo link (renovado por
                    {{ \App\Models\RncRevision::PUBLIC_LINK_DAYS }} dias).<br>
                    <strong>Publicar nova revisão</strong> — congela
                    <strong>{{ $latestRevision->label }}</strong> e cria
                    <strong>{{ $rnc->makeRevisionLabel($rnc->current_revision + 1) }}</strong>
                    com link próprio.
                </p>

                <form method="POST" action="{{ route('rnc.republish', $rnc) }}">
                    @csrf
                    <div class="form-group">
                        <label for="notas">Notas da publicação (opcional)</label>
                        <input type="text" name="notas" id="notas" maxlength="1000"
                               placeholder="Ex.: versão apresentada ao cliente em 01/10/2026.">
                    </div>
                    <button class="btn" type="submit">
                        Atualizar publicação ({{ $latestRevision->label }})
                    </button>
                </form>

                <form method="POST" action="{{ route('rnc.publish', $rnc) }}"
                      style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)"
                      data-confirm="Criar {{ $rnc->makeRevisionLabel($rnc->current_revision + 1) }}? A {{ $latestRevision->label }} fica guardada no histórico e continua com o link dela.">
                    @csrf
                    <div class="form-group">
                        <label for="notas-revisao">Notas desta revisão (opcional)</label>
                        <input type="text" name="notas" id="notas-revisao" maxlength="1000"
                               placeholder="Ex.: correção da NC 2 após retorno do cliente.">
                    </div>
                    <button class="btn btn-secondary" type="submit">
                        Publicar nova revisão ({{ $rnc->makeRevisionLabel($rnc->current_revision + 1) }})
                    </button>
                </form>
            @else
                <p class="muted">
                    Publicar congela o relatório na primeira revisão: gera o Markdown, o PDF e um
                    link público válido por {{ \App\Models\RncRevision::PUBLIC_LINK_DAYS }} dias.
                    Depois disso você pode continuar editando — é só atualizar a publicação.
                </p>

                <form method="POST" action="{{ route('rnc.publish', $rnc) }}">
                    @csrf
                    <div class="form-group">
                        <label for="notas">Notas da publicação (opcional)</label>
                        <input type="text" name="notas" id="notas" maxlength="1000"
                               placeholder="Ex.: versão apresentada ao cliente em 01/10/2026.">
                    </div>
                    <button class="btn" type="submit">
                        Publicar como {{ $rnc->makeRevisionLabel($rnc->current_revision + 1) }}
                    </button>
                </form>
            @endif
        @endif
    </div>

    {{-- Revisões --}}
    <div class="card">
        <h2 class="card-title">Revisões ({{ $rnc->revisions->count() }})</h2>

        @if($rnc->revisions->isEmpty())
            <div class="docs-empty">Nenhuma revisão publicada ainda.</div>
        @else
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Revisão</th>
                            <th>Publicada</th>
                            <th>Notas</th>
                            <th>Link público</th>
                            <th class="text-right">Arquivos</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rnc->revisions as $revision)
                            @php($linkExpirado = $revision->publicLinkExpired())
                            <tr>
                                <td><strong>{{ $revision->label }}</strong></td>
                                <td class="small">
                                    {{ $revision->published_at?->format('d/m/Y H:i') }}
                                    @if($revision->publisher)
                                        <div class="muted small">por {{ $revision->publisher->name }}</div>
                                    @endif
                                </td>
                                <td class="small muted">{{ \Illuminate\Support\Str::limit($revision->notas, 50) ?: '—' }}</td>
                                <td>
                                    @if($linkExpirado)
                                        <span class="badge badge-red">Expirado</span>
                                    @elseif($revision->publicLinkExpiringSoon())
                                        <span class="badge badge-red">Vence em {{ $revision->public_expires_at->format('d/m') }}</span>
                                    @else
                                        <span class="badge badge-green">Ativo até {{ $revision->public_expires_at->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <a class="btn btn-sm" href="{{ route('rnc.revision.pdf', [$rnc, $revision]) }}">PDF</a>
                                    <a class="btn btn-sm btn-secondary" href="{{ route('rnc.revision.markdown', [$rnc, $revision]) }}">MD</a>
                                    <a class="btn btn-sm btn-secondary" href="{{ route('rnc.revision.print', [$rnc, $revision]) }}" target="_blank">Imprimir</a>
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    @unless($linkExpirado)
                                        <a class="btn btn-sm btn-secondary" href="{{ $revision->publicUrl() }}" target="_blank">Abrir link</a>
                                        <form method="POST" action="{{ route('rnc.revision.send', [$rnc, $revision]) }}" style="display:inline">
                                            @csrf
                                            <button class="btn btn-sm btn-secondary" type="submit">Enviar</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('rnc.revision.link.renew', [$rnc, $revision]) }}" style="display:inline">
                                        @csrf
                                        <button class="btn btn-sm btn-secondary" type="submit">Renovar link</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="field-hint" style="margin-top:12px">
            O envio usa o e-mail de contato cadastrado do cliente
            @if($rnc->tenant?->contact_email)
                (<strong>{{ $rnc->tenant->contact_email }}</strong>)
            @else
                — <strong>não cadastrado</strong>, então o envio ficará indisponível até o cliente ter um e-mail.
            @endif
        </div>
    </div>

    <script>
        window.rncNormaItens = @json($normaItensJson);

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-toggle]');

            if (!trigger) {
                return;
            }

            var panel = document.getElementById(trigger.dataset.toggle);

            if (panel) {
                panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            }
        });

        (function () {
            var dados = window.rncNormaItens || {};

            function buscarItem(normaId, itemId) {
                var itens = dados[normaId] || [];

                for (var i = 0; i < itens.length; i++) {
                    if (String(itens[i].id) === String(itemId)) {
                        return itens[i];
                    }
                }

                return null;
            }

            function iniciar(root) {
                var normaSelect = root.querySelector('[data-norma-select]');
                var itemSelect = root.querySelector('[data-norma-itens]');
                var lista = root.querySelector('[data-referencias-list]');

                if (!normaSelect || !itemSelect || !lista) {
                    return;
                }

                function marcarVazio() {
                    if (lista.querySelector('[data-referencia]')) {
                        return;
                    }

                    if (!lista.querySelector('[data-referencia-vazio]')) {
                        var vazio = document.createElement('span');
                        vazio.className = 'badge badge-setor';
                        vazio.dataset.referenciaVazio = '1';
                        vazio.textContent = '-';
                        lista.appendChild(vazio);
                    }
                }

                function limparVazio() {
                    var vazio = lista.querySelector('[data-referencia-vazio]');

                    if (vazio) {
                        vazio.remove();
                    }
                }

                normaSelect.addEventListener('change', function () {
                    var itens = dados[normaSelect.value] || [];

                    itemSelect.innerHTML = '<option value="">— itens —</option>';

                    itens.forEach(function (item) {
                        var opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = item.codigo + (item.descricao ? ' — ' + item.descricao : '');
                        itemSelect.appendChild(opt);
                    });

                    itemSelect.disabled = itens.length === 0;
                    itemSelect.value = '';
                });

                if (normaSelect.value) {
                    normaSelect.dispatchEvent(new Event('change'));
                }

                root.querySelector('[data-add-referencia]').addEventListener('click', function () {
                    var id = itemSelect.value;

                    if (!id || lista.querySelector('[data-referencia][data-id="' + id + '"]')) {
                        return;
                    }

                    var item = buscarItem(normaSelect.value, id);
                    var span = document.createElement('span');
                    span.className = 'badge badge-setor';
                    span.dataset.referencia = '1';
                    span.dataset.id = id;

                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'norma_item_ids[]';
                    input.value = id;
                    span.appendChild(input);

                    span.appendChild(document.createTextNode(item ? item.codigo : id));

                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'badge-remove';
                    remove.dataset.removeReferencia = '1';
                    remove.innerHTML = '&times;';
                    remove.tabIndex = -1;
                    span.appendChild(remove);

                    lista.appendChild(span);
                    limparVazio();
                });

                lista.addEventListener('click', function (event) {
                    var botao = event.target.closest('[data-remove-referencia]');

                    if (botao) {
                        botao.closest('[data-referencia]').remove();
                        marcarVazio();
                    }
                });
            }

            document.querySelectorAll('[data-referencias]').forEach(iniciar);
        })();

        // Reabre o painel da NC (após anexar/remover evidência ou após criá-la) e
        // ancora no campo "Anexar arquivo" para não precisar rolar a página.
        @php($itemIdAberto = session('open_item_id') ?? session('new_item_id'))

        @if($itemIdAberto)
            (function () {
                var id = {{ (int) $itemIdAberto }};
                var painel = document.getElementById('editar-nc-' + id);
                var botao = document.querySelector('[data-toggle="editar-nc-' + id + '"]');

                if (painel) {
                    painel.style.display = 'block';
                    if (botao) botao.textContent = 'Ocultar';

                    var campoAnexo = painel.querySelector('input[type="file"]');
                    (campoAnexo || painel).scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            })();
        @endif
    </script>
@endsection

