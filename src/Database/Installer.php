<?php

declare(strict_types=1);

namespace AcfCountryField\Database;

use AcfCountryField\Repositories\LocationRepository;

/**
 * Creates, fills and removes the location tables.
 *
 * Location IDs are fixed, because saved field values and city rows refer to them. Version 1.0.1
 * inserted countries without their IDs, so tables filled by that version can't match countries
 * to cities. install() detects those tables and fills them again, and tidies up older tables.
 */
class Installer
{
    public const DB_VERSION = '2.1.0';
    public const VERSION_OPTION = 'acf_country_field_db_version';

    private const BATCH_SIZE = 1000;

    private \wpdb $wpdb;
    private string $data_dir;
    private string $countries_table;
    private string $states_table;
    private string $cities_table;

    public function __construct(?\wpdb $wpdb = null, ?string $data_dir = null)
    {
        if ($wpdb === null) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
        $this->data_dir = $data_dir ?? dirname(__DIR__, 2) . '/data';
        $this->countries_table = $wpdb->prefix . 'countries';
        $this->states_table = $wpdb->prefix . 'states';
        $this->cities_table = $wpdb->prefix . 'cities';
    }

    public function needsInstall(): bool
    {
        return version_compare((string) get_option(self::VERSION_OPTION, '0'), self::DB_VERSION, '<');
    }

    /**
     * Creates or upgrades the tables and fills them. Safe to run more than once.
     */
    public function install(): void
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        // Filling the tables runs about 40 large queries.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        dbDelta($this->schema());
        $this->fill();

        update_option(self::VERSION_OPTION, self::DB_VERSION);
    }

    public function uninstall(): void
    {
        foreach ([$this->cities_table, $this->states_table, $this->countries_table] as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::VERSION_OPTION);
    }

    /**
     * Table definitions in the format dbDelta() expects, so existing tables get the new columns and indexes.
     *
     * @return list<string>
     */
    private function schema(): array
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        return [
            "CREATE TABLE {$this->countries_table} (
  id int(10) unsigned NOT NULL auto_increment,
  country varchar(100) NOT NULL default '',
  PRIMARY KEY  (id),
  KEY country (country)
) {$charset_collate};",
            "CREATE TABLE {$this->states_table} (
  id smallint(5) unsigned NOT NULL auto_increment,
  state varchar(50) NOT NULL default '',
  PRIMARY KEY  (id),
  KEY state (state)
) {$charset_collate};",
            "CREATE TABLE {$this->cities_table} (
  id int(10) unsigned NOT NULL auto_increment,
  city varchar(200) NOT NULL default '',
  state smallint(5) unsigned NOT NULL default 0,
  country int(10) unsigned NOT NULL default 0,
  PRIMARY KEY  (id),
  KEY country_state (country,state),
  KEY city (city)
) {$charset_collate};",
        ];
    }

    private function fill(): void
    {
        $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->countries_table}");

        if ($count === 0 || $this->hasMismatchedIds()) {
            foreach ([$this->cities_table, $this->states_table, $this->countries_table] as $table) {
                $this->wpdb->query("TRUNCATE TABLE {$table}");
            }

            $this->insert($this->countries_table, ['id', 'country'], ['%d', '%s'], $this->data('countries'));
            $this->insert($this->states_table, ['id', 'state'], ['%d', '%s'], $this->data('states'));
            $this->insert($this->cities_table, ['id', 'city', 'state', 'country'], ['%d', '%s', '%d', '%d'], $this->data('cities'));

            return;
        }

        // Tables from before 1.0.1 have the right IDs, but are missing Cote d'Ivoire.
        $this->insert($this->countries_table, ['id', 'country'], ['%d', '%s'], $this->data('countries'));

        // They also list Alabama to California twice. No city uses the first copies, IDs 1 to 5.
        $this->wpdb->query(
            "DELETE copy FROM {$this->states_table} AS copy
            JOIN {$this->states_table} AS original ON original.id = copy.id + 5 AND original.state = copy.state
            WHERE copy.id <= 5"
        );
    }

    /**
     * True when the tables were filled by version 1.0.1, which numbered countries from 1.
     */
    private function hasMismatchedIds(): bool
    {
        $usa_id = $this->wpdb->get_var(
            (string) $this->wpdb->prepare(
                "SELECT id FROM %i WHERE country = %s LIMIT 1",
                $this->countries_table,
                'United States of America'
            )
        );

        return $usa_id !== null && (int) $usa_id !== LocationRepository::USA_COUNTRY_ID;
    }

    /**
     * @return list<list<int|string>>
     */
    private function data(string $name): array
    {
        return require $this->data_dir . '/' . $name . '.php';
    }

    /**
     * Inserts rows in batches, skipping any whose ID already exists.
     *
     * @param list<string> $columns
     * @param list<string> $formats
     * @param list<list<int|string>> $rows
     */
    private function insert(string $table, array $columns, array $formats, array $rows): void
    {
        $placeholders = '(' . implode(', ', $formats) . ')';
        $prefix = "INSERT IGNORE INTO {$table} (" . implode(', ', $columns) . ') VALUES ';

        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            $sql = $prefix . implode(', ', array_fill(0, count($batch), $placeholders));

            // @phpstan-ignore argument.type (the query is built from the table name and fixed placeholders.)
            $this->wpdb->query((string) $this->wpdb->prepare($sql, ...array_merge(...$batch)));
        }
    }
}
