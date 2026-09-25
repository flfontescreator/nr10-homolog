@extends('layouts.app')

@section('title', 'Editar funcionário — '.$funcionario->nome)

@section('content')
    <div class="page-header">
        <div>
            <h1>Editar funcionário</h1>
            <p class="subtitle">{{ $funcionario->nome }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('funcionarios.show', $funcionario) }}">← Voltar</a>
    </div>

    <div class="card" style="max-width:560px">
        <form method="POST" action="{{ route('funcionarios.update', $funcionario) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group">
                    <label for="nome">Nome completo *</label>
                    <input id="nome" name="nome" type="text" value="{{ old('nome', $funcionario->nome) }}" required>
                </div>
                <div class="form-group">
                    <label for="matricula">Matrícula</label>
                    <input id="matricula" name="matricula" type="text" value="{{ old('matricula', $funcionario->matricula) }}" maxlength="60">
                </div>
            </div>

            <button class="btn" type="submit">Salvar alterações</button>
        </form>
    </div>
@endsection