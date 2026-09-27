<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantItem;
use App\Support\DashboardStats;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $tenant = TenantContext::current();

        if (! $tenant) {
            return $this->platformOverview($user->accessibleTenants());
        }

        return view('dashboard', [
            'tenant' => $tenant,
            'stats' => DashboardStats::for($tenant),
        ]);
    }

    /**
     * Telemetria do dashboard: mesmo payload de index() em JSON, com os
     * fragmentos de listagem já renderizados em HTML. Consultado pelo
     * front a cada 60s para atualizar a página sem reload.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenant = TenantContext::current();
        abort_if(! $tenant, 404);

        $data = DashboardStats::for($tenant);
        $data['html'] = [
            'alertas' => view('dashboard.partials._alerts', ['alertas' => $data['alertas']])->render(),
            'setores' => view('dashboard.partials._agregado', ['rows' => $data['setores'], 'coluna' => 'Setor'])->render(),
            'responsaveis' => view('dashboard.partials._agregado', ['rows' => $data['responsaveis'], 'coluna' => 'Responsável'])->render(),
            'evidencias' => view('dashboard.partials._evidencias', ['evidences' => $data['recentEvidences']])->render(),
        ];

        unset($data['alertas'], $data['setores'], $data['responsaveis'], $data['recentEvidences']);

        return response()->json($data);
    }

    protected function platformOverview(Collection $tenants): View
    {
        $tenants = $tenants->map(function (Tenant $tenant) {
            $tenant->itemsCount = TenantItem::query()
                ->where('tenant_id', $tenant->id)->count();

            return $tenant;
        });

        return view('dashboard-platform', ['tenants' => $tenants]);
    }
}
