@extends('layouts.app')

@section('title', 'Editar '.$document->code)

@section('content')
    <div class="page-header">
        <div>
            <h1>
                <span class="badge badge-blue">{{ $document->code }}</span>
                Editar documento
            </h1>
            <p class="subtitle">Alterar título, descrição e a seleção de itens. Cada alteração gera uma nova versão no histórico.</p>
            <span class="badge badge-amber">Rascunho</span>
        </div>
        <div style="display:flex;gap:8px">
            <a class="btn btn-secondary" href="{{ route('nc-documents.show', $document) }}">← Voltar</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Corrija os pontos abaixo:</strong>
            <ul class="pill-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('nc-documents.update', $document) }}">
        @csrf
        @method('PUT')
        @include('nc-documents._selection', [
            'items' => $items,
            'operacional' => $operacional,
            'selected' => $selected,
            'title' => $document->title,
            'description' => $document->description,
            'submitLabel' => 'Salvar e registrar versão',
        ])
    </form>
@endsection