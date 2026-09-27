<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceDeletePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_delete_evidence(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente Teste']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $evidence = $this->makeEvidence($tenant, $user);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('evidencia.destroy-prontuario', $evidence))
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
    }

    public function test_viewer_cannot_delete_evidence(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente Teste']);
        $user = User::factory()->create(['role' => Role::Viewer, 'tenant_id' => $tenant->id]);

        $evidence = $this->makeEvidence($tenant, User::factory()->create());

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('evidencia.destroy-prontuario', $evidence))
            ->assertForbidden();

        $this->assertDatabaseHas('evidences', ['id' => $evidence->id]);
    }

    public function test_super_admin_can_delete_evidence(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente Teste']);
        $user = User::factory()->create(['role' => Role::SuperAdmin, 'tenant_id' => null]);

        $evidence = $this->makeEvidence($tenant, User::factory()->create());

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('cronograma.evidencia.destroy', [$evidence->tenant_item_id, $evidence]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
    }

    public function test_cannot_delete_evidence_from_another_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Cliente A']);
        $tenantB = Tenant::create(['name' => 'Cliente B']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenantA->id]);

        $evidence = $this->makeEvidence($tenantB, User::factory()->create());

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantA->id, 'two_step_verified' => true])
            ->delete(route('cronograma.evidencia.destroy', [$evidence->tenant_item_id, $evidence]))
            ->assertNotFound();

        $this->assertDatabaseHas('evidences', ['id' => $evidence->id]);
    }

    protected function makeEvidence(Tenant $tenant, User $uploader): Evidence
    {
        $catalogItem = CatalogItem::create([
            'source' => Source::Prontuario->value,
            'code' => '1.1',
            'n1' => 1,
            'n2' => 1,
            'description' => 'Item de teste',
        ]);

        $tenantItem = TenantItem::create([
            'tenant_id' => $tenant->id,
            'catalog_item_id' => $catalogItem->id,
        ]);

        Storage::fake('local');

        return Evidence::create([
            'tenant_id' => $tenant->id,
            'tenant_item_id' => $tenantItem->id,
            'uploaded_by' => $uploader->id,
            'original_name' => 'foto.jpg',
            'stored_path' => 'evidences/tenant-'.$tenant->id.'/foto.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);
    }
}
