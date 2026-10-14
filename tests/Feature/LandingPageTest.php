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

    public function test_the_landing_page_shows_catalog_highlights_and_both_apk_links(): void
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
                ->has('downloads', 2)
                ->where('downloads.0.name', 'Anteraja Kurir')
                ->where('downloads.0.app_id', 'com.anteraja.kurir')
                ->where('downloads.1.name', 'Anteraja Frozen')
                ->where('downloads.1.app_id', 'com.anteraja.frozen')
                ->has('downloads.0.apk_url')
                ->has('downloads.0.version')
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
