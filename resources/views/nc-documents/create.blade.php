@extends('layouts.app')

@section('title', 'Novo Documento de Não Conformidades')

@section('content')
    <div class="page-header">
        <div>
            <h1>Novo Documento de Não Conformidades</h1>
            <p class="subtitle">Selecione os itens normativos (Cronograma de Adequação) e operacionais (Prontuário NR-10) que se aplicam a este cliente.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('checklist.index') }}">← Voltar</a>
    </div>

    <form method="POST" action="{{ route('nc-documents.store') }}">
        @csrf
        @include('nc-documents._selection', [
            'items' => $items,
            'operacional' => $operacional,
            'selected' => [],
            'title' => '',
            'description' => '',
            'submitLabel' => 'Criar documento',
        ])
    </form>
@endsection