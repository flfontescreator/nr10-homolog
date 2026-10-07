<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Criticidade;
use App\Models\Projeto;
use App\Models\Rnc;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Administração das categorias globais do RNC (projetos, criticidades e
 * classificações de risco). Restrita a Admin/SuperAdmin.
 */
class RncCategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_nao_acessa_as_categorias(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.categoria.index'))
            ->assertForbidden();
    }

    public function test_admin_ve_a_pagina_e_cria_projeto(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.categoria.index'))
            ->assertOk()
            ->assertSee('Categorias do RNC');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.categoria.projeto.store'), ['nome' => 'Projeto Alpha', 'ativo' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('projetos', ['nome' => 'Projeto Alpha', 'ativo' => true]);
    }

    public function test_admin_cria_criticidade_e_classificacao_de_risco(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.categoria.criticidade.store'), ['nome' => 'Urgente', 'ordem' => 5])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.categoria.classificacao.store'), ['nome' => 'Crítico', 'ordem' => 4])
            ->assertRedirect();

        $this->assertDatabaseHas('criticidades', ['nome' => 'Urgente', 'ordem' => 5]);
        $this->assertDatabaseHas('classificacoes_risco', ['nome' => 'Crítico', 'ordem' => 4]);
    }

    public function test_nao_exclui_criticidade_em_uso(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
        $criticidade = Criticidade::create(['nome' => 'Alta', 'ordem' => 1]);

        $rnc = Rnc::create([
            'tenant_id' => $tenant->id,
            'number' => 1,
            'code' => 'RNC_0001',
            'titulo' => 'Relatório',
        ]);

        $rnc->items()->create([
            'tenant_id' => $tenant->id,
            'numero' => 1,
            'titulo' => 'NC',
            'criticidade_id' => $criticidade->id,
        ]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->from(route('rnc.categoria.index'))
            ->delete(route('rnc.categoria.criticidade.destroy', $criticidade))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('criticidades', ['id' => $criticidade->id]);
    }

    public function test_atualiza_projeto(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
        $projeto = Projeto::create(['nome' => 'Antigo', 'ativo' => true]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->put(route('rnc.categoria.projeto.update', $projeto), ['nome' => 'Novo'])
            ->assertRedirect();

        $this->assertDatabaseHas('projetos', ['id' => $projeto->id, 'nome' => 'Novo', 'ativo' => false]);
    }

    public function test_nao_exclui_projeto_com_rnc_vinculado(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
        $projeto = Projeto::create(['nome' => 'Com RNC', 'ativo' => true]);

        Rnc::create([
            'tenant_id' => $tenant->id,
            'number' => 1,
            'code' => 'RNC_0001',
            'titulo' => 'Relatório',
            'projeto_id' => $projeto->id,
        ]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->from(route('rnc.categoria.index'))
            ->delete(route('rnc.categoria.projeto.destroy', $projeto))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('projetos', ['id' => $projeto->id]);
    }

    protected function makeTenant(): Tenant
    {
        return Tenant::create(['name' => 'Cliente RNC Cat '.Str::random(5)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionData(Tenant $tenant): array
    {
        return ['tenant_id' => $tenant->id, 'two_step_verified' => true];
    }
}
