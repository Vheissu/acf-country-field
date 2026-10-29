<?php

declare(strict_types=1);

namespace AcfCountryField;

use AcfCountryField\Ajax\LocationAjaxHandler;
use AcfCountryField\Database\Installer;
use AcfCountryField\Fields\CountryField;
use AcfCountryField\Fields\LegacyCountryField;

/**
 * Wires the plugin into WordPress and ACF.
 */
final class Plugin
{
    public const VERSION = '2.1.0';

    // Matches LegacyCountryField::TYPE. That class can't load before ACF does, so it isn't referenced here.
    private const LEGACY_FIELD_TYPE = 'COUNTRY_FIELD';

    private string $plugin_file;

    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
    }

    public function init(): void
    {
        // Before ACF registers field types on init (priority 5), so labels are translated.
        add_action('init', [$this, 'loadTextdomain'], 0);
        add_action('acf/include_field_types', [$this, 'registerFieldTypes']);
        add_filter('acf/load_field/type=' . self::LEGACY_FIELD_TYPE, [$this, 'upgradeLegacyField']);
        add_action('admin_init', [$this, 'maybeInstall']);
        add_action('admin_notices', [$this, 'renderMissingAcfNotice']);

        (new LocationAjaxHandler())->register();
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'acf-country-field',
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }

    public function registerFieldTypes(): void
    {
        acf_register_field_type(new CountryField($this->plugin_file));
        acf_register_field_type(new LegacyCountryField($this->plugin_file));
    }

    /**
     * Version 1 registered the field type as "COUNTRY_FIELD". Loading those fields as the current
     * type means the field group editor shows the right type, and saving the group stores it.
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function upgradeLegacyField(array $field): array
    {
        $field['type'] = CountryField::TYPE;

        return $field;
    }

    /**
     * Installs or upgrades the location tables for the current site when needed. This covers
     * plugin updates that skip activation, and sites on a network where the plugin is network activated.
     */
    public function maybeInstall(): void
    {
        $installer = new Installer();

        if ($installer->needsInstall()) {
            $installer->install();
        }
    }

    /**
     * Warn administrators when ACF isn't loaded. Checked late so ACF bundled in a theme still counts.
     */
    public function renderMissingAcfNotice(): void
    {
        if (class_exists('ACF') || !current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'ACF Country Field requires Advanced Custom Fields to be installed and activated.',
                'acf-country-field'
            )
        );
    }

    public static function activate(): void
    {
        (new Installer())->install();
    }

    /**
     * Drops the location tables on every site. Called from uninstall.php.
     */
    public static function uninstall(): void
    {
        if (!is_multisite()) {
            (new Installer())->uninstall();
            return;
        }

        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
            switch_to_blog((int) $site_id);
            (new Installer())->uninstall();
            restore_current_blog();
        }
    }
}
