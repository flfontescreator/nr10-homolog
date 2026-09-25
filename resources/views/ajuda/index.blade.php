@extends('layouts.app')

@section('title', 'Ajuda e documentação')

@section('content')
    <div class="page-header">
        <div>
            <h1>Ajuda e documentação</h1>
            <p class="subtitle">Como o sistema funciona, papéis de acesso e regras de operação.</p>
        </div>
    </div>

    @if($tenant)
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-value">{{ $counts['cronograma'] ?? 0 }}</div>
                <div class="stat-label">Subitens no cronograma</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $counts['prontuario'] ?? 0 }}</div>
                <div class="stat-label">Itens no prontuário</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $tenant->name }}</div>
                <div class="stat-label">Cliente ativo</div>
            </div>
        </div>
    @endif

    @foreach($defaults->groupBy('module') as $module => $items)
        <div class="card">
            <h2 class="card-title">{{ $module }}</h2>
            <table class="grid">
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td style="width:220px;white-space:nowrap"><strong>{{ $item['key'] }}</strong></td>
                            <td>{{ $item['value'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="card">
        <h2 class="card-title">Sobre o piloto</h2>
        <p>
            Este é um <strong>sistema em piloto</strong> para gestão de conformidades NR-10. O catálogo de
            itens/subitens é fixo e vem das planilhas oficiais. Os campos de controle são preenchidos por cliente.
        </p>
        <ul class="pill-list">
            <li>Banco de dados usado: MySQL (somente).</li>
            <li>Publicação futura: Hostinger (primeiro Business, depois Cloud).</li>
            <li>Menus no topo podem migrar para menu lateral em versões futuras.</li>
            <li>Controle de versão do código com histórico no GitHub.</li>
        </ul>
    </div>
@endsection