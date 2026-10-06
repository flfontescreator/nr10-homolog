<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Funcionario;
use App\Models\FuncionarioItem;
use App\Models\Situacao;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogSeeder::class);
        $this->call(RncCatalogSeeder::class);
        $this->call(LocalidadeSeeder::class);

        $this->seedSuperAdmins();
        $this->seedDemoTenant();
    }

    protected function seedSuperAdmins(): void
    {
        $admins = [
            [
                'name' => env('SEED_ADMIN_NAME', 'Flávio (Administrador)'),
                'email' => env('SEED_ADMIN_EMAIL', 'gamesfhs@gmail.com'),
                'password' => env('SEED_ADMIN_PASSWORD', 'Dev@2026Nr10'),
            ],
            [
                'name' => env('SEED_BRUNO_NAME', 'Bruno (Gestor)'),
                'email' => env('SEED_BRUNO_EMAIL', 'bruno@greenjob.local'),
                'password' => env('SEED_BRUNO_PASSWORD', 'Greenjob@2026'),
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => $admin['password'], // hashed pelo cast
                    'role' => Role::SuperAdmin->value,
                    'tenant_id' => null,
                ]
            );
        }
    }

    /**
     * Cliente piloto, já nasce com a árvore completa de itens/subitens.
     * Os campos de controle do prontuário recebem os valores originais das
     * planilhas apenas para demonstração (clientes reais nascem vazios).
     */
    protected function seedDemoTenant(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['name' => 'Greenjob — Piloto'],
            [
                'contact_name' => 'Bruno',
                'is_active' => true,
            ]
        );

        $tenant->bootstrapItems();

        // Usuários do cliente piloto.
        $demoUsers = [
            ['name' => 'Admin Demo', 'email' => 'admin@demo.greenjob.local', 'password' => 'Admin@2026', 'role' => Role::Admin],
            ['name' => 'Gerente Demo', 'email' => 'gerente@demo.greenjob.local', 'password' => 'Gerente@2026', 'role' => Role::Manager],
            ['name' => 'Visitante Demo', 'email' => 'visitante@demo.greenjob.local', 'password' => 'Visitante@2026', 'role' => Role::Viewer],
        ];

        foreach ($demoUsers as $demo) {
            User::updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => $demo['password'],
                    'role' => $demo['role']->value,
                    'tenant_id' => $tenant->id,
                ]
            );
        }

        $this->seedDemoProntuarioValues($tenant);
        $this->seedDemoFuncionarios($tenant);
    }

    protected function seedDemoFuncionarios(Tenant $tenant): void
    {
        $funcionarios = [
            ['nome' => 'João da Silva', 'matricula' => 'F001'],
            ['nome' => 'Maria Souza', 'matricula' => 'F002'],
            ['nome' => 'Carlos Pereira', 'matricula' => 'F003'],
        ];

        $itens = [
            'Atestado médico de aptitude física',
            'Certificado de conclusão do treinamento NR-10',
            'Documento de identificação com foto',
        ];

        foreach ($funcionarios as $f) {
            $funcionario = Funcionario::updateOrCreate(
                ['tenant_id' => $tenant->id, 'matricula' => $f['matricula']],
                ['nome' => $f['nome']]
            );

            // Os itens de documentação são criados sob demanda no módulo
            // Funcionário; o seeding apenas deixa um exemplo navegável.
            foreach ($itens as $titulo) {
                FuncionarioItem::firstOrCreate([
                    'funcionario_id' => $funcionario->id,
                    'titulo' => $titulo,
                ], [
                    'tenant_id' => $tenant->id,
                    'numero' => $funcionario->nextItemNumber(),
                    'situacao_id' => Situacao::default()?->id,
                ]);
            }
        }
    }

    protected function seedDemoProntuarioValues(Tenant $tenant): void
    {
        $rows = CatalogSeeder::csvRows(storage_path('app/imports/prontuario.csv'));

        $catalog = CatalogItem::where('source', Source::Prontuario->value)->get()->keyBy('code');

        foreach ($rows as $row) {
            $code = trim((string) ($row[0] ?? ''));

            if (! preg_match('/^[\d]+(?:\.[\d]+)*$/', $code) || ! $catalog->has($code)) {
                continue;
            }

            $catalogItem = $catalog->get($code);

            if ($catalogItem->is_section || $catalogItem->n1 === 4) {
                continue;
            }

            $tenantItem = TenantItem::firstOrCreate(
                ['tenant_id' => $tenant->id, 'catalog_item_id' => $catalogItem->id]
            );

            $percentualRaw = str_replace(',', '.', trim((string) ($row[5] ?? '')));

            $tenantItem->fill([
                'evidencias_status' => $this->normalizeEvidenciaStatus((string) ($row[2] ?? '')),
                'data_realizacao' => $this->toDate((string) ($row[3] ?? '')),
                'data_validade' => $this->toDate((string) ($row[4] ?? '')),
                'percentual' => is_numeric($percentualRaw) ? ((float) $percentualRaw) * 100 : null,
                'comentarios' => $this->clean((string) ($row[6] ?? '')),
            ]);

            if ($tenantItem->isDirty()) {
                $tenantItem->save();
            }
        }
    }

    protected function normalizeEvidenciaStatus(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'digital' => 'Digital',
            'pendente' => 'Pendente',
            'nao aplicado', 'não aplicado' => 'Nao Aplicado',
            default => null,
        };
    }

    protected function toDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $value) ?: \DateTime::createFromFormat('Y-m-d', $value);

        // A planilha tem células de status na coluna de data ("Conforme
        // Revisão"): não é data, então fica nula em vez de quebrar o seed.
        return $parsed instanceof \DateTimeInterface ? $parsed->format('Y-m-d') : null;
    }

    protected function clean(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }
}
