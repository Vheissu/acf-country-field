<?php

declare(strict_types=1);

namespace AcfCountryField\Repositories;

/**
 * Repository for accessing location data (countries, cities, states).
 *
 * All database queries use prepared statements to prevent SQL injection.
 */
class LocationRepository
{
    private \wpdb $wpdb;
    private string $countries_table;
    private string $cities_table;
    private string $states_table;

    public const USA_COUNTRY_ID = 446;

    public function __construct(?\wpdb $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
            $this->wpdb = $wpdb;
        } else {
            $this->wpdb = $wpdb;
        }

        $this->countries_table = $this->wpdb->prefix . 'countries';
        $this->cities_table = $this->wpdb->prefix . 'cities';
        $this->states_table = $this->wpdb->prefix . 'states';
    }

    /**
     * Get all countries ordered alphabetically.
     *
     * @return array<int, string> Associative array of country_id => country_name
     */
    public function getCountries(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT DISTINCT id, country FROM {$this->countries_table} WHERE country != '' ORDER BY country ASC"
        );

        if (!$results) {
            return [];
        }

        $countries = [];
        foreach ($results as $row) {
            $countries[(int) $row->id] = $row->country;
        }

        return $countries;
    }

    /**
     * Get a single country by ID.
     *
     * @param int $country_id The country ID
     * @return string|null The country name or null if not found
     */
    public function getCountry(int $country_id): ?string
    {
        if ($country_id <= 0) {
            return null;
        }

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT country FROM {$this->countries_table} WHERE id = %d",
                $country_id
            )
        );

        return $result ?: null;
    }

    /**
     * Get all cities for a given country.
     *
     * @param int $country_id The country ID
     * @return array<int, string> Associative array of city_id => city_name
     */
    public function getCitiesByCountry(int $country_id): array
    {
        if ($country_id <= 0) {
            return [];
        }

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT id, city FROM {$this->cities_table} WHERE country = %d AND city != '' ORDER BY city ASC",
                $country_id
            )
        );

        if (!$results) {
            return [];
        }

        $cities = [];
        foreach ($results as $row) {
            $cities[(int) $row->id] = $row->city;
        }

        return $cities;
    }

    /**
     * Get a single city by ID.
     *
     * @param int $city_id The city ID
     * @return string|null The city name or null if not found
     */
    public function getCity(int $city_id): ?string
    {
        if ($city_id <= 0) {
            return null;
        }

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT city FROM {$this->cities_table} WHERE id = %d",
                $city_id
            )
        );

        return $result ?: null;
    }

    /**
     * Get all US states ordered alphabetically.
     *
     * @return array<int, string> Associative array of state_id => state_name
     */
    public function getStates(): array
    {
        $results = $this->wpdb->get_results(
            "SELECT DISTINCT id, state FROM {$this->states_table} WHERE state != '' ORDER BY state ASC"
        );

        if (!$results) {
            return [];
        }

        $states = [];
        foreach ($results as $row) {
            $states[(int) $row->id] = $row->state;
        }

        return $states;
    }

    /**
     * Get a single state by ID.
     *
     * @param int $state_id The state ID
     * @return string|null The state name or null if not found
     */
    public function getState(int $state_id): ?string
    {
        if ($state_id <= 0) {
            return null;
        }

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT state FROM {$this->states_table} WHERE id = %d",
                $state_id
            )
        );

        return $result ?: null;
    }

    /**
     * Check if a country ID represents the United States.
     *
     * @param int $country_id The country ID to check
     * @return bool True if the country is USA
     */
    public function isUnitedStates(int $country_id): bool
    {
        return $country_id === self::USA_COUNTRY_ID;
    }

    /**
     * Check if the location tables exist.
     *
     * @return bool True if all tables exist
     */
    public function tablesExist(): bool
    {
        $tables = [
            $this->countries_table,
            $this->cities_table,
            $this->states_table,
        ];

        foreach ($tables as $table) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table
                )
            );

            if ($exists !== $table) {
                return false;
            }
        }

        return true;
    }
}
