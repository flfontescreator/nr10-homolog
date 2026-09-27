<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('deploy:db-backup {--dir= : Diretório onde salvar o dump}')]
#[Description('Cria um dump de segurança do banco antes de aplicar migrações no deploy')]
class DeployDbBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));

        $dir = $this->option('dir') ?: storage_path('app/backups');

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Não foi possível criar o diretório de backup: '.$dir);

            return self::FAILURE;
        }

        $file = rtrim($dir, '/\\').'/db-'.date('Ymd-His').'.sql';

        $process = new Process([
            'mysqldump',
            '--single-transaction',
            '--no-tablespaces',
            '--skip-lock-tables',
            '--host='.$connection['host'],
            '--user='.$connection['username'],
            '--password='.$connection['password'],
            '--result-file='.$file,
            $connection['database'],
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            $this->error('Backup falhou:'.PHP_EOL.$process->getErrorOutput());

            return self::FAILURE;
        }

        $this->info('Backup criado: '.$file.' ('.round(filesize($file) / 1024, 1).' KB)');

        return self::SUCCESS;
    }
}
