<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O catálogo operacional passa a ser apenas uma base/referência: excluir um
     * item dele não pode derrubar os documentos de não conformidades nem as
     * linhas de trabalho dos clientes. Para isso, tenant_items e
     * nc_document_items passam a guardar cópia própria (code, title, source) e
     * a FK catalog_item_id vira SET NULL (em vez de cascade).
     */
    public function up(): void
    {
        foreach (['tenant_items', 'nc_document_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('code', 30)->nullable()->after('catalog_item_id');
                $blueprint->string('title')->nullable()->after('code');
                $blueprint->string('source', 20)->nullable()->after('title');
            });

            DB::table($table)
                ->whereNotNull('catalog_item_id')
                ->select('id', 'catalog_item_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table) {
                    $catalog = DB::table('catalog_items')
                        ->whereIn('id', $rows->pluck('catalog_item_id')->all())
                        ->get()
                        ->keyBy('id');

                    foreach ($rows as $row) {
                        $cat = $catalog->get($row->catalog_item_id);

                        if (! $cat) {
                            continue;
                        }

                        DB::table($table)->where('id', $row->id)->update([
                            'code' => $cat->code,
                            'title' => $cat->title,
                            'source' => $cat->source,
                        ]);
                    }
                });
        }

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
            $table->unsignedBigInteger('catalog_item_id')->nullable()->change();
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->foreign('catalog_item_id')->references('id')->on('catalog_items')->onDelete('set null');
        });

        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
            $table->unsignedBigInteger('catalog_item_id')->nullable()->change();
        });

        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->foreign('catalog_item_id')->references('id')->on('catalog_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
        });

        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->foreign('catalog_item_id')->references('id')->on('catalog_items')->onDelete('cascade');
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->foreign('catalog_item_id')->references('id')->on('catalog_items')->onDelete('cascade');
        });

        foreach (['nc_document_items', 'tenant_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['code', 'title', 'source']);
            });
        }
    }
};
