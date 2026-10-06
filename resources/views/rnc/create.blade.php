@extends('layouts.app')

@section('title', 'Novo RNC')

@section('content')
    <div class="page-header">
        <div>
            <h1>Novo RNC</h1>
            <p class="subtitle">
                Será criado como <strong>rascunho</strong> com o código
                <strong>{{ $nextCode }}</strong>. Escolha o modelo, cadastre as não
                conformidades e publique para gerar a revisão oficial.
            </p>
        </div>
        <a class="btn btn-secondary" href="{{ route('rnc.index') }}">Voltar</a>
    </div>

    <form method="POST" action="{{ route('rnc.store') }}">
        @csrf

        @include('rnc._form', [
            'modelos' => $modelos,
            'projetos' => $projetos,
            'modeloSelecionado' => $modeloSelecionado,
            'rnc' => null,
        ])

        <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn" type="submit">Criar rascunho</button>
            <a class="btn btn-secondary" href="{{ route('rnc.index') }}">Cancelar</a>
        </div>
    </form>
@endsection

