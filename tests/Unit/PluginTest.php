<?php

declare(strict_types=1);

use AcfCountryField\Database\Installer;
use AcfCountryField\Fields\CountryField;
use AcfCountryField\Fields\LegacyCountryField;
use AcfCountryField\Plugin;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    $GLOBALS['wpdb'] = new wpdb();
    $this->plugin = new Plugin('/plugins/acf-country-field/acf-country-field.php');
});

afterEach(function (): void {
    unset($GLOBALS['wpdb']);
});

it('hooks into WordPress and ACF', function (): void {
    Actions\expectAdded('init')->once()->with([$this->plugin, 'loadTextdomain'], 0);
    Actions\expectAdded('acf/include_field_types')->once()->with([$this->plugin, 'registerFieldTypes']);
    Filters\expectAdded('acf/load_field/type=COUNTRY_FIELD')->once()->with([$this->plugin, 'upgradeLegacyField']);
    Actions\expectAdded('admin_init')->once()->with([$this->plugin, 'maybeInstall']);
    Actions\expectAdded('admin_notices')->once()->with([$this->plugin, 'renderMissingAcfNotice']);
    Actions\expectAdded('wp_ajax_acf_country_field_cities')->once();
    Actions\expectAdded('wp_ajax_nopriv_acf_country_field_cities')->once();

    $this->plugin->init();
});

it('registers both field type names', function (): void {
    $registered = [];
    Functions\when('acf_register_field_type')->alias(function ($field) use (&$registered): void {
        $registered[] = $field;
    });

    $this->plugin->registerFieldTypes();

    expect($registered)->toHaveCount(2)
        ->and($registered[0])->toBeInstanceOf(CountryField::class)
        ->and($registered[1])->toBeInstanceOf(LegacyCountryField::class);
});

it('loads version 1 fields as the current type', function (): void {
    expect($this->plugin->upgradeLegacyField(['type' => 'COUNTRY_FIELD', 'name' => 'location']))
        ->toBe(['type' => 'country_field', 'name' => 'location']);
});

it('skips installing when the tables are current', function (): void {
    $this->siteOptions[Installer::VERSION_OPTION] = Installer::DB_VERSION;

    $this->plugin->maybeInstall();

    expect($GLOBALS['wpdb']->queries)->toBe([]);
});

it('warns administrators when ACF is missing', function (): void {
    Functions\when('current_user_can')->justReturn(true);

    ob_start();
    $this->plugin->renderMissingAcfNotice();
    $html = ob_get_clean();

    expect($html)->toContain('requires Advanced Custom Fields');
});

it('says nothing to users who cannot manage plugins', function (): void {
    Functions\when('current_user_can')->justReturn(false);

    ob_start();
    $this->plugin->renderMissingAcfNotice();

    expect(ob_get_clean())->toBe('');
});

it('uninstalls every site on a network', function (): void {
    $switched = [];
    Functions\when('is_multisite')->justReturn(true);
    Functions\when('get_sites')->justReturn([1, 2]);
    Functions\when('switch_to_blog')->alias(function (int $id) use (&$switched): void {
        $switched[] = $id;
    });
    Functions\when('restore_current_blog')->justReturn(true);

    Plugin::uninstall();

    expect($switched)->toBe([1, 2])
        ->and($GLOBALS['wpdb']->queries)->toHaveCount(6);
});
