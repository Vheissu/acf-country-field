<?php

declare(strict_types=1);

use AcfCountryField\Database\Installer;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->wpdb = new wpdb();
    $this->dbDelta = [];

    Functions\when('dbDelta')->alias(function (array $queries): array {
        $this->dbDelta = $queries;

        return [];
    });

    $this->installer = new Installer($this->wpdb);
});

/**
 * @param array<string, mixed> $answers SQL fragments mapped to get_var() answers.
 */
function respondTo(wpdb $wpdb, array $answers): void
{
    $wpdb->respond = static function (string $method, string $sql) use ($answers) {
        foreach ($answers as $fragment => $answer) {
            if ($method === 'get_var' && str_contains($sql, $fragment)) {
                return $answer;
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

    $this->siteOptions[Installer::VERSION_OPTION] = '2.0.0';
    expect($this->installer->needsInstall())->toBeTrue();

    $this->siteOptions[Installer::VERSION_OPTION] = Installer::DB_VERSION;
    expect($this->installer->needsInstall())->toBeFalse();
});

it('creates the three tables with dbDelta', function (): void {
    respondTo($this->wpdb, ['COUNT(*)' => '0']);

    $this->installer->install();

    expect($this->dbDelta)->toHaveCount(3)
        ->and($this->dbDelta[0])->toStartWith('CREATE TABLE wp_countries (')
        ->and($this->dbDelta[1])->toStartWith('CREATE TABLE wp_states (')
        ->and($this->dbDelta[2])->toStartWith('CREATE TABLE wp_cities (')
        ->and($this->dbDelta[2])->toContain('PRIMARY KEY  (id)')
        ->and($this->dbDelta[2])->toContain('KEY country_state (country,state)')
        ->and($this->siteOptions[Installer::VERSION_OPTION])->toBe(Installer::DB_VERSION);
});

it('fills empty tables with every row and its fixed ID', function (): void {
    respondTo($this->wpdb, ['COUNT(*)' => '0']);

    $this->installer->install();

    $inserts = queriesStartingWith($this->wpdb, 'INSERT IGNORE INTO');
    $countries = array_values(array_filter($inserts, static fn($sql) => str_contains($sql, 'wp_countries')));
    $cities = array_values(array_filter($inserts, static fn($sql) => str_contains($sql, 'wp_cities')));

    $country_rows = require dirname(__DIR__, 2) . '/data/countries.php';
    $city_rows = require dirname(__DIR__, 2) . '/data/cities.php';

    expect($countries)->toHaveCount(1)
        ->and($countries[0])->toContain("(446, 'United States of America')")
        ->and($countries[0])->toContain("(3834, 'Cote d\\'Ivoire')")
        ->and(substr_count($countries[0], '),'))->toBe(count($country_rows) - 1)
        ->and($cities)->toHaveCount((int) ceil(count($city_rows) / 1000))
        ->and($cities[0])->toContain("(914, 'Bost/Laskar', 0, 237)");
});

it('fills tables again when version 1.0.1 numbered the countries', function (): void {
    respondTo($this->wpdb, ['COUNT(*)' => '211', 'United States of America' => '210']);

    $this->installer->install();

    expect(queriesStartingWith($this->wpdb, 'TRUNCATE TABLE'))->toBe([
        'TRUNCATE TABLE wp_cities',
        'TRUNCATE TABLE wp_states',
        'TRUNCATE TABLE wp_countries',
    ])->and(queriesStartingWith($this->wpdb, 'INSERT IGNORE INTO wp_cities'))->not->toBeEmpty();
});

it('tidies tables that have the right IDs', function (): void {
    respondTo($this->wpdb, ['COUNT(*)' => '210', 'United States of America' => '446']);

    $this->installer->install();

    $inserts = queriesStartingWith($this->wpdb, 'INSERT IGNORE INTO');
    $deletes = queriesStartingWith($this->wpdb, 'DELETE copy FROM wp_states');

    expect(queriesStartingWith($this->wpdb, 'TRUNCATE TABLE'))->toBe([])
        ->and($inserts)->toHaveCount(1)
        ->and($inserts[0])->toStartWith('INSERT IGNORE INTO wp_countries')
        ->and($deletes)->toHaveCount(1)
        ->and($deletes[0])->toContain('original.id = copy.id + 5 AND original.state = copy.state');
});

it('drops the tables and the version option on uninstall', function (): void {
    $this->siteOptions[Installer::VERSION_OPTION] = Installer::DB_VERSION;

    $this->installer->uninstall();

    expect($this->wpdb->queries)->toBe([
        'DROP TABLE IF EXISTS wp_cities',
        'DROP TABLE IF EXISTS wp_states',
        'DROP TABLE IF EXISTS wp_countries',
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
