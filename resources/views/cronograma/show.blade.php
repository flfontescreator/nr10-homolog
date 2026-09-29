@extends('layouts.app')

@section('title', 'Cronograma — '.$item->catalogItem->code)

@section('content')
@php($cat = $item->catalogItem)
@php($working = $working ?? $item)

@if($lockedByDocument ?? false)
    <div class="alert alert-warning">
        Este subitem pertence a um <strong>documento finalizado</strong>. A edição e os anexos
        deste documento ficam bloqueados até ele ser reaberto (o cronograma continua livre).
    </div>
@endif

    <div class="page-header">
        <div>
            <h1>
                <span class="badge badge-blue">{{ $cat->code }}</span>
                Cronograma de Adequação
            </h1>
            <p class="subtitle">{{ $cat->title }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ $backUrl }}">← Voltar</a>
    </div>

    <div class="card">
        <h2 class="card-title">
            Campos de controle
            @if($working->criticidade_atual)
                @include('partials.criticidade', ['criticidade' => $working->criticidade_atual])
            @endif
        </h2>

        @if($canWrite)
            <form method="POST" action="{{ $updateRoute }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>Data da inspeção</label>
                        <input type="date" name="data_inspecao" value="{{ $working->data_inspecao?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Condição inicial</label>
                        <select name="condicao_inicial">
                            <option value="">-</option>
                            @foreach(\App\Support\CronogramaOptions::condicoesIniciais() as $opcao)
                                <option value="{{ $opcao }}" @selected(old('condicao_inicial', $working->condicao_inicial) === $opcao)>{{ $opcao }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Criticidade</label>
                        <select name="criticidade">
                            <option value="">—</option>
                            @foreach(\App\Support\CronogramaOptions::criticidades() as $opcao)
                                <option value="{{ $opcao }}" @selected(old('criticidade', $working->criticidade_atual) === $opcao)>{{ $opcao }}</option>
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
                            @foreach(old('setores', $working->setores_list) as $setor)
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
                        <input type="text" name="id_relatorio" maxlength="60" value="{{ old('id_relatorio', $working->id_relatorio) }}">
                    </div>
                    <div class="form-group">
                        <label>Prazo de adequação</label>
                        <input type="date" name="prazo_adequacao" value="{{ $working->prazo_adequacao?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Data de verificação</label>
                        <input type="date" name="data_realizacao" value="{{ $working->data_realizacao?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Responsável</label>
                        <input type="text" name="responsavel" value="{{ old('responsavel', $working->responsavel) }}">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">—</option>
                            @foreach(\App\Enums\ItemStatus::options() as $value => $label)
                                <option value="{{ $value }}" @selected($working->status?->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Descrição da não conformidade</label>
                    <textarea name="descricao_nc">{{ old('descricao_nc', $working->descricao_nc) }}</textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Ação</label>
                        <textarea name="acao" style="min-height:60px">{{ old('acao', $working->acao) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Ação realizada</label>
                        <textarea name="acao_realizada" style="min-height:60px">{{ old('acao_realizada', $working->acao_realizada) }}</textarea>
                    </div>
                </div>

                <button class="btn" type="submit">Salvar alterações</button>
            </form>
        @else
            <dl class="detail-grid">
                <dt>Data da inspeção</dt><dd>{{ $working->data_inspecao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Condição inicial</dt><dd>{{ $working->condicao_inicial ?? '—' }}</dd>
                <dt>Criticidade</dt><dd>@include('partials.criticidade', ['criticidade' => $working->criticidade_atual])</dd>
                <dt>Setores</dt>
                <dd class="setores-mini">
                    @forelse($working->setores_list as $setor)
                        <span class="badge badge-setor">{{ $setor }}</span>
                    @empty
                        <span class="muted">—</span>
                    @endforelse
                </dd>
                <dt>ID - Relatório</dt><dd>{{ $working->id_relatorio ?? '—' }}</dd>
                <dt>Prazo de adequação</dt><dd>{{ $working->prazo_adequacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Data de verificação</dt><dd>{{ $working->data_realizacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Responsável</dt><dd>{{ $working->responsavel ?? '—' }}</dd>
                <dt>Status</dt><dd>{{ $working->status?->label() ?? '—' }}</dd>
                <dt>Descrição da não conformidade</dt><dd>{{ $working->descricao_nc ?? '—' }}</dd>
                <dt>Ação</dt><dd>{{ $working->acao ?? '—' }}</dd>
                <dt>Ação realizada</dt><dd>{{ $working->acao_realizada ?? '—' }}</dd>
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
        'evidences' => $evidences,
        'canWrite' => $canWrite,
        'canDeleteEvidence' => $canDeleteEvidence,
        'uploadRoute' => $uploadRoute,
        'destroyRoute' => 'cronograma.evidencia.destroy',
        'destroyRouteModel' => $item,
        'destroyRouteParams' => $destroyRouteParams ?? [],
        'libraryAttachRoute' => $libraryAttachRoute ?? null,
        'libraryAvailable' => $libraryAvailable ?? collect(),
    ])
@endsection