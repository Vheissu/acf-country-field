<?php

declare(strict_types=1);

namespace AcfCountryField;

use AcfCountryField\Ajax\LocationAjaxHandler;
use AcfCountryField\Database\Installer;
use AcfCountryField\Fields\CountryField;

/**
 * Main plugin class.
 *
 * Handles plugin initialization, hooks, and lifecycle.
 */
final class Plugin
{
    private const VERSION = '2.0.0';
    private const MIN_PHP_VERSION = '8.0';
    private const MIN_ACF_VERSION = '5.0.0';

    private static ?self $instance = null;
    private string $plugin_file;
    private Installer $installer;
    private LocationAjaxHandler $ajax_handler;

    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->installer = new Installer();
        $this->ajax_handler = new LocationAjaxHandler();
    }

    /**
     * Get singleton instance.
     */
    public static function getInstance(string $plugin_file = ''): self
    {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }

        return self::$instance;
    }

    /**
     * Initialize the plugin.
     */
    public function init(): void
    {
        // Check requirements
        if (!$this->checkRequirements()) {
            return;
        }

        // Load translations
        $this->loadTextdomain();

        // Register AJAX handlers
        $this->ajax_handler->register();

        // Register ACF field type
        add_action('acf/include_field_types', [$this, 'registerFieldType']);

        // Check for upgrades
        add_action('admin_init', [$this, 'maybeUpgrade']);
    }

    /**
     * Check if plugin requirements are met.
     */
    private function checkRequirements(): bool
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function (): void {
                $message = sprintf(
                    /* translators: 1: Required PHP version, 2: Current PHP version */
                    __('ACF Country Field requires PHP %1$s or higher. You are running PHP %2$s.', 'acf-country-field'),
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
                );
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
            });

            return false;
        }

        // Check if ACF is active
        if (!$this->isAcfActive()) {
            add_action('admin_notices', function (): void {
                $message = __('ACF Country Field requires Advanced Custom Fields to be installed and activated.', 'acf-country-field');
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
            });

            return false;
        }

        return true;
    }

    /**
     * Check if ACF is active.
     */
    private function isAcfActive(): bool
    {
        return class_exists('ACF') || class_exists('acf');
    }

    /**
     * Load plugin translations.
     */
    private function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'acf-country-field',
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }

    /**
     * Register the ACF field type.
     */
    public function registerFieldType(): void
    {
        new CountryField();
    }

    /**
     * Check if database upgrade is needed.
     */
    public function maybeUpgrade(): void
    {
        if ($this->installer->needsUpgrade()) {
            $this->installer->upgrade();
        }
    }

    /**
     * Plugin activation hook.
     */
    public static function activate(): void
    {
        $installer = new Installer();
        $installer->install();

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Plugin deactivation hook.
     *
     * Note: We don't remove data on deactivation, only on uninstall.
     */
    public static function deactivate(): void
    {
        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Plugin uninstall hook.
     *
     * Removes all plugin data including database tables.
     */
    public static function uninstall(): void
    {
        $installer = new Installer();
        $installer->uninstall();

        // Clean up any options
        delete_option('acf_country_field_db_version');
    }

    /**
     * Get plugin version.
     */
    public static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get plugin directory path.
     */
    public function getPath(): string
    {
        return plugin_dir_path($this->plugin_file);
    }

    /**
     * Get plugin directory URL.
     */
    public function getUrl(): string
    {
        return plugin_dir_url($this->plugin_file);
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization.
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
