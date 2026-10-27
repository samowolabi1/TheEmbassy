<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // What another church's deployment would set in .env.
        config([
            'church.name' => 'RCCG Grace Chapel',
            'church.short_name' => 'Grace Chapel',
            'church.brand.wordmark' => 'GRACE CHAPEL',
            'church.brand.wordmark_sub' => 'LEKKI',
            'church.brand.logo' => 'images/grace.png',
            'church.brand.market_name' => 'Grace Market',
            'church.brand.app_name' => 'The Grace app',
            'church.brand.colors.navy' => '#123456',
        ]);
    }

    public function test_public_site_uses_the_configured_church(): void
    {
        $this->get('/')
            ->assertSee('GRACE CHAPEL')
            ->assertSee('LEKKI')
            ->assertSee('images/grace.png')
            ->assertSee('GRACE MARKET')
            ->assertSee('--navy: #123456;', false)
            ->assertDontSee('THE ROYAL TRIBE');
    }

    public function test_admin_uses_the_configured_church(): void
    {
        $this->get('/admin/login')->assertSee('GRACE CHAPEL');
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee('GRACE CHAPEL');
    }

    public function test_invalid_colours_are_ignored(): void
    {
        config(['church.brand.colors.navy' => 'red; } body { display: none']);

        $this->get('/')->assertDontSee('display: none', false);
    }
}
