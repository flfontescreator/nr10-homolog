<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:sync-normativa')]
#[Description('Importa o Catálogo Normativo (Cronograma de Adequação + Matriz NR-10 2026) dos CSVs de storage/app/imports, sem tocar no catálogo operacional')]
class SyncNormativaCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        (new CatalogSeeder)->syncNormativa();

        $this->info('Catálogo normativo (cronograma + matriz NR-10 2026) sincronizado com sucesso.');

        return self::SUCCESS;
    }
}
