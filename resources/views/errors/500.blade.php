@extends('layouts.app')

@section('title', 'Erro interno')

@section('content')
    <div class="card docs-empty">
        <h1 style="font-size:40px;margin:0">500</h1>
        <p>Algo deu errado. Tente novamente; se o problema persistir, reporte em
            <a href="https://github.com/anomalyco/opencode/issues" target="_blank" rel="noopener">nosso suporte</a>
            com uma descrição do que estava fazendo.</p>
        <p style="margin-top:16px">
            <a class="btn" href="{{ route('dashboard') }}">Ir para o painel</a>
        </p>
    </div>
@endsection