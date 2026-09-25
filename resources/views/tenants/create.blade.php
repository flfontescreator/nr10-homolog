@extends('layouts.app')

@section('title', 'Novo cliente')

@section('content')
    <div class="page-header">
        <div>
            <h1>Novo cliente</h1>
            <p class="subtitle">Ao salvar, o sistema cria todos os itens/subitens do catálogo NR-10 e o usuário administrador do cliente.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('tenants.index') }}">← Voltar</a>
    </div>

    <div class="card" style="max-width:720px">
        <form method="POST" action="{{ route('tenants.store') }}">
            @csrf

            <div class="form-group">
                <label for="name">Razão social / nome do cliente *</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="cnpj">CNPJ</label>
                    <div class="field-with-button">
                        <input id="cnpj" name="cnpj" type="text" value="{{ old('cnpj') }}" placeholder="00.000.000/0000-00">
                        <button type="button" class="btn btn-secondary" id="btn-buscar-cnpj">Buscar</button>
                    </div>
                    <small class="hint" id="cnpj-status"></small>
                </div>
                <div class="form-group">
                    <label for="contact_name">Contato</label>
                    <input id="contact_name" name="contact_name" type="text" value="{{ old('contact_name') }}">
                </div>
                <div class="form-group">
                    <label for="contact_email">E-mail do administrador (login) *</label>
                    <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}" required>
                    <small class="hint">O e-mail informado será o login de acesso do administrador deste cliente.</small>
                </div>
                <div class="form-group">
                    <label for="contact_phone">Telefone</label>
                    <input id="contact_phone" name="contact_phone" type="text" value="{{ old('contact_phone') }}">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Endereço</label>
                <input id="address" name="address" type="text" value="{{ old('address') }}">
            </div>

            <button class="btn" type="submit">Criar cliente e carregar catálogo</button>
        </form>
    </div>
@endsection