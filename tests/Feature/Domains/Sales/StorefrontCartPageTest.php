<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StorefrontCartPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Sec-Fetch-Site', 'same-origin');
    }

    public function test_guest_can_open_the_public_cart_page_without_creating_an_account(): void
    {
        $this->get(route('sales.cart.page'))
            ->assertOk()
            ->assertViewIs('storefront.cart')
            ->assertSee('Mon panier')
            ->assertSee('Votre panier est vide')
            ->assertSee('Continuer mes achats');
    }

    public function test_guest_cart_endpoint_creates_a_guest_cart_and_returns_a_versioned_snapshot(): void
    {
        $response = $this->getJson(route('sales.cart.show'));

        $response->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseCount('sales_carts', 1);
        $this->assertNotEmpty($response->headers->getCookies());
    }
}
