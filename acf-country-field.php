<?php

/**
 * Plugin Name: Advanced Custom Fields: Country Field
 * Plugin URI: https://github.com/Vheissu/acf-country-field
 * Description: Adds a country, state and city field type to Advanced Custom Fields.
 * Version: 2.1.0
 * Author: Dwayne Charrington
 * Author URI: https://ilikekillnerds.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-country-field
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 *
 * @package AcfCountryField
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ACF_COUNTRY_FIELD_VERSION', '2.1.0');
define('ACF_COUNTRY_FIELD_FILE', __FILE__);
define('ACF_COUNTRY_FIELD_PATH', plugin_dir_path(__FILE__));

require_once ACF_COUNTRY_FIELD_PATH . 'autoload.php';

register_activation_hook(__FILE__, [AcfCountryField\Plugin::class, 'activate']);

(new AcfCountryField\Plugin(ACF_COUNTRY_FIELD_FILE))->init();
