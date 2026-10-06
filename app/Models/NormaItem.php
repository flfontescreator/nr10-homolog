<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Item de norma técnica (ex.: "10.3.1 ..." da NR-10) usado como referência
 * normativa de uma não conformidade. Catálogo GLOBAL, ligado à sua norma.
 */
class NormaItem extends Model
{
    protected $table = 'norma_itens';

    protected $fillable = [
        'norma_tecnica_id',
        'codigo',
        'descricao',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function normaTecnica(): BelongsTo
    {
        return $this->belongsTo(NormaTecnica::class);
    }

    public function rncItems(): BelongsToMany
    {
        return $this->belongsToMany(RncItem::class, 'rnc_item_norma_item', 'norma_item_id', 'rnc_item_id')
            ->withTimestamps();
    }
}
