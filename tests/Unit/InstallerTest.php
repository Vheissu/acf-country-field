<?php

declare(strict_types=1);

use AcfCountryField\Database\Installer;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->wpdb = new wpdb();
    $this->wpdb->respond = static fn(string $method) => $method === 'query' ? 1 : null;
    $this->dbDelta = [];

    Functions\when('dbDelta')->alias(function (array $queries): array {
        $this->dbDelta = $queries;

        return [];
    });

    $this->installer = new Installer($this->wpdb);
});

/**
 * Answers the installer's table checks as if the given tables exist with the given columns.
 *
 * @param array<string, list<string>> $tables
 */
function withTables(wpdb $wpdb, array $tables): void
{
    $wpdb->respond = static function (string $method, string $sql) use ($tables) {
        foreach ($tables as $table => $columns) {
            if ($method === 'get_var' && $sql === "SHOW TABLES LIKE '" . addslashes(addcslashes($table, '_%\\')) . "'") {
                return $table;
            }

            if ($method === 'get_col' && $sql === "SHOW COLUMNS FROM {$table}") {
                return $columns;
            }
        }

        return $method === 'query' ? 1 : null;
    };
}

/**
 * @return list<string>
 */
function queriesStartingWith(wpdb $wpdb, string $start): array
{
    return array_values(array_filter($wpdb->queries, static fn(string $sql): bool => str_starts_with($sql, $start)));
}

it('needs installing until the current version is stored', function (): void {
    expect($this->installer->needsInstall())->toBeTrue();

    $this->siteOptions[Installer::VERSION_OPTION] = '2.1.0';
    expect($this->installer->needsInstall())->toBeTrue();

    $this->siteOptions[Installer::VERSION_OPTION] = Installer::DB_VERSION;
    expect($this->installer->needsInstall())->toBeFalse();
});

it('creates the plugin tables with dbDelta', function (): void {
    $this->installer->install();

    expect($this->dbDelta)->toHaveCount(3)
        ->and($this->dbDelta[0])->toStartWith('CREATE TABLE wp_acf_country_field_countries (')
        ->and($this->dbDelta[1])->toStartWith('CREATE TABLE wp_acf_country_field_states (')
        ->and($this->dbDelta[2])->toStartWith('CREATE TABLE wp_acf_country_field_cities (')
        ->and($this->dbDelta[2])->toContain('PRIMARY KEY  (id)')
        ->and($this->dbDelta[2])->toContain('KEY country_state (country,state)')
        ->and($this->siteOptions[Installer::VERSION_OPTION])->toBe(Installer::DB_VERSION);
});

it('fills the tables with every row and its fixed ID', function (): void {
    $this->installer->install();

    $countries = queriesStartingWith($this->wpdb, 'INSERT IGNORE INTO wp_acf_country_field_countries');
    $states = queriesStartingWith($this->wpdb, 'INSERT IGNORE INTO wp_acf_country_field_states');
    $cities = queriesStartingWith($this->wpdb, 'INSERT IGNORE INTO wp_acf_country_field_cities');

    $country_rows = require dirname(__DIR__, 2) . '/data/countries.php';
    $city_rows = require dirname(__DIR__, 2) . '/data/cities.php';

    expect($countries)->toHaveCount(1)
        ->and($countries[0])->toContain("(446, 'United States of America')")
        ->and($countries[0])->toContain("(3834, 'Cote d\\'Ivoire')")
        ->and(substr_count($countries[0], '),'))->toBe(count($country_rows) - 1)
        ->and($states)->toHaveCount(1)
        ->and($states[0])->toContain("(49, 'Texas')")
        ->and($cities)->toHaveCount((int) ceil(count($city_rows) / 1000))
        ->and($cities[0])->toContain("(914, 'Bost/Laskar', 0, 237)");
});

it('drops version 1 tables', function (): void {
    withTables($this->wpdb, [
        'wp_cities' => ['id', 'city', 'state', 'country'],
        'wp_states' => ['id', 'state'],
        'wp_countries' => ['id', 'country'],
    ]);

    $this->installer->install();

    expect(queriesStartingWith($this->wpdb, 'DROP TABLE'))->toBe([
        'DROP TABLE IF EXISTS wp_cities',
        'DROP TABLE IF EXISTS wp_states',
        'DROP TABLE IF EXISTS wp_countries',
    ]);
});

it('leaves tables with the old names alone when they belong to something else', function (array $tables): void {
    withTables($this->wpdb, $tables);

    $this->installer->install();

    expect(queriesStartingWith($this->wpdb, 'DROP TABLE'))->toBe([]);
})->with([
    'different columns' => [[
        'wp_cities' => ['id', 'city', 'state', 'country'],
        'wp_states' => ['id', 'state', 'code'],
        'wp_countries' => ['id', 'country'],
    ]],
    'only some tables' => [[
        'wp_cities' => ['id', 'city', 'state', 'country'],
        'wp_countries' => ['id', 'country'],
    ]],
]);

it('drops the tables and the version option on uninstall', function (): void {
    $this->siteOptions[Installer::VERSION_OPTION] = Installer::DB_VERSION;

    $this->installer->uninstall();

    expect(queriesStartingWith($this->wpdb, 'DROP TABLE'))->toBe([
        'DROP TABLE IF EXISTS wp_acf_country_field_cities',
        'DROP TABLE IF EXISTS wp_acf_country_field_states',
        'DROP TABLE IF EXISTS wp_acf_country_field_countries',
    ])->and($this->siteOptions)->not->toHaveKey(Installer::VERSION_OPTION);
});

it('ships consistent location data', function (): void {
    $data = dirname(__DIR__, 2) . '/data';
    $countries = array_column(require $data . '/countries.php', 1, 0);
    $states = array_column(require $data . '/states.php', 1, 0);
    $cities = require $data . '/cities.php';

    $city_ids = array_column($cities, 0);
    $city_keys = array_map(static fn(array $city): string => strtolower($city[1]) . '|' . $city[2] . '|' . $city[3], $cities);

    expect($countries[446])->toBe('United States of America')
        ->and(count(array_unique($countries)))->toBe(count($countries))
        ->and($states)->toHaveCount(51)
        ->and(count(array_unique($states)))->toBe(51)
        ->and(count(array_unique($city_ids)))->toBe(count($city_ids))
        ->and(count(array_unique($city_keys)))->toBe(count($cities));

    $bad = array_filter($cities, static fn(array $city): bool => $city[1] === ''
        || !isset($countries[$city[3]])
        || ($city[2] !== 0 && ($city[3] !== 446 || !isset($states[$city[2]]))));

    expect($bad)->toBe([]);
});
