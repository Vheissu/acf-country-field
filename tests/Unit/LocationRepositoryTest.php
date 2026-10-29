<?php

declare(strict_types=1);

use AcfCountryField\Repositories\LocationRepository;

beforeEach(function (): void {
    $this->wpdb = new wpdb();
    $this->repository = new LocationRepository($this->wpdb);
});

it('lists countries by ID in query order', function (): void {
    $this->wpdb->respond = static fn(string $method, string $sql) => [
        (object) ['id' => '248', 'name' => 'Australia'],
        (object) ['id' => '446', 'name' => 'United States of America'],
    ];

    expect($this->repository->getCountries())->toBe([248 => 'Australia', 446 => 'United States of America'])
        ->and($this->wpdb->queries[0])->toContain('FROM wp_acf_country_field_countries')
        ->and($this->wpdb->queries[0])->toContain('ORDER BY country ASC');
});

it('loads countries once per request', function (): void {
    $this->wpdb->respond = static fn() => [(object) ['id' => '248', 'name' => 'Australia']];

    $this->repository->getCountries();
    $this->repository->getCountry(248);
    $this->repository->findCountryId('Australia');

    expect($this->wpdb->queries)->toHaveCount(1);
});

it('finds countries by ID and by name', function (): void {
    $this->wpdb->respond = static fn() => [(object) ['id' => '248', 'name' => 'Australia']];

    expect($this->repository->getCountry(248))->toBe('Australia')
        ->and($this->repository->getCountry(1))->toBeNull()
        ->and($this->repository->findCountryId('Australia'))->toBe(248)
        ->and($this->repository->findCountryId('Atlantis'))->toBeNull();
});

it('returns an empty list when the query fails', function (): void {
    expect($this->repository->getCountries())->toBe([])
        ->and($this->repository->getStates())->toBe([]);
});

it('lists states by ID', function (): void {
    $this->wpdb->respond = static fn() => [(object) ['id' => '1', 'name' => 'Alabama']];

    expect($this->repository->getStates())->toBe([1 => 'Alabama'])
        ->and($this->repository->getState(1))->toBe('Alabama')
        ->and($this->repository->getState(2))->toBeNull()
        ->and($this->repository->findStateId('Alabama'))->toBe(1)
        ->and($this->repository->findStateId('Ontario'))->toBeNull()
        ->and($this->wpdb->queries[0])->toContain('FROM wp_acf_country_field_states');
});

it('lists the cities in a country', function (): void {
    $this->wpdb->respond = static fn() => [(object) ['id' => '1060', 'name' => 'Sydney']];

    expect($this->repository->getCities(248))->toBe([1060 => 'Sydney'])
        ->and($this->wpdb->queries[0])->toContain('WHERE country = 248 AND city')
        ->and($this->wpdb->queries[0])->not->toContain('state =');
});

it('limits cities to a state', function (): void {
    $this->wpdb->respond = static fn() => [];

    $this->repository->getCities(446, 5);

    expect($this->wpdb->queries[0])->toContain('WHERE country = 446 AND state = 5');
});

it('skips the city query without a country', function (): void {
    expect($this->repository->getCities(0))->toBe([])
        ->and($this->wpdb->queries)->toBe([]);
});

it('gets a city with its state and country', function (): void {
    $this->wpdb->respond = static fn() => (object) ['id' => '40000', 'city' => 'Austin', 'state' => '44', 'country' => '446'];

    expect($this->repository->getCity(40000))->toBe([
        'id' => 40000,
        'name' => 'Austin',
        'state_id' => 44,
        'country_id' => 446,
    ]);
});

it('returns null for missing or unnamed cities', function (): void {
    expect($this->repository->getCity(0))->toBeNull()
        ->and($this->repository->getCity(5))->toBeNull();

    $this->wpdb->respond = static fn() => (object) ['id' => '14431', 'city' => '', 'state' => '11', 'country' => '446'];

    expect($this->repository->getCity(14431))->toBeNull();
});

it('knows the United States ID', function (): void {
    expect($this->repository->isUnitedStates(446))->toBeTrue()
        ->and($this->repository->isUnitedStates(248))->toBeFalse();
});
