<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versão (snapshot) de um documento de não conformidades.
 * Preserva a seleção completa e o estado operacional dos itens naquele momento.
 */
class NcDocumentVersion extends Model
{
    protected $fillable = [
        'document_id',
        'version',
        'selection',
        'summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'selection' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(NcDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
