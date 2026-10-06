@php($editing = isset($rnc) && $rnc->exists)
@php($modeloAtual = old('modelo', $editing ? $rnc->modelo->value : ($modeloSelecionado ?? \App\Enums\RncModelo::Tecnica->value)))

<div class="card">
    <h2 class="card-title">Modelo do relatório</h2>

    @if($editing)
        <input type="hidden" name="modelo" value="{{ $modeloAtual }}">
        <p class="muted">
            <span class="badge badge-blue">{{ \App\Enums\RncModelo::from($modeloAtual)->label() }}</span>
            — o modelo é definido na criação e não pode ser alterado.
        </p>
    @else
        <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
            @foreach($modelos as $value => $label)
                <label style="display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--border);border-radius:8px;cursor:pointer">
                    <input type="radio" name="modelo" value="{{ $value }}" @checked($modeloAtual === $value) required>
                    <span>
                        <strong>{{ $label }}</strong>
                        <span class="muted small" style="display:block">
                            {{ $value === \App\Enums\RncModelo::Fotografica->value
                                ? 'Layout com registro fotográfico, resumo e conclusão.'
                                : 'Layout técnico com inspeção, não conformidades e recomendações.' }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('modelo')<div class="field-hint" style="color:#b3261e">{{ $message }}</div>@enderror
    @endif
</div>

<div class="card">
    <h2 class="card-title">Cabeçalho do relatório</h2>

    <div class="form-group">
        <label for="titulo">Título do relatório *</label>
        <input type="text" name="titulo" id="titulo" required maxlength="255"
               value="{{ old('titulo', $rnc?->titulo) }}"
               placeholder="Ex.: RNC da inspeção mensal de instalações elétricas">
    </div>

    <div class="form-group">
        <label for="descricao">Descrição / objetivo</label>
        <textarea name="descricao" id="descricao" rows="3"
                  placeholder="Ex.: relatório apresentado ao cliente em reunião de 01/10/2026.">{{ old('descricao', $rnc?->descricao) }}</textarea>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label for="projeto_id">Projeto</label>
            <select name="projeto_id" id="projeto_id">
                <option value="">— selecione —</option>
                @foreach($projetos as $projeto)
                    <option value="{{ $projeto->id }}" @selected(old('projeto_id', $rnc?->projeto_id) == $projeto->id)>
                        {{ $projeto->nome }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="data_inspecao">Data de inspeção</label>
            <input type="date" name="data_inspecao" id="data_inspecao"
                   value="{{ old('data_inspecao', $rnc?->data_inspecao?->format('Y-m-d')) }}">
        </div>

        <div class="form-group">
            <label for="responsavel_nome">Responsável pela emissão</label>
            <input type="text" name="responsavel_nome" id="responsavel_nome" maxlength="255"
                   value="{{ old('responsavel_nome', $rnc?->responsavel_nome) }}"
                   placeholder="Ex.: João da Silva">
        </div>

        <div class="form-group">
            <label for="responsavel_cargo">Cargo do responsável</label>
            <input type="text" name="responsavel_cargo" id="responsavel_cargo" maxlength="120"
                   value="{{ old('responsavel_cargo', $rnc?->responsavel_cargo) }}"
                   placeholder="Ex.: Engenheiro Eletricista CREA 12345">
        </div>
    </div>

    <div class="field-hint">
        Cliente, CNPJ e endereço vêm do cadastro do cliente — são eles que aparecem
        no relatório e recebem o envio. O cargo do responsável é armazenado, mas não
        é exibido no relatório.
    </div>
</div>

<div class="card" data-modelo-bloco="tecnica">
    <h2 class="card-title">Blocos do RNC Técnico (Markdown)</h2>

    <div class="form-group">
        <label for="recomendacoes">Recomendações de boas práticas da NR-10</label>
        <textarea name="recomendacoes" id="recomendacoes" rows="6"
                  placeholder="- Recomendação 1&#10;- Recomendação 2">{{ old('recomendacoes', $rnc?->recomendacoes) }}</textarea>
    </div>
</div>

<div class="card" data-modelo-bloco="fotografica">
    <h2 class="card-title">Blocos do RNC Fotográfico (Markdown)</h2>

    <div class="form-group">
        <label for="resumo">Resumo</label>
        <textarea name="resumo" id="resumo" rows="6"
                  placeholder="Resumo do relatório. Aceita **negrito**, *itálico* e listas com -.">{{ old('resumo', $rnc?->resumo) }}</textarea>
    </div>

    <div class="form-group">
        <label for="conclusao">Conclusão</label>
        <textarea name="conclusao" id="conclusao" rows="6"
                  placeholder="- Conclusão 1&#10;- Conclusão 2">{{ old('conclusao', $rnc?->conclusao) }}</textarea>
    </div>
</div>

<script>
    (function () {
        var radios = document.querySelectorAll('input[name="modelo"]');
        var blocos = document.querySelectorAll('[data-modelo-bloco]');

        function aplicar() {
            var selecionado = document.querySelector('input[name="modelo"]:checked');
            var valor = selecionado ? selecionado.value : @json($modeloAtual);

            blocos.forEach(function (bloco) {
                bloco.style.display = bloco.dataset.modeloBloco === valor ? '' : 'none';
            });
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', aplicar);
        });

        aplicar();
    })();
</script>

