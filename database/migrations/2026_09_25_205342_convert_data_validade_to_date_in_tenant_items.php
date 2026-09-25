<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Converte "Data de validade" (Prontuário) de texto livre para data com calendário.
     *
     * Valores que não são datas reais (ex.: "1 ano", "Conforme Revisão", que vinham
     * da planilha original) não têm representação em uma coluna DATE e passam a NULL.
     * Isso só afeta linhas antigas que usavam esses textos; o preenchimento pelo
     * formulário passa a exigir uma data válida.
     */
    public function up(): void
    {
        $valid = [];

        foreach (DB::table('tenant_items')->select('id', 'data_validade')->whereNotNull('data_validade')->cursor() as $row) {
            $date = $this->toDate((string) $row->data_validade);
            $valid[$row->id] = $date;
        }

        foreach ($valid as $id => $date) {
            DB::table('tenant_items')->where('id', $id)->update(['data_validade' => $date]);
        }

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->date('data_validade')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->string('data_validade', 40)->nullable()->change();
        });
    }

    protected function toDate(string $value): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'Y/m/d'] as $format) {
            $parsed = DateTime::createFromFormat($format, $value);
            if ($parsed !== false) {
                $errors = DateTime::getLastErrors();

                if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $parsed->format('Y-m-d');
                }
            }
        }

        return null;
    }
};
