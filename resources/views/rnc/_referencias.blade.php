{{--
    Referências normativas de uma NC. O picker carrega os itens da norma
    selecionada (ex.: NR-10) e "Inserir" vira badge com input oculto
    `norma_item_ids[]` — mesmo padrão do picker de setores.
--}}
@php($selecionados = $selected ?? collect())

<div data-referencias>
    <label>Referências normativas</label>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <select data-norma-select style="max-width:220px">
            <option value="">— norma —</option>
            @foreach($normas as $norma)
                <option value="{{ $norma->id }}" @selected($loop->first) @disabled($norma->itens->isEmpty())>
                    {{ $norma->codigo }}{{ $norma->itens->isEmpty() ? ' (sem itens)' : '' }}
                </option>
            @endforeach
        </select>

        <select data-norma-itens disabled style="max-width:320px">
            <option value="">— itens —</option>
        </select>

        <button class="btn btn-sm btn-secondary" type="button" data-add-referencia>Inserir</button>
    </div>

    <div class="setores-list" data-referencias-list>
        @forelse($selecionados as $item)
            <span class="badge badge-setor" data-referencia data-id="{{ $item->id }}">
                <input type="hidden" name="norma_item_ids[]" value="{{ $item->id }}">
                {{ $item->codigo }}
                <button type="button" class="badge-remove" data-remove-referencia
                        aria-label="Remover referência" tabindex="-1">&times;</button>
            </span>
        @empty
            <span class="badge badge-setor" data-referencia-vazio>-</span>
        @endforelse
    </div>
</div>

