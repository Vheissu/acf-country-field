<?php

declare(strict_types=1);

namespace AcfCountryField\Database;

/**
 * Handles database table creation and removal.
 *
 * Note: Uses direct SQL for table creation/removal instead of dbDelta
 * because dbDelta doesn't support DROP TABLE statements.
 */
class Installer
{
    private \wpdb $wpdb;
    private string $countries_table;
    private string $cities_table;
    private string $states_table;
    private string $charset_collate;

    public function __construct(?\wpdb $wpdb = null)
    {
        global $wpdb as $global_wpdb;
        $this->wpdb = $wpdb ?? $global_wpdb;

        $this->countries_table = $this->wpdb->prefix . 'countries';
        $this->cities_table = $this->wpdb->prefix . 'cities';
        $this->states_table = $this->wpdb->prefix . 'states';
        $this->charset_collate = $this->wpdb->get_charset_collate();
    }

    /**
     * Install database tables and populate with data.
     */
    public function install(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $this->createTables();
        $this->populateData();

        // Store installed version
        update_option('acf_country_field_db_version', '2.0.0');
    }

    /**
     * Create the database tables using proper charset.
     */
    private function createTables(): void
    {
        // Countries table
        $sql_countries = "CREATE TABLE IF NOT EXISTS {$this->countries_table} (
            id INT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
            country VARCHAR(100) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY country_idx (country)
        ) {$this->charset_collate};";

        // Cities table
        $sql_cities = "CREATE TABLE IF NOT EXISTS {$this->cities_table} (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            city VARCHAR(200) NOT NULL DEFAULT '',
            state INT(3) UNSIGNED DEFAULT 0,
            country INT(3) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY country_idx (country),
            KEY city_idx (city)
        ) {$this->charset_collate};";

        // States table
        $sql_states = "CREATE TABLE IF NOT EXISTS {$this->states_table} (
            id TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
            state VARCHAR(50) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY state_idx (state)
        ) {$this->charset_collate};";

        dbDelta($sql_countries);
        dbDelta($sql_cities);
        dbDelta($sql_states);
    }

    /**
     * Populate tables with location data.
     */
    private function populateData(): void
    {
        // Check if data already exists
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->countries_table}");

        if ((int) $count > 0) {
            return; // Data already populated
        }

        // Include the data file (captures output for dbDelta)
        $data_file = dirname(__DIR__, 2) . '/lib/install-data.php';

        if (file_exists($data_file)) {
            ob_start();
            require $data_file;
            $sql = ob_get_clean();

            if (!empty($sql)) {
                // Execute the SQL statements
                $this->executeMultipleStatements($sql);
            }
        }
    }

    /**
     * Execute multiple SQL statements.
     *
     * @param string $sql Multiple SQL statements separated by semicolons
     */
    private function executeMultipleStatements(string $sql): void
    {
        // Split by semicolons (handling INSERT statements)
        $statements = explode(";\n", $sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);

            // Skip empty statements and CREATE TABLE (already done)
            if (empty($statement) || stripos($statement, 'CREATE TABLE') === 0) {
                continue;
            }

            // Execute INSERT statements
            if (stripos($statement, 'INSERT') === 0) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Data file contains static SQL
                $this->wpdb->query($statement);
            }
        }
    }

    /**
     * Uninstall database tables.
     *
     * Note: Only call this on complete plugin uninstall, not deactivation.
     * This preserves user data during temporary deactivations.
     */
    public function uninstall(): void
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are constructed from wpdb prefix
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->cities_table}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->states_table}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->countries_table}");

        delete_option('acf_country_field_db_version');
    }

    /**
     * Check if upgrade is needed.
     *
     * @return bool True if upgrade is needed
     */
    public function needsUpgrade(): bool
    {
        $installed_version = get_option('acf_country_field_db_version', '0');
        return version_compare($installed_version, '2.0.0', '<');
    }

    /**
     * Run upgrade routines.
     */
    public function upgrade(): void
    {
        $installed_version = get_option('acf_country_field_db_version', '0');

        // Upgrade from v1.x to v2.0
        if (version_compare($installed_version, '2.0.0', '<')) {
            $this->createTables(); // Ensures tables have correct charset
            update_option('acf_country_field_db_version', '2.0.0');
        }
    }
}
