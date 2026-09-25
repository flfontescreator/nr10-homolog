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
        <form method="POST" action="{{ route('tenants.update', $tenant) }}">
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

            <div class="form-group">
                <label for="address">Endereço</label>
                <input id="address" name="address" type="text" value="{{ old('address', $tenant->address) }}">
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