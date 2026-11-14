<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ChurchLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_directions_and_the_map_come_from_the_address(): void
    {
        $address = rawurlencode('11 Lawrence Omagbemi Street, Powerline, Ejigbo, Lagos');
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination='.$address, ChurchLocation::directionsUrl());

        $this->get(route('about'))->assertOk()
            ->assertSee('https://www.google.com/maps?q='.$address.'&amp;output=embed', false)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination='.$address, false)
            ->assertSee('Open in Google Maps');
        $this->get(route('contact'))->assertOk()->assertSee('Get directions');
        $this->get(route('home'))->assertOk()->assertSee('https://www.google.com/maps/search/?api=1&amp;query='.$address, false);
    }

    public function test_admin_changes_the_address_and_can_pin_the_exact_spot(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->put(route('admin.settings.update'), ['church__address' => '5 New Road, Ikeja, Lagos'])->assertSessionHasNoErrors();
        Setting::applyToConfig(); // as on the next request
        $this->get(route('about'))->assertSee('5 New Road, Ikeja, Lagos')->assertSee(rawurlencode('5 New Road, Ikeja, Lagos'), false);

        // A shared Google Maps link and an embed link override the ones made from the address.
        $this->put(route('admin.settings.update'), [
            'church__address' => '5 New Road, Ikeja, Lagos',
            'church__directions_url' => 'https://maps.app.goo.gl/abc123',
            'church__map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!demo',
        ])->assertSessionHasNoErrors();
        Setting::applyToConfig();
        $this->get(route('about'))->assertSee('https://maps.app.goo.gl/abc123', false)->assertSee('https://www.google.com/maps/embed?pb=!1m18!demo', false);
    }

    public function test_no_map_or_directions_while_the_address_is_a_placeholder(): void
    {
        config(['church.address' => '[Venue address]']);
        $this->assertNull(ChurchLocation::directionsUrl());
        $this->get(route('about'))->assertOk()->assertDontSee('Get directions')->assertSee('[Map]');
    }
}
