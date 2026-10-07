<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php
        $currentUser = auth()->user();
        $currentTenant = \App\Support\TenantContext::current();
        $routeName = request()->route()?->getName() ?? '';

        if (str_starts_with($routeName, 'cronograma')) {
            $crumb = 'Check-List NR-10';
        } elseif (str_starts_with($routeName, 'prontuario')) {
            $crumb = 'Prontuário NR-10';
        } elseif (str_starts_with($routeName, 'rnc')) {
            $crumb = 'RNC';
        } elseif (str_starts_with($routeName, 'funcionarios')) {
            $crumb = 'Funcionários';
        } elseif (str_starts_with($routeName, 'checklist') || str_starts_with($routeName, 'nc-documents')) {
            $crumb = 'Não Conformidades';
        } elseif (str_starts_with($routeName, 'auditoria')) {
            $crumb = 'Auditoria';
        } elseif (str_starts_with($routeName, 'documentos')) {
            $crumb = 'Gestão de Documentos';
        } elseif (str_starts_with($routeName, 'tenants')) {
            $crumb = 'Clientes';
        } elseif (str_starts_with($routeName, 'usuarios')) {
            $crumb = 'Usuários';
        } elseif (str_starts_with($routeName, 'ajuda')) {
            $crumb = 'Ajuda';
        } else {
            $crumb = 'Dashboard';
        }

        $navGroups = [
            'dashboard' => [
                'label' => null,
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'match' => 'dashboard*'],
                ],
            ],
            'operacao' => [
                'label' => 'Operação',
                'items' => [
                    ['label' => 'Check-List NR-10', 'icon' => 'clipboard', 'route' => 'cronograma.index', 'match' => 'cronograma*'],
                    ['label' => 'Prontuário NR-10', 'icon' => 'file', 'route' => 'prontuario.index', 'match' => 'prontuario*'],
                    ['label' => 'RNC', 'icon' => 'alert', 'route' => 'rnc.index', 'match' => 'rnc.*'],
                    ['label' => 'Funcionários', 'icon' => 'users', 'route' => 'funcionarios.index', 'match' => 'funcionarios*'],
                    ['label' => 'Não Conformidades', 'icon' => 'zap', 'route' => 'checklist.index', 'match' => 'checklist*'],
                ],
            ],
            'documentos' => [
                'label' => 'Documentos',
                'items' => [
                    ['label' => 'Gestão de Documentos', 'icon' => 'folder', 'route' => 'documentos.index', 'match' => 'documentos*'],
                ],
            ],
            'administracao' => [
                'label' => 'Administração',
                'items' => [
                    ['label' => 'Clientes', 'icon' => 'building', 'route' => 'tenants.index', 'match' => 'tenants*', 'visible' => $currentUser->isSuperAdmin()],
                    ['label' => 'Categoria', 'icon' => 'alert', 'route' => 'rnc.categoria.index', 'match' => 'rnc.categoria.*', 'visible' => $currentUser->isAdmin()],
                ],
            ],
            'configuracoes' => [
                'label' => 'Configurações',
                'items' => [
                    ['label' => 'Usuários', 'icon' => 'user', 'route' => 'usuarios.index', 'match' => 'usuarios*', 'visible' => $currentUser->isAdmin()],
                    ['label' => 'Auditoria', 'icon' => 'eye', 'route' => 'auditoria.index', 'match' => 'auditoria*', 'visible' => $currentUser->isAdmin()],
                ],
            ],
        ];
    @endphp

    <header class="app-header">
        <div class="app-header-left">
            <button type="button" class="icon-btn sidebar-toggle" data-sidebar-toggle aria-label="Alternar menu lateral">
                @include('partials.icon', ['name' => 'menu'])
            </button>

            <a class="brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('img/logo-greenjob.png') }}" alt="GreenJob" style="height:34px;width:auto">
            </a>
        </div>

        <div class="app-header-right">
            @if ($currentUser->isSuperAdmin())
                @php
                    $accessible = $currentUser->accessibleTenants();
                @endphp
                <form class="tenant-switcher" method="POST" action="{{ route('tenant.switch') }}">
                    @csrf
                    <select name="tenant_id" onchange="this.form.submit()" title="Trocar de cliente">
                        <option value="">— Cliente —</option>
                        @foreach ($accessible as $t)
                            <option value="{{ $t->id }}" @selected($currentTenant?->id === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </form>
            @else
                <span class="tenant-chip">
                    @include('partials.icon', ['name' => 'building'])
                    {{ $currentTenant?->name ?? auth()->user()->tenant?->name }}
                </span>
            @endif

            <div class="user-chip">
                <span class="user-avatar">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                <span class="user-info">
                    <span class="user-name">{{ $currentUser->name }}</span>
                    <span class="role-badge">{{ $currentUser->role->label() }}</span>
                </span>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="icon-btn" type="submit" title="Sair" aria-label="Sair">
                    @include('partials.icon', ['name' => 'logout'])
                </button>
            </form>
        </div>
    </header>

    <div class="app-layout">
        <button type="button" class="sidebar-scrim" data-sidebar-close aria-label="Fechar menu"></button>

        <aside class="app-sidebar">
            <nav class="sidebar-nav">
                @foreach ($navGroups as $group)
                    @php
                        $visible = array_values(array_filter($group['items'], fn ($item) => ($item['visible'] ?? true) === true));
                    @endphp
                    @if (empty($visible))
                        @continue
                    @endif

                    @if ($group['label'])
                        <div class="sidebar-group-label">{{ $group['label'] }}</div>
                    @endif

                    <div class="sidebar-group">
                        @foreach ($visible as $item)
                            <a class="sidebar-link {{ request()->routeIs($item['match']) ? 'active' : '' }}"
                               href="{{ route($item['route']) }}"
                               title="{{ $item['label'] }}">
                                <span class="sidebar-icon">@include('partials.icon', ['name' => $item['icon']])</span>
                                <span class="sidebar-text">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="sidebar-footer">
                <button type="button" class="icon-btn sidebar-expand-toggle" data-sidebar-expand aria-label="Expandir menu">
                    @include('partials.icon', ['name' => 'chevron-left'])
                </button>
            </div>
        </aside>

        <div class="app-main">
            <nav class="breadcrumb" aria-label="Localização atual">
                <a class="breadcrumb-link" href="{{ route('dashboard') }}">Início</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current">{{ $crumb }}</span>
            </nav>

            <div class="flash-wrap">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('warning'))
                    <div class="alert alert-warning">{{ session('warning') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Corrija os pontos abaixo:</strong>
                        <ul class="pill-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <main class="content">
                @yield('content')
            </main>

            <footer class="footer">
                Gestão de Conformidades NR-10 · Sistema em piloto
            </footer>
        </div>
    </div>

    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>