@extends('layouts.app')

@section('title', 'Editar funcionário — '.$funcionario->nome)

@section('content')
    <div class="page-header">
        <div>
            <h1>Editar funcionário</h1>
            <p class="subtitle">{{ $funcionario->nome }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('funcionarios.index') }}">← Voltar</a>
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
                <div class="form-group">
                    <label for="cpf">CPF</label>
                    <input id="cpf" name="cpf" type="text" inputmode="numeric"
                           value="{{ old('cpf', \App\Support\Cpf::mask($funcionario->cpf)) }}"
                           maxlength="14" placeholder="000.000.000-00"
                           oninput="cpfMask(this)">
                    <div class="field-hint">11 dígitos, com máscara automática. Deixe em branco se não houver.</div>
                </div>
                <div class="form-group">
                    <label for="data_admissao">Data de admissão</label>
                    <input id="data_admissao" name="data_admissao" type="date"
                           value="{{ old('data_admissao', $funcionario->data_admissao?->format('Y-m-d')) }}">
                    <div class="field-hint">Opcional.</div>
                </div>
            </div>

            <script>
                function cpfMask(input) {
                    const digits = input.value.replace(/\D/g, '').slice(0, 11);
                    let masked = digits;
                    if (digits.length > 6) {
                        masked = digits.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
                    } else if (digits.length > 3) {
                        masked = digits.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
                    } else if (digits.length > 0) {
                        masked = digits.replace(/(\d{3})(\d{0,3})/, '$1.$2');
                    }
                    input.value = masked;
                }
            </script>

            <button class="btn" type="submit">Salvar alterações</button>
        </form>
    </div>
@endsection