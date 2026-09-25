<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\Evidence;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
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
            'cronogramaTotal' => TenantItem::query()
                ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Cronograma->value))
                ->count(),
            'prontuarioTotal' => TenantItem::query()
                ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Prontuario->value))
                ->count(),
            'checklistTotal' => TenantItem::query()
                ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Checklist->value))
                ->count(),
            'cronogramaComAcao' => TenantItem::query()
                ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Cronograma->value))
                ->whereNotNull('acao')
                ->count(),
            'checklistComAcao' => TenantItem::query()
                ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Checklist->value))
                ->whereNotNull('acao')
                ->count(),
            'prontuarioMedia' => TenantItem::query()
                ->join('catalog_items', 'catalog_items.id', '=', 'tenant_items.catalog_item_id')
                ->where('catalog_items.source', Source::Prontuario->value)
                ->whereNotNull('tenant_items.percentual')
                ->avg('tenant_items.percentual'),
            'evidenciasCount' => Evidence::count(),
            'recentEvidences' => Evidence::with(['tenantItem.catalogItem', 'uploader'])
                ->latest()
                ->limit(6)
                ->get(),
        ]);
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
