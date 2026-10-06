@extends('layouts.app')

@section('title', 'Editar cliente — '.$tenant->name)

@section('content')
    <div class="page-header">
        <div>
            <h1>Editar cliente</h1>
            <p class="subtitle">{{ $tenant->name }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('tenants.show', $tenant) }}">← Voltar</a>
    </div>

    <div class="card" style="max-width:720px">
        <form method="POST" action="{{ route('tenants.update', $tenant) }}"
              data-cep-url="{{ route('tenants.cep-lookup', '__CEP__') }}"
              data-cidades-url="{{ route('tenants.cidades') }}"
              data-bairros-url="{{ route('tenants.bairros') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="name">Razão social / nome do cliente *</label>
                <input id="name" name="name" type="text" value="{{ old('name', $tenant->name) }}" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="cnpj">CNPJ</label>
                    <div class="field-with-button">
                        <input id="cnpj" name="cnpj" type="text" value="{{ old('cnpj', $tenant->cnpj) }}" placeholder="00.000.000/0000-00">
                        <button type="button" class="btn btn-secondary" id="btn-buscar-cnpj">Buscar</button>
                    </div>
                    <small class="hint" id="cnpj-status"></small>
                </div>
                <div class="form-group">
                    <label for="contact_name">Contato</label>
                    <input id="contact_name" name="contact_name" type="text" value="{{ old('contact_name', $tenant->contact_name) }}">
                </div>
                <div class="form-group">
                    <label for="contact_email">E-mail de contato</label>
                    <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $tenant->contact_email) }}">
                </div>
                <div class="form-group">
                    <label for="contact_phone">Telefone</label>
                    <input id="contact_phone" name="contact_phone" type="text" value="{{ old('contact_phone', $tenant->contact_phone) }}">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="cep">CEP</label>
                    <div class="field-with-button">
                        <input id="cep" name="cep" type="text" value="{{ old('cep', $tenant->cep) }}" placeholder="00000-000" inputmode="numeric" maxlength="9">
                        <button type="button" class="btn btn-secondary" id="btn-buscar-cep">Buscar</button>
                    </div>
                    <small class="hint" id="cep-status"></small>
                </div>
                <div class="form-group">
                    <label for="numero">Número</label>
                    <input id="numero" name="numero" type="text" value="{{ old('numero', $tenant->numero) }}">
                </div>
                <div class="form-group">
                    <label for="complemento">Complemento</label>
                    <input id="complemento" name="complemento" type="text" value="{{ old('complemento', $tenant->complemento) }}">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Logradouro</label>
                <input id="address" name="address" type="text" value="{{ old('address', $tenant->address) }}" placeholder="Rua, avenida, rodovia…">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="uf">UF</label>
                    <select id="uf" name="uf">
                        <option value="">—</option>
                        @foreach ($ufs as $sigla => $nome)
                            <option value="{{ $sigla }}" @selected(old('uf', $tenant->uf) === $sigla)>{{ $sigla }} — {{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input id="cidade" name="cidade" type="text" value="{{ old('cidade', $tenant->cidade) }}" list="cidades-lista" autocomplete="off">
                    <datalist id="cidades-lista"></datalist>
                </div>
                <div class="form-group">
                    <label for="bairro">Bairro</label>
                    <input id="bairro" name="bairro" type="text" value="{{ old('bairro', $tenant->bairro) }}" list="bairros-lista" autocomplete="off">
                    <datalist id="bairros-lista"></datalist>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                    <input name="is_active" type="checkbox" value="1" style="width:auto"
                           {{ $tenant->is_active ? 'checked' : '' }}>
                    Cliente ativo
                </label>
            </div>

            <button class="btn" type="submit">Salvar alterações</button>
        </form>
    </div>
@endsection
