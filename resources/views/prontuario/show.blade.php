@extends('layouts.app')

@section('title', 'Prontuário — '.$item->display_code)

@section('content')
    <div class="page-header">
        <div>
            <h1>
                <span class="badge badge-green">{{ $item->display_code }}</span>
                Check-list Prontuário NR-10
            </h1>
            <p class="subtitle">
                {{ $item->display_title }}
                @if($item->funcionario)
                    <span class="badge badge-blue">Funcionário: {{ $item->funcionario->nome }}</span>
                @endif
            </p>
        </div>
        <a class="btn btn-secondary" href="{{ $item->funcionario ? route('funcionarios.show', $item->funcionario) : route('prontuario.index') }}">← Voltar</a>
    </div>

    <div class="card">
        <h2 class="card-title">Campos de controle</h2>

        @if($canWrite)
            <form method="POST" action="{{ route('prontuario.update', $item) }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>Evidências</label>
                        <select name="evidencias_status">
                            <option value="">— Selecione —</option>
                            @foreach(['Digital', 'Pendente', 'Nao Aplicado'] as $opt)
                                <option value="{{ $opt }}" @selected($item->evidencias_status === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condição inicial</label>
                        <select name="condicao_inicial">
                            <option value="">-</option>
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
                        <label>Data de verificação</label>
                        <input type="date" name="data_realizacao" value="{{ $item->data_realizacao?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Validade do documento</label>
                        <input type="date" name="data_validade" value="{{ $item->data_validade?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label>Percentual (0 a 100)</label>
                        <input type="number" name="percentual" min="0" max="100" step="0.01"
                               value="{{ old('percentual', $item->percentual) }}">
                        <div class="field-hint">A Média Geral é calculada a partir destes percentuais.</div>
                    </div>
                    <div class="form-group">
                        <label>Prazo para execução</label>
                        <input type="date" name="prazo_execucao" value="{{ $item->prazo_execucao?->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label>Comentários</label>
                    <textarea name="comentarios">{{ old('comentarios', $item->comentarios) }}</textarea>
                </div>

                <button class="btn" type="submit">Salvar alterações</button>
            </form>
        @else
            <dl class="detail-grid">
                <dt>Evidências</dt><dd>{{ $item->evidencias_status ?? '—' }}</dd>
                <dt>Condição inicial</dt><dd>{{ $item->condicao_inicial ?? '—' }}</dd>
                <dt>Criticidade</dt><dd>@include('partials.criticidade', ['criticidade' => $item->criticidade_atual])</dd>
                <dt>Data de verificação</dt><dd>{{ $item->data_realizacao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Validade do documento</dt><dd>{{ $item->data_validade?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Percentual</dt><dd>{{ $item->percentual !== null ? number_format($item->percentual, 0, ',', '.') . '%' : '—' }}</dd>
                <dt>Prazo para execução</dt><dd>{{ $item->prazo_execucao?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Comentários</dt><dd>{{ $item->comentarios ?? '—' }}</dd>
            </dl>
        @endif
    </div>

    @include('partials.evidences', [
        'item' => $item,
        'canWrite' => $canWrite,
        'canDeleteEvidence' => $canDeleteEvidence,
        'uploadRoute' => route('prontuario.evidencia.upload', $item),
        'destroyRoute' => 'evidencia.destroy-prontuario',
    ])
@endsection