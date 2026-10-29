<?php

declare(strict_types=1);

namespace AcfCountryField\Repositories;

/**
 * Reads countries, US states and cities from the location tables.
 *
 * Countries and states are small, so each list is loaded once per request and kept in memory.
 *
 * @phpstan-type City array{id: int, name: string, state_id: int, country_id: int}
 */
class LocationRepository
{
    public const USA_COUNTRY_ID = 446;

    private \wpdb $wpdb;
    private string $countries_table;
    private string $states_table;
    private string $cities_table;

    /** @var array<int, string>|null */
    private ?array $countries = null;

    /** @var array<int, string>|null */
    private ?array $states = null;

    public function __construct(?\wpdb $wpdb = null)
    {
        if ($wpdb === null) {
            global $wpdb;
        }

        $this->wpdb = $wpdb;
        $this->countries_table = $wpdb->prefix . 'countries';
        $this->states_table = $wpdb->prefix . 'states';
        $this->cities_table = $wpdb->prefix . 'cities';
    }

    /**
     * @return array<int, string> Country names keyed by ID, sorted by name.
     */
    public function getCountries(): array
    {
        if ($this->countries === null) {
            $this->countries = $this->pairs(
                "SELECT id, country AS name FROM {$this->countries_table} WHERE country != '' ORDER BY country ASC"
            );
        }

        return $this->countries;
    }

    public function getCountry(int $country_id): ?string
    {
        return $this->getCountries()[$country_id] ?? null;
    }

    public function findCountryId(string $name): ?int
    {
        $id = array_search($name, $this->getCountries(), true);

        return $id === false ? null : $id;
    }

    /**
     * @return array<int, string> US state names keyed by ID, sorted by name.
     */
    public function getStates(): array
    {
        if ($this->states === null) {
            $this->states = $this->pairs(
                "SELECT id, state AS name FROM {$this->states_table} WHERE state != '' ORDER BY state ASC"
            );
        }

        return $this->states;
    }

    public function getState(int $state_id): ?string
    {
        return $this->getStates()[$state_id] ?? null;
    }

    public function findStateId(string $name): ?int
    {
        $id = array_search($name, $this->getStates(), true);

        return $id === false ? null : $id;
    }

    /**
     * Cities in a country, optionally limited to one US state.
     *
     * @return array<int, string> City names keyed by ID, sorted by name.
     */
    public function getCities(int $country_id, int $state_id = 0): array
    {
        if ($country_id <= 0) {
            return [];
        }

        if ($state_id > 0) {
            $sql = $this->wpdb->prepare(
                "SELECT id, city AS name FROM %i WHERE country = %d AND state = %d AND city != '' ORDER BY city ASC",
                $this->cities_table,
                $country_id,
                $state_id
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT id, city AS name FROM %i WHERE country = %d AND city != '' ORDER BY city ASC",
                $this->cities_table,
                $country_id
            );
        }

        return $this->pairs((string) $sql);
    }

    /**
     * @return City|null
     */
    public function getCity(int $city_id): ?array
    {
        if ($city_id <= 0) {
            return null;
        }

        $row = $this->wpdb->get_row(
            (string) $this->wpdb->prepare(
                "SELECT id, city, state, country FROM %i WHERE id = %d",
                $this->cities_table,
                $city_id
            )
        );

        if (!is_object($row) || (string) $row->city === '') {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'name' => (string) $row->city,
            'state_id' => (int) $row->state,
            'country_id' => (int) $row->country,
        ];
    }

    public function isUnitedStates(int $country_id): bool
    {
        return $country_id === self::USA_COUNTRY_ID;
    }

    /**
     * @return array<int, string>
     */
    private function pairs(string $sql): array
    {
        $rows = $this->wpdb->get_results($sql);

        if (!is_array($rows)) {
            return [];
        }

        $pairs = [];

        foreach ($rows as $row) {
            $pairs[(int) $row->id] = (string) $row->name;
        }

        return $pairs;
    }
}
