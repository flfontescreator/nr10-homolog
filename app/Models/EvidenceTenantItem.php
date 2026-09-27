<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo de biblioteca POR SUB-ITEM DO PLANO (N:N): o mesmo arquivo (Evidence)
 * pode ser anexado a vários sub-itens do cronograma. tenant_item_id na linha da
 * evidência continua sendo a ÂNCORA/origem; este pivô é o vínculo de card.
 */
class EvidenceTenantItem extends Pivot
{
    use BelongsToTenant;

    protected $table = 'evidence_tenant_item';

    protected $fillable = [
        'tenant_id',
        'evidence_id',
        'tenant_item_id',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function tenantItem(): BelongsTo
    {
        return $this->belongsTo(TenantItem::class);
    }
}
