<?php

namespace Tests\Feature;

use App\Enums\RncModelo;
use App\Enums\RncStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Rnc;
use App\Models\RncItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arquivamento de RNC: estado final somente leitura (toda alteração responde
 * 409 em `RncController::authorizeTenant()`), badge/filtro "Arquivada" no
 * índice e reversão via desarquivar.
 */
class RncArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Cliente Arquivo']);
        $this->manager = User::factory()->create([
            'role' => Role::Manager,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_manager_arquiva_e_a_rnc_vira_somente_leitura_na_tela(): void
    {
        $rnc = $this->makeRnc();

        $this->como($this->manager)
            ->from(route('rnc.show', $rnc))
            ->post(route('rnc.arquivar', $rnc))
            ->assertRedirect(route('rnc.show', $rnc));

        $this->assertSame(RncStatus::Arquivado, $rnc->fresh()->status);

        $this->como($this->manager)
            ->get(route('rnc.show', $rnc))
            ->assertOk()
            ->assertSee('Desarquivar')
            ->assertDontSee('Editar cabeçalho')
            ->assertSee('Arquivada');
    }

    public function test_visualizador_nao_pode_arquivar(): void
    {
        $viewer = User::factory()->create([
            'role' => Role::Viewer,
            'tenant_id' => $this->tenant->id,
        ]);
        $rnc = $this->makeRnc();

        $this->actingAs($viewer)
            ->withSession($this->sessionData())
            ->post(route('rnc.arquivar', $rnc))
            ->assertForbidden();
    }

    public function test_rnc_arquivada_bloqueia_toda_alteracao_com_409(): void
    {
        $rnc = $this->makeRnc();
        $item = $this->makeItem($rnc);

        $this->arquivar($rnc);

        $this->como($this->manager)->put(route('rnc.update', $rnc), [])->assertStatus(409);
        $this->como($this->manager)->post(route('rnc.item.store', $rnc), [])->assertStatus(409);
        $this->como($this->manager)->put(route('rnc.item.update', [$rnc, $item]), [])->assertStatus(409);
        $this->como($this->manager)->delete(route('rnc.item.destroy', [$rnc, $item]))->assertStatus(409);
        $this->como($this->manager)->post(route('rnc.publish', $rnc), [])->assertStatus(409);
        $this->como($this->manager)->delete(route('rnc.destroy', $rnc))->assertStatus(409);

        // Nada mudou: segue arquivado e com o mesmo conteúdo.
        $this->assertSame(RncStatus::Arquivado, $rnc->fresh()->status);
        $this->assertSame(1, $rnc->items()->count());
    }

    public function test_desarquivar_de_rascunho_volta_para_rascunho(): void
    {
        $rnc = $this->makeRnc();
        $this->arquivar($rnc);

        $this->como($this->manager)
            ->from(route('rnc.show', $rnc))
            ->post(route('rnc.desarquivar', $rnc))
            ->assertRedirect(route('rnc.show', $rnc));

        $this->assertSame(RncStatus::Rascunho, $rnc->fresh()->status);

        // Libera a edição de volta.
        $this->como($this->manager)
            ->put(route('rnc.update', $rnc), [
                'titulo' => 'Título atualizado',
                'modelo' => RncModelo::Tecnica->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Título atualizado', $rnc->fresh()->titulo);
    }

    public function test_desarquivar_de_publicada_volta_para_publicado(): void
    {
        $rnc = $this->makeRnc();
        $this->makeItem($rnc);

        $this->como($this->manager)
            ->post(route('rnc.publish', $rnc))
            ->assertRedirect();

        $this->arquivar($rnc);

        $this->como($this->manager)
            ->post(route('rnc.desarquivar', $rnc))
            ->assertRedirect();

        $this->assertSame(RncStatus::Publicado, $rnc->fresh()->status);
    }

    public function test_indice_ganha_filtro_e_badge_de_arquivado(): void
    {
        $arquivado = $this->makeRnc();
        $this->arquivar($arquivado);

        $ativo = $this->makeRnc();

        $linkArquivado = route('rnc.show', $arquivado);
        $linkAtivo = route('rnc.show', $ativo);

        $this->como($this->manager)
            ->get(route('rnc.index'))
            ->assertOk()
            ->assertSee($linkArquivado)
            ->assertSee($linkAtivo)
            ->assertSee('Arquivada');

        $this->como($this->manager)
            ->get(route('rnc.index', ['situacao' => 'arquivado']))
            ->assertOk()
            ->assertSee($linkArquivado)
            ->assertDontSee($linkAtivo);

        $this->como($this->manager)
            ->get(route('rnc.index', ['situacao' => 'rascunho']))
            ->assertOk()
            ->assertSee($linkAtivo)
            ->assertDontSee($linkArquivado);
    }

    public function test_arquivar_e_desarquivar_sao_auditados(): void
    {
        $rnc = $this->makeRnc();
        $this->arquivar($rnc);

        $this->como($this->manager)
            ->post(route('rnc.desarquivar', $rnc))
            ->assertRedirect();

        $acoes = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('auditable_id', $rnc->id)
            ->pluck('action');

        $this->assertTrue($acoes->contains('rnc.archive'));
        $this->assertTrue($acoes->contains('rnc.unarchive'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function arquivar(Rnc $rnc): void
    {
        $this->como($this->manager)
            ->post(route('rnc.arquivar', $rnc))
            ->assertRedirect();
    }

    private function como(User $user): static
    {
        return $this->actingAs($user)->withSession($this->sessionData());
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionData(): array
    {
        return ['tenant_id' => $this->tenant->id, 'two_step_verified' => true];
    }

    private function makeRnc(): Rnc
    {
        $number = Rnc::nextNumber($this->tenant->id);

        return Rnc::create([
            'tenant_id' => $this->tenant->id,
            'number' => $number,
            'code' => Rnc::makeCode($number),
            'titulo' => 'Relatório de inspeção',
            'modelo' => RncModelo::Tecnica,
            'responsavel_nome' => 'João da Silva',
            'responsavel_cargo' => 'Engenheiro Eletricista',
            'status' => RncStatus::Rascunho,
            'current_revision' => 0,
        ]);
    }

    private function makeItem(Rnc $rnc): RncItem
    {
        return $rnc->items()->create([
            'tenant_id' => $rnc->tenant_id,
            'numero' => $rnc->nextItemNumber(),
            'titulo' => 'Condição encontrada em campo',
            'descricao' => 'Condição encontrada em campo.',
            'prazo_adequacao' => now()->addWeek(),
        ]);
    }
}
