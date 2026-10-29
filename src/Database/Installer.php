<?php

declare(strict_types=1);

namespace AcfCountryField\Database;

/**
 * Creates, fills and removes the location tables.
 *
 * Location IDs are fixed, because saved field values and city rows refer to them. Version 1 kept
 * the data in tables with generic names (`{prefix}countries` and so on). install() replaces them
 * with the plugin's own tables, filled from the shipped data, and drops the old ones.
 */
class Installer
{
    public const DB_VERSION = '2.1.0.1';
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
        $this->countries_table = $wpdb->prefix . Tables::COUNTRIES;
        $this->states_table = $wpdb->prefix . Tables::STATES;
        $this->cities_table = $wpdb->prefix . Tables::CITIES;
    }

    public function needsInstall(): bool
    {
        return version_compare((string) get_option(self::VERSION_OPTION, '0'), self::DB_VERSION, '<');
    }

    /**
     * Creates or upgrades the tables and fills them. Safe to run more than once, and finishes
     * filling tables that an earlier run left part done.
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

        $this->insert($this->countries_table, ['id', 'country'], ['%d', '%s'], $this->data('countries'));
        $this->insert($this->states_table, ['id', 'state'], ['%d', '%s'], $this->data('states'));
        $this->insert($this->cities_table, ['id', 'city', 'state', 'country'], ['%d', '%s', '%d', '%d'], $this->data('cities'));

        $this->dropLegacyTables();

        update_option(self::VERSION_OPTION, self::DB_VERSION);
    }

    public function uninstall(): void
    {
        foreach ([$this->cities_table, $this->states_table, $this->countries_table] as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        $this->dropLegacyTables();

        delete_option(self::VERSION_OPTION);
    }

    /**
     * Drops version 1's tables. Another plugin could use the same generic names, so they're only
     * dropped when all three exist with exactly version 1's columns.
     */
    private function dropLegacyTables(): void
    {
        $tables = [];

        foreach (Tables::LEGACY as $name => $columns) {
            $table = $this->wpdb->prefix . $name;

            if ($this->columns($table) !== $columns) {
                return;
            }

            $tables[] = $table;
        }

        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * @return list<string> The table's column names, or none when it doesn't exist.
     */
    private function columns(string $table): array
    {
        $exists = $this->wpdb->get_var(
            (string) $this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table))
        );

        if ($exists !== $table) {
            return [];
        }

        return array_map('strval', $this->wpdb->get_col((string) $this->wpdb->prepare('SHOW COLUMNS FROM %i', $table)));
    }

    /**
     * Table definitions in the format dbDelta() expects.
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
