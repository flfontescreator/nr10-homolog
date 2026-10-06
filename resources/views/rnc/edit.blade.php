@extends('layouts.app')

@section('title', 'Editar RNC '.$rnc->code)

@section('content')
    <div class="page-header">
        <div>
            <h1>Editar {{ $rnc->code }}</h1>
            <p class="subtitle">
                @if($rnc->current_revision > 0)
                    A última revisão publicada é <strong>{{ $rnc->makeRevisionLabel($rnc->current_revision) }}</strong>
                    e permanece imutável. As alterações só entram no relatório oficial quando você
                    <strong>publicar de novo</strong>, criando a próxima revisão.
                @else
                    Este relatório ainda é um rascunho: nenhuma revisão oficial foi publicada.
                @endif
            </p>
        </div>
        <a class="btn btn-secondary" href="{{ route('rnc.show', $rnc) }}">Voltar ao relatório</a>
    </div>

    <form method="POST" action="{{ route('rnc.update', $rnc) }}">
        @csrf
        @method('PUT')

        @include('rnc._form', [
            'modelos' => $modelos,
            'projetos' => $projetos,
            'rnc' => $rnc,
        ])

        <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn" type="submit">Salvar cabeçalho</button>
            <a class="btn btn-secondary" href="{{ route('rnc.show', $rnc) }}">Cancelar</a>
        </div>
    </form>
@endsection

