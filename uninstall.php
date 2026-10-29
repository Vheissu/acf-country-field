<?php

/**
 * Removes the location tables and stored options when the plugin is deleted.
 *
 * Saved field values are left alone. They include the country, state and city names.
 *
 * @package AcfCountryField
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/autoload.php';

AcfCountryField\Plugin::uninstall();
