<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CatalogItem;
use App\Models\NcDocument;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $adminA;

    protected User $adminB;

    protected User $superAdmin;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Cliente A']);
        $this->tenantB = Tenant::create(['name' => 'Cliente B']);

        $this->tenantA->bootstrapItems();
        $this->tenantB->bootstrapItems();

        $this->adminA = User::factory()->create(['role' => 'admin', 'tenant_id' => $this->tenantA->id]);
        $this->adminB = User::factory()->create(['role' => 'admin', 'tenant_id' => $this->tenantB->id]);
        $this->manager = User::factory()->create(['role' => 'manager', 'tenant_id' => $this->tenantA->id]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    }

    protected function catalogIds(int $count): array
    {
        return CatalogItem::query()
            ->where('source', 'cronograma')
            ->where('is_section', false)
            ->orderBy('sort')
            ->limit($count)
            ->pluck('id')
            ->all();
    }

    protected function createDocument(Tenant $tenant, User $user): void
    {
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $this->catalogIds(2)])
            ->assertRedirect();
    }

    public function test_super_admin_sees_audit_of_all_tenants(): void
    {
        $this->createDocument($this->tenantA, $this->adminA);
        $this->createDocument($this->tenantB, $this->adminB);

        $this->actingAs($this->superAdmin)
            ->withSession(['two_step_verified' => true])
            ->get(route('auditoria.index'))
            ->assertOk()
            ->assertSee('Cliente A')
            ->assertSee('Cliente B');
    }

    public function test_admin_sees_only_own_tenant_audit(): void
    {
        $this->createDocument($this->tenantA, $this->adminA);
        $this->createDocument($this->tenantB, $this->adminB);

        $this->actingAs($this->adminA)
            ->withSession(['tenant_id' => $this->tenantA->id, 'two_step_verified' => true])
            ->get(route('auditoria.index'))
            ->assertOk()
            ->assertSee('Cliente A', false)
            ->assertDontSee('Cliente B');
    }

    public function test_manager_and_viewer_cannot_access_audit(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenantA->id, 'two_step_verified' => true])
            ->get(route('auditoria.index'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->withSession(['tenant_id' => $this->tenantA->id, 'two_step_verified' => true])
            ->get(route('auditoria.index'))
            ->assertForbidden();
    }

    public function test_groups_of_users_have_audit_visibility_consistent(): void
    {
        $this->assertTrue($this->adminA->isAdmin());
        $this->assertTrue($this->superAdmin->isAdmin());
        $this->assertFalse($this->manager->isAdmin());
    }

    public function test_document_update_diff_renders(): void
    {
        $this->createDocument($this->tenantA, $this->adminA);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();
        $ids = $this->catalogIds(2);

        $this->actingAs($this->adminA)
            ->withSession(['tenant_id' => $this->tenantA->id, 'two_step_verified' => true])
            ->put(route('nc-documents.update', $document), [
                'title' => 'Título novo',
                'catalog_item_ids' => $ids,
            ])
            ->assertRedirect();

        $this->actingAs($this->adminA)
            ->withSession(['tenant_id' => $this->tenantA->id, 'two_step_verified' => true])
            ->get(route('auditoria.index'))
            ->assertOk()
            ->assertSee('nc_document.updated')
            ->assertSee('detalhes');
    }

    public function test_failed_login_is_audited_without_sensitive_data(): void
    {
        $this->post(route('login.store'), [
            'email' => 'nao.existe@example.com',
            'password' => 'senha-inexistente',
        ])->assertSessionHasErrors('email');

        $audit = AuditLog::withoutGlobalScopes()->where('action', 'auth.login_failed')->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('nao.existe@example.com', $audit->summary);
        $this->assertNull($audit->data_new);
        $this->assertNull($audit->data_old);
    }
}
