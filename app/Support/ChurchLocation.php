<?php

namespace App\Support;

/**
 * Where the church meets, and the Google Maps links for it. Everything comes from the address set
 * in admin › Settings & roles; a custom directions or map link there, if given, is used instead.
 * No Google API key is needed for any of these.
 */
class ChurchLocation
{
    /**
     * The address, or null while it's still the template's placeholder.
     */
    public static function address(): ?string
    {
        $address = trim((string) config('church.address'));

        return $address === '' || str_contains($address, '[') ? null : $address;
    }

    /**
     * Turn-by-turn directions from wherever the visitor is (Google Maps works out their location).
     */
    public static function directionsUrl(): ?string
    {
        $custom = (string) config('church.directions_url');
        if (preg_match('#^https?://#', $custom)) {
            return $custom;
        }

        return ($address = self::address()) ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($address) : null;
    }

    /**
     * The place on Google Maps, to look at or save.
     */
    public static function mapUrl(): ?string
    {
        return ($address = self::address()) ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : null;
    }

    /**
     * A map to show on the page: the embed link from Settings, or one made from the address.
     */
    public static function embedUrl(): ?string
    {
        $custom = (string) config('church.map_embed_url');
        if (str_starts_with($custom, 'https://www.google.com/maps/embed')) {
            return $custom;
        }

        return ($address = self::address()) ? 'https://www.google.com/maps?q='.rawurlencode($address).'&output=embed' : null;
    }
}
