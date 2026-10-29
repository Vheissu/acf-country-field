<?php

declare(strict_types=1);

namespace AcfCountryField\Database;

/**
 * Names of the location tables, without the site's table prefix.
 */
final class Tables
{
    public const COUNTRIES = 'acf_country_field_countries';
    public const STATES = 'acf_country_field_states';
    public const CITIES = 'acf_country_field_cities';

    /**
     * Version 1 used these generic names. Kept so install() can remove them.
     */
    public const LEGACY = [
        'cities' => ['id', 'city', 'state', 'country'],
        'states' => ['id', 'state'],
        'countries' => ['id', 'country'],
    ];
}
