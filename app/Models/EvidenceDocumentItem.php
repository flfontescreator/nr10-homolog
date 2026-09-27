<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo de biblioteca POR SUB-ITEM do documento (N:N): o mesmo arquivo
 * (Evidence) pode ser anexado a vários sub-itens, de um ou vários documentos.
 * Substitui a coluna única evidences.nc_document_item_id (Fase 7).
 */
class EvidenceDocumentItem extends Pivot
{
    use BelongsToTenant;

    protected $table = 'evidence_document_item';

    protected $fillable = [
        'tenant_id',
        'evidence_id',
        'nc_document_item_id',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function ncDocumentItem(): BelongsTo
    {
        return $this->belongsTo(NcDocumentItem::class);
    }
}
