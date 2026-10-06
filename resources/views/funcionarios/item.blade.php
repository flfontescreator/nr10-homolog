@extends('layouts.app')

@section('title', 'Funcionário — Item '.$item->numero)

@section('content')
    <div class="page-header">
        <div>
            <h1>
                Item {{ $item->numero }}
                <span class="badge badge-green">{{ $funcionario->nome }}</span>
                @if($funcionario->situacao)
                    <span class="badge {{ $funcionario->situacao->isInativo() ? 'badge-neutral' : 'badge-blue' }}">
                        {{ $funcionario->situacao->nome }}
                    </span>
                @endif
            </h1>
            <p class="subtitle">{{ $item->titulo }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('funcionarios.show', $funcionario) }}">← Voltar</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="pill-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php($canEditItem = $canWrite)

    <div class="card">
        <h2 class="card-title">Campos de controle</h2>

        @if($canEditItem)
            <form method="POST" action="{{ route('funcionarios.item.update', [$funcionario, $item]) }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>Título do item *</label>
                        <input type="text" name="titulo" required maxlength="255"
                               value="{{ old('titulo', $item->titulo) }}">
                    </div>
                    <div class="form-group">
                        <label>Situação</label>
                        <select name="situacao_id">
                            <option value="">— Selecione —</option>
                            @foreach($situacoes as $situacao)
                                <option value="{{ $situacao->id }}"
                                        @selected(old('situacao_id', $item->situacao_id) === $situacao->id)>
                                    {{ $situacao->nome }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Prazo para adequação</label>
                        <input type="date" name="prazo_adequacao"
                               value="{{ old('prazo_adequacao', $item->prazo_adequacao?->format('Y-m-d')) }}">
                    </div>
                    <div class="form-group">
                        <label>Data de adequação</label>
                        <input type="date" name="data_adequacao"
                               value="{{ old('data_adequacao', $item->data_adequacao?->format('Y-m-d')) }}">
                    </div>
                    <div class="form-group">
                        <label>Data de verificação</label>
                        <input type="date" name="data_verificacao"
                               value="{{ old('data_verificacao', $item->data_verificacao?->format('Y-m-d')) }}">
                    </div>
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao">{{ old('descricao', $item->descricao) }}</textarea>
                </div>

                <div class="form-group">
                    <label>Comentários</label>
                    <textarea name="comentario">{{ old('comentario', $item->comentario) }}</textarea>
                </div>

                <button class="btn" type="submit">Salvar alterações</button>
            </form>
        @else
            <dl class="detail-grid">
                <dt>Situação</dt><dd>{{ $item->situacao?->nome ?? '—' }}</dd>
                <dt>Prazo para adequação</dt><dd>{{ $item->prazo_adequacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Data de adequação</dt><dd>{{ $item->data_adequacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Data de verificação</dt><dd>{{ $item->data_verificacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Descrição</dt><dd>{{ $item->descricao ?? '—' }}</dd>
                <dt>Comentários</dt><dd>{{ $item->comentario ?? '—' }}</dd>
            </dl>
        @endif
    </div>

    @include('partials.evidences', [
        'item' => $item,
        'canWrite' => $canEditItem,
        'canDeleteEvidence' => $canDeleteEvidence,
        'uploadRoute' => route('funcionarios.item.evidencia.upload', [$funcionario, $item]),
        'destroyRouteResolver' => fn ($evidence) => route('funcionarios.item.evidencia.destroy', [$funcionario, $item, $evidence]),
    ])
@endsection