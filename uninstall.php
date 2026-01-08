<?php

declare(strict_types=1);

/**
 * Uninstall script for ACF Country Field.
 *
 * This file is called when the plugin is uninstalled (deleted).
 * It removes all plugin data including database tables.
 *
 * @package AcfCountryField
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load autoloader
$autoloader = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    // Manual class loading
    require_once __DIR__ . '/src/Database/Installer.php';
}

// Run uninstall
AcfCountryField\Plugin::uninstall();
