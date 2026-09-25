@extends('layouts.app')

@section('title', 'Página não encontrada')

@section('content')
    <div class="card docs-empty">
        <h1 style="font-size:40px;margin:0">404</h1>
        <p>Não encontramos o que você procura. O item pode ter sido excluído ou o endereço está incorreto.</p>
        <p style="margin-top:16px">
            <a class="btn" href="{{ route('dashboard') }}">Ir para o painel</a>
        </p>
    </div>
@endsection