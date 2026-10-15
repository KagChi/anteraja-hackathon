<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The marketing landing page and the blog it links to.
 */
final class LandingPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_landing_page_shows_catalog_highlights_and_both_app_links(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Landing')
                ->has('products', 4)
                ->where('products.0.slug', 'nugget-ayam-1kg')
                ->where('products.0.name', 'Nugget Ayam 1 kg')
                ->where('products.0.price_label', 'Rp 48.000')
                ->where('products.0.weight_label', '1 kg')
                ->where('products.0.image_url', '/images/catalog/nugget-ayam.svg')
                ->has('apps', 2)
                ->where('apps.0.key', 'client')
                ->where('apps.0.name', 'Anteraja Frozen')
                ->where('apps.0.url', '/client')
                ->where('apps.1.key', 'courier')
                ->where('apps.1.name', 'Anteraja Kurir')
                ->where('apps.1.url', '/courier')
        );
    }

    public function test_the_blog_index_and_an_article_are_reachable(): void
    {
        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Blog/Index'));

        $this->get('/blog/cara-menjaga-suhu-frozen-selama-pengiriman')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Blog/Show')
                ->where('slug', 'cara-menjaga-suhu-frozen-selama-pengiriman')
            );
    }

    public function test_the_landing_page_still_routes_staff_to_the_admin_login(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }
}
