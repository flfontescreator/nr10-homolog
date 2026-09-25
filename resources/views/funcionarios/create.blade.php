@extends('layouts.app')

@section('title', 'Novo funcionário')

@section('content')
    <div class="page-header">
        <div>
            <h1>Novo funcionário</h1>
            <p class="subtitle">Ao salvar, o sistema cria automaticamente os sub-itens 4.1 a 4.8 do prontuário para este funcionário.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('funcionarios.index') }}">← Voltar</a>
    </div>

    <div class="card" style="max-width:560px">
        <form method="POST" action="{{ route('funcionarios.store') }}">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label for="nome">Nome completo *</label>
                    <input id="nome" name="nome" type="text" value="{{ old('nome') }}" required>
                </div>
                <div class="form-group">
                    <label for="matricula">Matrícula</label>
                    <input id="matricula" name="matricula" type="text" value="{{ old('matricula') }}" maxlength="60">
                </div>
            </div>

            <button class="btn" type="submit">Criar funcionário</button>
        </form>
    </div>
@endsection