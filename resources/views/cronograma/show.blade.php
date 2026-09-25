@extends('layouts.app')

@section('title', 'Cronograma — '.$item->catalogItem->code)

@section('content')
    @php($cat = $item->catalogItem)

    <div class="page-header">
        <div>
            <h1>
                <span class="badge badge-blue">{{ $cat->code }}</span>
                Cronograma de Adequação
            </h1>
            <p class="subtitle">{{ $cat->title }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('cronograma.index') }}">← Voltar</a>
    </div>

    <div class="card">
        <h2 class="card-title">
            Campos de controle
            @if($item->criticidade_atual)
                @include('partials.criticidade', ['criticidade' => $item->criticidade_atual])
            @endif
        </h2>

        @if($canWrite)
            <form method="POST" action="{{ route('cronograma.update', $item) }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>Data da inspeção</label>
                        <input type="date" name="data_inspecao" value="{{ $item->data_inspecao?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Condição inicial</label>
                        <select name="condicao_inicial">
                            <option value="">—</option>
                            @foreach(\App\Support\CronogramaOptions::condicoesIniciais() as $opcao)
                                <option value="{{ $opcao }}" @selected(old('condicao_inicial', $item->condicao_inicial) === $opcao)>{{ $opcao }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Criticidade</label>
                        <select name="criticidade">
                            <option value="">—</option>
                            @foreach(\App\Support\CronogramaOptions::criticidades() as $opcao)
                                <option value="{{ $opcao }}" @selected(old('criticidade', $item->criticidade_atual) === $opcao)>{{ $opcao }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Setores</label>
                        <div class="setores-add">
                            <select id="novo-setor" aria-label="Adicionar setor">
                                <option value="">Selecionar setor…</option>
                                @foreach(\App\Support\CronogramaOptions::setores() as $opcao)
                                    <option value="{{ $opcao }}">{{ $opcao }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm" id="btn-add-setor">+ Adicionar</button>
                        </div>
                        <div class="setores-list" id="setores-list">
                            @foreach(old('setores', $item->setores_list) as $setor)
                                <span class="badge badge-setor" data-setor="{{ $setor }}">
                                    {{ $setor }}
                                    <input type="hidden" name="setores[]" value="{{ $setor }}">
                                    <button type="button" class="badge-remove" data-remove-setor aria-label="Remover setor" tabindex="-1">&times;</button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        <label>ID - Relatório</label>
                        <input type="text" name="id_relatorio" maxlength="60" value="{{ old('id_relatorio', $item->id_relatorio) }}">
                    </div>
                    <div class="form-group">
                        <label>Prazo de adequação</label>
                        <input type="date" name="prazo_adequacao" value="{{ $item->prazo_adequacao?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Data da realização</label>
                        <input type="date" name="data_realizacao" value="{{ $item->data_realizacao?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Responsável</label>
                        <input type="text" name="responsavel" value="{{ old('responsavel', $item->responsavel) }}">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">—</option>
                            @foreach(\App\Enums\ItemStatus::options() as $value => $label)
                                <option value="{{ $value }}" @selected($item->status?->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Descrição da não conformidade</label>
                    <textarea name="descricao_nc">{{ old('descricao_nc', $item->descricao_nc) }}</textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Ação</label>
                        <textarea name="acao" style="min-height:60px">{{ old('acao', $item->acao) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Ação realizada</label>
                        <textarea name="acao_realizada" style="min-height:60px">{{ old('acao_realizada', $item->acao_realizada) }}</textarea>
                    </div>
                </div>

                <button class="btn" type="submit">Salvar alterações</button>
            </form>
        @else
            <dl class="detail-grid">
                <dt>Data da inspeção</dt><dd>{{ $item->data_inspecao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Condição inicial</dt><dd>{{ $item->condicao_inicial ?? '—' }}</dd>
                <dt>Criticidade</dt><dd>@include('partials.criticidade', ['criticidade' => $item->criticidade_atual])</dd>
                <dt>Setores</dt>
                <dd class="setores-mini">
                    @forelse($item->setores_list as $setor)
                        <span class="badge badge-setor">{{ $setor }}</span>
                    @empty
                        <span class="muted">—</span>
                    @endforelse
                </dd>
                <dt>ID - Relatório</dt><dd>{{ $item->id_relatorio ?? '—' }}</dd>
                <dt>Prazo de adequação</dt><dd>{{ $item->prazo_adequacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Data da realização</dt><dd>{{ $item->data_realizacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Responsável</dt><dd>{{ $item->responsavel ?? '—' }}</dd>
                <dt>Status</dt><dd>{{ $item->status?->label() ?? '—' }}</dd>
                <dt>Descrição da não conformidade</dt><dd>{{ $item->descricao_nc ?? '—' }}</dd>
                <dt>Ação</dt><dd>{{ $item->acao ?? '—' }}</dd>
                <dt>Ação realizada</dt><dd>{{ $item->acao_realizada ?? '—' }}</dd>
            </dl>
        @endif
    </div>

    <div class="card">
        <h2 class="card-title">Informações fixas do catálogo</h2>
        <dl class="detail-grid">
            <dt>Código</dt><dd>{{ $cat->code }}</dd>
            <dt>Criticidade (catálogo)</dt><dd>{{ $cat->criticidade ?: '—' }}</dd>
            <dt>Setor (catálogo)</dt><dd>{{ $cat->setor ?: '—' }}</dd>
            <dt>Detalhamento técnico</dt><dd>{{ $cat->detalhamento ?: '—' }}</dd>
        </dl>
    </div>

    @include('partials.evidences', [
        'item' => $item,
        'canWrite' => $canWrite,
        'canDeleteEvidence' => $canDeleteEvidence,
        'uploadRoute' => route('cronograma.evidencia.upload', $item),
        'destroyRoute' => 'evidencia.destroy-cronograma',
    ])
@endsection