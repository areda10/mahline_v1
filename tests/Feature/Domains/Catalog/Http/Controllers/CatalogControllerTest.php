<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Catalog;
use App\Domains\Identity\Authentication\Models\AuthenticationSession;
use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    private Cooperative $cooperative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cooperative = Cooperative::factory()
            ->active()
            ->create();

        $user = User::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
        ]);

        // Passer par le véritable workflow pour que le client HTTP de test
        // conserve le cookie de session régénéré et la clé MAHLINE.
        $this->post(route('authentication.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/');

        // Diagnostic : vérifier l'état réellement conservé par le client HTTP.
        $this->assertAuthenticatedAs($user);
        $authenticationSessionId = session()->get('authentication_session_id');
        $this->assertIsString($authenticationSessionId);

        $authenticationSession = AuthenticationSession::query()
            ->find($authenticationSessionId);

        $this->assertNotNull($authenticationSession);
        $this->assertSame(
            $user->getKey(),
            $authenticationSession->user_id,
        );
        $this->assertSame(
            session()->getId(),
            $authenticationSession->session_id,
        );
        $this->assertNull($authenticationSession->revoked_at);

        // Ces tests isolent le comportement du contrôleur.
        // Les règles d'autorisation doivent être testées séparément.
        Gate::before(static fn (): bool => true);
    }

    public function test_catalog_index_returns_successful_response(): void
    {
        $response = $this->get(route(
            'backoffice.catalogs.index',
            ['cooperative' => $this->cooperative->getKey()],
        ));

        $response->assertOk()
            ->assertViewIs('backoffice.catalogs.index');
    }

    public function test_catalog_can_be_created(): void
    {
        $response = $this->post(route(
            'backoffice.catalogs.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'name' => 'Catalogue Bien-être',
            'description' => 'Produits de bien-être',
            'is_active' => true,
        ]);

        $response->assertRedirectToRoute(
            'backoffice.catalogs.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertDatabaseHas('catalogs', [
            'cooperative_id' => $this->cooperative->getKey(),
            'name' => 'Catalogue Bien-être',
            'slug' => 'catalogue-bien-etre',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }

    public function test_catalog_creation_requires_a_name(): void
    {
        $response = $this->from(route(
            'backoffice.catalogs.create',
            ['cooperative' => $this->cooperative->getKey()],
        ))->post(route(
            'backoffice.catalogs.store',
            ['cooperative' => $this->cooperative->getKey()],
        ), [
            'description' => 'Sans nom',
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('catalogs', [
            'cooperative_id' => $this->cooperative->getKey(),
            'description' => 'Sans nom',
        ]);
    }

    public function test_catalog_can_be_deleted_without_deleting_its_record_physically(): void
    {
        $catalog = Catalog::factory()->create([
            'cooperative_id' => $this->cooperative->getKey(),
        ]);

        $response = $this->delete(route(
            'backoffice.catalogs.destroy',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $catalog->getKey(),
            ],
        ));

        $response->assertRedirectToRoute(
            'backoffice.catalogs.index',
            ['cooperative' => $this->cooperative->getKey()],
        );

        $this->assertSoftDeleted('catalogs', [
            'id' => $catalog->getKey(),
        ]);
    }

    public function test_catalog_from_another_cooperative_is_not_found(): void
    {
        $otherCooperative = Cooperative::factory()
            ->active()
            ->create();

        $foreignCatalog = Catalog::factory()->create([
            'cooperative_id' => $otherCooperative->getKey(),
        ]);

        $response = $this->get(route(
            'backoffice.catalogs.show',
            [
                'cooperative' => $this->cooperative->getKey(),
                'catalog' => $foreignCatalog->getKey(),
            ],
        ));

        $response->assertNotFound();
    }
}