<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

#[Signature('deploy:db-backup {--dir= : Diretório onde salvar o dump}')]
#[Description('Cria um dump lógico (SQL) do banco antes de aplicar migrações no deploy')]
class DeployDbBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dir = $this->option('dir') ?: storage_path('app/backups');

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Não foi possível criar o diretório de backup: '.$dir);

            return self::FAILURE;
        }

        $file = rtrim($dir, '/\\').'/db-'.date('Ymd-His').'.sql';

        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET NAMES utf8mb4');

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

        $sql = '-- deploy:db-backup '.date('c').PHP_EOL.'SET NAMES utf8mb4;'.PHP_EOL.'SET FOREIGN_KEY_CHECKS=0;'.PHP_EOL;

        foreach ($tables as [$table]) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);

            $sql .= PHP_EOL."DROP TABLE IF EXISTS `$table`;".PHP_EOL.$create['Create Table'].';'.PHP_EOL;

            $stmt = $pdo->query("SELECT * FROM `$table`");

            $rows = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $rows[] = $row;

                if (count($rows) >= 300) {
                    $sql .= $this->insertRows($pdo, $table, $rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                $sql .= $this->insertRows($pdo, $table, $rows);
            }
        }

        $sql .= PHP_EOL.'SET FOREIGN_KEY_CHECKS=1;'.PHP_EOL;

        if (file_put_contents($file, $sql) === false) {
            $this->error('Falha ao escrever o arquivo de backup: '.$file);

            return self::FAILURE;
        }

        $this->info('Backup criado: '.$file.' ('.round(filesize($file) / 1024, 1).' KB)');

        return self::SUCCESS;
    }

    private function insertRows(PDO $pdo, string $table, array $rows): string
    {
        $sql = "INSERT INTO `$table` VALUES ";
        $first = true;

        foreach ($rows as $row) {
            $sql .= $first ? '' : ',';
            $sql .= '('.implode(',', array_map(
                fn ($value) => is_null($value) ? 'NULL' : $pdo->quote((string) $value),
                $row,
            )).')';
            $first = false;
        }

        return $sql.';'.PHP_EOL;
    }
}
