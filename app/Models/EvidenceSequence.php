<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Contador global da nomenclatura de arquivos anexados.
 *
 * Uma linha por CATEGORIA (`img`/`doc`), nunca por módulo nem por cliente: o
 * próximo número de uma imagem é o mesmo venha do RNC, do Prontuário ou dos
 * Funcionários. Isso mantém a ordenação por nome equivalente à ordem de
 * upload do sistema inteiro e torna a colisão de nomes impossível entre telas.
 *
 * O incremento é transacional e usa `lockForUpdate()`: dois uploads
 * simultâneos não podem receber o mesmo número.
 *
 * Sobre o teto de 999.999.999: a sequência NÃO trava. Ao ultrapassar, o
 * número simplesmente continua (1000000001, 1000000002, ...) e o `sprintf`
 * com %09d passa a emitir 10 dígitos, o que preserva a ordenação. Os avisos
 * de "restam 1000" são regra de negócio separada, não um limite técnico.
 */
class EvidenceSequence extends Model
{
    /** Categoria de imagem (prefixo `img_` no nome do arquivo). */
    public const IMAGEM = 'img';

    /** Categoria de documento/PDF (prefixo `doc_` no nome do arquivo). */
    public const DOCUMENTO = 'doc';

    /** Categorias válidas. */
    public const CATEGORIAS = [self::IMAGEM, self::DOCUMENTO];

    protected $table = 'evidence_sequences';

    protected $fillable = [
        'categoria',
        'ultimo',
    ];

    protected function casts(): array
    {
        return [
            'ultimo' => 'integer',
        ];
    }

    /**
     * Reserva e devolve o próximo número da categoria. O `lockForUpdate()` só
     * tem efeito dentro de uma transação — por isso o método garante a sua.
     */
    public static function proximo(string $categoria): int
    {
        return (int) DB::transaction(function () use ($categoria) {
            $linha = self::query()
                ->where('categoria', $categoria)
                ->lockForUpdate()
                ->first();

            if (! $linha) {
                $linha = self::create(['categoria' => $categoria, 'ultimo' => 0]);
            }

            $proximo = $linha->ultimo + 1;

            $linha->forceFill(['ultimo' => $proximo])->save();

            return $proximo;
        });
    }
}
