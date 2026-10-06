<?php

namespace Tests\Feature;

use App\Enums\RncModelo;
use App\Enums\RncStatus;
use App\Enums\Role;
use App\Models\Bairro;
use App\Models\Cidade;
use App\Models\Rnc;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cadastro de cliente com endereço estruturado (CEP, número, complemento,
 * bairro, cidade e UF) e as bases de cidades/bairros que alimentam o form.
 */
class TenantEnderecoTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulario_de_cliente_mostra_os_campos_de_endereco(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->get(route('tenants.create'))
            ->assertOk()
            ->assertSee('id="cep"', false)
            ->assertSee('id="btn-buscar-cep"', false)
            ->assertSee('list="cidades-lista"', false)
            ->assertSee('list="bairros-lista"', false)
            ->assertSee('data-cep-url');
    }

    public function test_cria_cliente_com_endereco_estruturado_e_normaliza_cep_e_uf(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->post(route('tenants.store'), [
                'name' => 'Cliente com Endereço',
                'contact_email' => 'admin@endereco.local',
                'address' => '  Rua das Flores  ',
                'cep' => '13070000',
                'numero' => '123',
                'complemento' => 'Sala 4',
                'bairro' => 'Jardim Guanabara',
                'cidade' => 'Campinas',
                'uf' => 'sp',
            ])
            ->assertSessionHasNoErrors();

        $tenant = Tenant::query()->firstOrFail();

        $this->assertSame('Rua das Flores', $tenant->address);
        $this->assertSame('13070-000', $tenant->cep);
        $this->assertSame('SP', $tenant->uf);
        $this->assertSame(
            'Rua das Flores, 123, Sala 4, Jardim Guanabara, Campinas — SP, CEP 13070-000',
            $tenant->enderecoCompleto(),
        );
    }

    public function test_atualiza_o_endereco_do_cliente(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin]);
        $tenant = Tenant::create([
            'name' => 'Cliente Antigo',
            'address' => 'Rua XV de Novembro, 45, Centro, Curitiba — PR, CEP 80020-310',
        ]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->put(route('tenants.update', $tenant), [
                'name' => 'Cliente Antigo',
                'address' => 'Rua XV de Novembro',
                'numero' => '45',
                'bairro' => 'Centro',
                'cidade' => 'Curitiba',
                'uf' => 'pr',
                'cep' => '80020-310',
            ])
            ->assertSessionHasNoErrors();

        $tenant->refresh();

        $this->assertSame('Rua XV de Novembro', $tenant->address);
        $this->assertSame('PR', $tenant->uf);
        $this->assertSame('Rua XV de Novembro, 45, Centro, Curitiba — PR, CEP 80020-310', $tenant->enderecoCompleto());
    }

    public function test_rejeita_cep_e_uf_invalidos(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->post(route('tenants.store'), [
                'name' => 'Cliente Inválido',
                'contact_email' => 'admin@invalido.local',
                'cep' => '1234',
                'uf' => 'XX',
            ])
            ->assertSessionHasErrors(['cep', 'uf']);
    }

    public function test_endereco_completo_usa_o_logradouro_legado_sem_repetir_o_numero(): void
    {
        // Cadastro antigo: endereço inteiro em `address`, número junto.
        $legado = new Tenant([
            'address' => 'Rua das Flores, 123, Centro, Campinas — SP, CEP 13070-000',
            'numero' => '123',
        ]);

        $this->assertSame('Rua das Flores, 123, Centro, Campinas — SP, CEP 13070-000', $legado->enderecoCompleto());
        $this->assertNull((new Tenant(['name' => 'Sem endereço']))->enderecoCompleto());

        // Cadastro novo: logradouro e número separados.
        $novo = new Tenant([
            'address' => 'Rua das Flores',
            'numero' => '123',
            'complemento' => 'Sala 4',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
            'cep' => '13070-000',
        ]);

        $this->assertSame(
            'Rua das Flores, 123, Sala 4, Centro, Campinas — SP, CEP 13070-000',
            $novo->enderecoCompleto(),
        );
    }

    public function test_snapshot_da_rnc_usa_o_endereco_completo_do_cliente(): void
    {
        $tenant = Tenant::create([
            'name' => 'Cliente do Snapshot',
            'address' => 'Rua das Flores',
            'numero' => '123',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
            'uf' => 'SP',
            'cep' => '13070-000',
        ]);

        $number = Rnc::nextNumber($tenant->id);
        $rnc = Rnc::create([
            'tenant_id' => $tenant->id,
            'number' => $number,
            'code' => Rnc::makeCode($number),
            'titulo' => 'Inspeção das instalações',
            'descricao' => 'Inspeção mensal.',
            'modelo' => RncModelo::Tecnica,
            'responsavel_nome' => 'João da Silva',
            'responsavel_cargo' => 'Engenheiro Eletricista',
            'status' => RncStatus::Rascunho,
            'current_revision' => 0,
        ]);

        $this->assertSame(
            'Rua das Flores, 123, Centro, Campinas — SP, CEP 13070-000',
            $rnc->buildSnapshot()['cliente_endereco'],
        );
    }

    public function test_super_admin_busca_cep_e_alimenta_a_base_de_bairros(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'cep' => '13070000',
                'logradouro' => 'AVENIDA ANDRADE NEVES',
                'bairro' => 'JARDIM GUANABARA',
                'localidade' => 'CAMPINAS',
                'uf' => 'sp',
            ]),
        ]);

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cep-lookup', '13070000'))
            ->assertOk()
            ->assertJson([
                'cep' => '13070-000',
                'address' => 'Avenida Andrade Neves',
                'bairro' => 'Jardim Guanabara',
                'cidade' => 'Campinas',
                'uf' => 'SP',
            ]);

        $this->assertTrue(Cidade::query()->where(['uf' => 'SP', 'nome' => 'Campinas'])->exists());
        $this->assertTrue(Bairro::query()->where([
            'uf' => 'SP',
            'cidade' => 'Campinas',
            'nome' => 'Jardim Guanabara',
        ])->exists());
    }

    public function test_busca_de_cep_responde_404_quando_o_cep_nao_existe(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true]),
        ]);

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cep-lookup', '99999999'))
            ->assertNotFound();
    }

    public function test_busca_de_cep_responde_503_quando_a_api_falha(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response('', 500),
        ]);

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cep-lookup', '13070000'))
            ->assertStatus(503);
    }

    public function test_nao_super_admin_nao_consulta_cep(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($manager)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cep-lookup', '13070000'))
            ->assertForbidden();
    }

    public function test_lista_somente_os_bairros_da_cidade_selecionada(): void
    {
        Bairro::registrar('SP', 'Campinas', 'Centro');
        Bairro::registrar('SP', 'Campinas', 'Cambuí');
        Bairro::registrar('SP', 'São Paulo', 'Centro');
        Bairro::registrar('RJ', 'Rio de Janeiro', 'Centro');

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.bairros', ['uf' => 'SP', 'cidade' => 'Campinas']))
            ->assertOk()
            ->assertExactJson(['Cambuí', 'Centro']);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.bairros', ['uf' => 'RJ', 'cidade' => 'Rio de Janeiro']))
            ->assertExactJson(['Centro']);
    }

    public function test_lista_somente_as_cidades_da_uf_selecionada(): void
    {
        Cidade::registrar('SP', 'Campinas');
        Cidade::registrar('SP', 'Santos');
        Cidade::registrar('AC', 'Rio Branco');

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cidades', ['uf' => 'SP']))
            ->assertOk()
            ->assertExactJson(['Campinas', 'Santos']);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cidades', ['uf' => 'AC']))
            ->assertExactJson(['Rio Branco']);
    }

    public function test_nao_super_admin_nao_lista_cidades_nem_bairros(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($manager)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cidades', ['uf' => 'SP']))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.bairros', ['uf' => 'SP', 'cidade' => 'Campinas']))
            ->assertForbidden();
    }
}
