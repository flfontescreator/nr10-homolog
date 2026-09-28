@extends('layouts.app')

@section('title', 'Auditoria')

@section('content')
    @php
        $filter = $filters ?? [];
    @endphp

    <div class="page-header">
        <div>
            <h1>Auditoria do sistema</h1>
            <p class="subtitle">
                {{ $isSuper ? 'Visão geral de todas as ações do sistema.' : 'Ações registradas no seu cliente.' }}
            </p>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('auditoria.index') }}" class="form-grid">
            <div class="form-group">
                <label>Buscar</label>
                <input type="text" name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Resumo, usuário, cliente…">
            </div>
            <div class="form-group">
                <label>Ação</label>
                <select name="action">
                    <option value="">Todas</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" @selected(($filter['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>De</label>
                <input type="date" name="de" value="{{ $filter['de'] ?? '' }}">
            </div>
            <div class="form-group">
                <label>Até</label>
                <input type="date" name="ate" value="{{ $filter['ate'] ?? '' }}">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button class="btn" type="submit">Filtrar</button>
                @if(array_filter($filter))
                    <a class="btn btn-secondary" href="{{ route('auditoria.index') }}">Limpar</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>Data/hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Resumo</th>
                        @if($isSuper)
                            <th>Cliente</th>
                        @endif
                        <th>IP</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td style="white-space:nowrap">{{ $log->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->user?->name ?: '—' }}</td>
                            <td><span class="badge badge-neutral">{{ $log->action }}</span></td>
                            <td style="max-width:320px">{{ $log->summary }}</td>
                            @if($isSuper)
                                <td>{{ $log->tenant?->name ?: 'Plataforma' }}</td>
                            @endif
                            <td>{{ $log->ip ?: '—' }}</td>
                            <td>
                                @if($log->data_old || $log->data_new)
                                    <details>
                                        <summary class="muted" style="cursor:pointer">detalhes</summary>
                                        <div class="audit-diff" style="margin-top:6px;font-size:13px">
                                            @foreach(array_keys($log->data_new ?? $log->data_old ?? []) as $key)
                                                @php
                                                    $old = $log->data_old[$key] ?? null;
                                                    $new = $log->data_new[$key] ?? null;
                                                    $show = fn ($v) => is_array($v) ? json_encode($v) : (is_null($v) ? '∅' : (string) $v);
                                                @endphp
                                                @if($old !== $new)
                                                    <div>
                                                        <strong>{{ $key }}</strong>:
                                                        <span class="muted" style="text-decoration:line-through">{{ $show($old) }}</span>
                                                        →
                                                        <span>{{ $show($new) }}</span>
                                                    </div>
                                                @else
                                                    <div><strong>{{ $key }}</strong>: {{ $show($new) }}</div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isSuper ? 7 : 6 }}" class="muted">Nenhum registro encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px">
            {{ $logs->links() }}
        </div>
    </div>
@endsection