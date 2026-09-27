<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo de biblioteca: um arquivo (Evidence) referenciado por um documento
 * DN. É uma relação N:N — o mesmo arquivo pode ser reutilizado em vários
 * documentos (badges "Documento de referência" em Gestão de Documentos).
 */
class EvidenceDocument extends Pivot
{
    use BelongsToTenant;

    protected $table = 'evidence_document';

    protected $fillable = [
        'tenant_id',
        'evidence_id',
        'document_id',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(NcDocument::class);
    }
}
