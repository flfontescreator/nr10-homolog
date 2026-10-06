<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Desliga os subitens 4.x do Prontuário: o módulo Funcionário passa a usar
     * a tabela funcionario_items. O item 4 é recriado vazio, então as linhas
     * antigas e suas evidências são removidas.
     */
    public function up(): void
    {
        $funcionarioItemIds = fn () => DB::table('tenant_items')
            ->whereNotNull('funcionario_id')
            ->pluck('id');

        // 1. Evidências ancoradas nos itens 4.x saem do disco antes das linhas,
        //    para não deixar arquivos órfãos.
        $paths = DB::table('evidences')
            ->whereIn('tenant_item_id', $funcionarioItemIds())
            ->pluck('stored_path')
            ->all();

        // 2. Vínculos de biblioteca dessas evidências.
        DB::table('evidence_document_item')
            ->whereIn('evidence_id', fn ($q) => $q
                ->select('id')->from('evidences')
                ->whereIn('tenant_item_id', $funcionarioItemIds()))
            ->delete();

        DB::table('evidence_tenant_item')
            ->whereIn('tenant_item_id', $funcionarioItemIds())
            ->delete();

        DB::table('evidence_document')
            ->whereIn('evidence_id', fn ($q) => $q
                ->select('id')->from('evidences')
                ->whereIn('tenant_item_id', $funcionarioItemIds()))
            ->delete();

        // 3. Itens de RNC que apontavam para as linhas 4.x do Prontuário.
        DB::table('nc_document_items')
            ->whereIn('tenant_item_id', $funcionarioItemIds())
            ->delete();

        // 4. Evidências ancoradas nos itens 4.x.
        DB::table('evidences')
            ->whereIn('tenant_item_id', $funcionarioItemIds())
            ->delete();

        // 5. As linhas tenant_items do item 4.
        DB::table('tenant_items')->whereNotNull('funcionario_id')->delete();

        // 6. O item 4.x some do catálogo do Prontuário.
        DB::table('catalog_items')
            ->where('source', 'prontuario')
            ->where('n1', 4)
            ->delete();

        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    public function down(): void
    {
        // Catálogo e linhas do item 4 são recriados a partir do catálogo
        // operacional; não há reversão de dados.
    }
};
