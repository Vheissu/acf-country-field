<?php

/**
 * Class autoloader, shared by the plugin and uninstall.php. Composer is only needed for development.
 *
 * @package AcfCountryField
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'AcfCountryField\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
