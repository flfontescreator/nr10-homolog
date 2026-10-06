<div class="card">
    <h2 class="card-title">{{ $titulo }}</h2>
    @if($itens->isEmpty())
        <p class="muted">Nenhum registro cadastrado.</p>
    @else
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th style="width:90px">Ordem</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itens as $item)
                        <tr>
                            <td>{{ $item->nome }}</td>
                            <td>{{ $item->ordem }}</td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm" data-toggle-form="edit-{{ $prefixo }}-{{ $item->id }}">Editar</button>
                                <form method="POST" action="{{ route($rotaDestroy, $item) }}" style="display:inline"
                                      data-confirm="Excluir &quot;{{ $item->nome }}&quot;? Esta ação não pode ser desfeita.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-{{ $prefixo }}-{{ $item->id }}" style="display:none">
                            <td colspan="3">
                                <form method="POST" action="{{ route($rotaUpdate, $item) }}" class="form-grid" style="grid-template-columns:1fr auto auto">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-group" style="margin-bottom:0">
                                        <input type="text" name="nome" maxlength="60" value="{{ $item->nome }}" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0">
                                        <input type="number" name="ordem" min="0" max="65535" value="{{ $item->ordem }}" style="width:110px">
                                    </div>
                                    <button class="btn btn-sm" type="submit">Salvar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
