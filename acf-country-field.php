<?php

declare(strict_types=1);

/**
 * Plugin Name: Advanced Custom Fields: Country Field
 * Plugin URI: https://github.com/Vheissu/acf-country-field
 * Description: Adds a country, city, and state field to your WordPress sites using Advanced Custom Fields.
 * Version: 2.0.0
 * Author: Dwayne Charrington
 * Author URI: https://ilikekillnerds.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-country-field
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 *
 * @package AcfCountryField
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ACF_COUNTRY_FIELD_VERSION', '2.0.0');
define('ACF_COUNTRY_FIELD_FILE', __FILE__);
define('ACF_COUNTRY_FIELD_PATH', plugin_dir_path(__FILE__));
define('ACF_COUNTRY_FIELD_URL', plugin_dir_url(__FILE__));

// Autoloader
$autoloader = ACF_COUNTRY_FIELD_PATH . 'vendor/autoload.php';

if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    // Fallback: Manual class loading for when composer isn't run
    spl_autoload_register(static function (string $class): void {
        $prefix = 'AcfCountryField\\';
        $base_dir = ACF_COUNTRY_FIELD_PATH . 'src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, [AcfCountryField\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [AcfCountryField\Plugin::class, 'deactivate']);

// Initialize plugin after plugins are loaded (ensures ACF is available)
add_action('plugins_loaded', static function (): void {
    $plugin = AcfCountryField\Plugin::getInstance(ACF_COUNTRY_FIELD_FILE);
    $plugin->init();
}, 15);
