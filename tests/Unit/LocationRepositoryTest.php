<?php

declare(strict_types=1);

use AcfCountryField\Repositories\LocationRepository;

beforeEach(function (): void {
    $this->wpdb = Mockery::mock('wpdb');
    $this->wpdb->prefix = 'wp_';
});

describe('LocationRepository', function (): void {

    describe('getCountries', function (): void {

        it('returns an empty array when no countries exist', function (): void {
            $this->wpdb->shouldReceive('get_results')
                ->once()
                ->andReturn([]);

            $repository = new LocationRepository($this->wpdb);
            $countries = $repository->getCountries();

            expect($countries)->toBeArray()->toBeEmpty();
        });

        it('returns countries as id => name array', function (): void {
            $mockResults = [
                (object) ['id' => 1, 'country' => 'Australia'],
                (object) ['id' => 2, 'country' => 'Canada'],
                (object) ['id' => 446, 'country' => 'United States'],
            ];

            $this->wpdb->shouldReceive('get_results')
                ->once()
                ->andReturn($mockResults);

            $repository = new LocationRepository($this->wpdb);
            $countries = $repository->getCountries();

            expect($countries)->toBeArray()
                ->and($countries)->toHaveCount(3)
                ->and($countries[1])->toBe('Australia')
                ->and($countries[2])->toBe('Canada')
                ->and($countries[446])->toBe('United States');
        });

    });

    describe('getCountry', function (): void {

        it('returns null for invalid country ID', function (): void {
            $repository = new LocationRepository($this->wpdb);

            expect($repository->getCountry(0))->toBeNull()
                ->and($repository->getCountry(-1))->toBeNull();
        });

        it('returns country name for valid ID', function (): void {
            $this->wpdb->shouldReceive('prepare')
                ->once()
                ->with(Mockery::type('string'), 446)
                ->andReturn("SELECT country FROM wp_countries WHERE id = 446");

            $this->wpdb->shouldReceive('get_var')
                ->once()
                ->andReturn('United States');

            $repository = new LocationRepository($this->wpdb);
            $country = $repository->getCountry(446);

            expect($country)->toBe('United States');
        });

        it('returns null when country not found', function (): void {
            $this->wpdb->shouldReceive('prepare')
                ->once()
                ->andReturn("SELECT country FROM wp_countries WHERE id = 99999");

            $this->wpdb->shouldReceive('get_var')
                ->once()
                ->andReturn(null);

            $repository = new LocationRepository($this->wpdb);
            $country = $repository->getCountry(99999);

            expect($country)->toBeNull();
        });

    });

    describe('getCitiesByCountry', function (): void {

        it('returns empty array for invalid country ID', function (): void {
            $repository = new LocationRepository($this->wpdb);

            expect($repository->getCitiesByCountry(0))->toBeEmpty()
                ->and($repository->getCitiesByCountry(-1))->toBeEmpty();
        });

        it('returns cities for valid country ID', function (): void {
            $mockResults = [
                (object) ['id' => 100, 'city' => 'New York'],
                (object) ['id' => 101, 'city' => 'Los Angeles'],
            ];

            $this->wpdb->shouldReceive('prepare')
                ->once()
                ->with(Mockery::type('string'), 446)
                ->andReturn("SELECT DISTINCT id, city FROM wp_cities WHERE country = 446");

            $this->wpdb->shouldReceive('get_results')
                ->once()
                ->andReturn($mockResults);

            $repository = new LocationRepository($this->wpdb);
            $cities = $repository->getCitiesByCountry(446);

            expect($cities)->toHaveCount(2)
                ->and($cities[100])->toBe('New York')
                ->and($cities[101])->toBe('Los Angeles');
        });

    });

    describe('getCity', function (): void {

        it('returns null for invalid city ID', function (): void {
            $repository = new LocationRepository($this->wpdb);

            expect($repository->getCity(0))->toBeNull();
        });

        it('returns city name for valid ID', function (): void {
            $this->wpdb->shouldReceive('prepare')
                ->once()
                ->andReturn("SELECT city FROM wp_cities WHERE id = 100");

            $this->wpdb->shouldReceive('get_var')
                ->once()
                ->andReturn('New York');

            $repository = new LocationRepository($this->wpdb);

            expect($repository->getCity(100))->toBe('New York');
        });

    });

    describe('getStates', function (): void {

        it('returns states as id => name array', function (): void {
            $mockResults = [
                (object) ['id' => 1, 'state' => 'Alabama'],
                (object) ['id' => 5, 'state' => 'California'],
                (object) ['id' => 33, 'state' => 'New York'],
            ];

            $this->wpdb->shouldReceive('get_results')
                ->once()
                ->andReturn($mockResults);

            $repository = new LocationRepository($this->wpdb);
            $states = $repository->getStates();

            expect($states)->toHaveCount(3)
                ->and($states[1])->toBe('Alabama')
                ->and($states[5])->toBe('California')
                ->and($states[33])->toBe('New York');
        });

    });

    describe('getState', function (): void {

        it('returns null for invalid state ID', function (): void {
            $repository = new LocationRepository($this->wpdb);

            expect($repository->getState(0))->toBeNull();
        });

        it('returns state name for valid ID', function (): void {
            $this->wpdb->shouldReceive('prepare')
                ->once()
                ->andReturn("SELECT state FROM wp_states WHERE id = 5");

            $this->wpdb->shouldReceive('get_var')
                ->once()
                ->andReturn('California');

            $repository = new LocationRepository($this->wpdb);

            expect($repository->getState(5))->toBe('California');
        });

    });

    describe('isUnitedStates', function (): void {

        it('returns true for USA country ID', function (): void {
            $repository = new LocationRepository($this->wpdb);

            expect($repository->isUnitedStates(446))->toBeTrue();
        });

        it('returns false for non-USA country IDs', function (): void {
            $repository = new LocationRepository($this->wpdb);

            expect($repository->isUnitedStates(1))->toBeFalse()
                ->and($repository->isUnitedStates(100))->toBeFalse()
                ->and($repository->isUnitedStates(0))->toBeFalse();
        });

    });

    describe('tablesExist', function (): void {

        it('returns true when all tables exist', function (): void {
            $this->wpdb->shouldReceive('prepare')
                ->times(3)
                ->andReturnUsing(fn($sql, $table) => "SHOW TABLES LIKE '$table'");

            $this->wpdb->shouldReceive('get_var')
                ->times(3)
                ->andReturnUsing(function () {
                    static $calls = 0;
                    $tables = ['wp_countries', 'wp_cities', 'wp_states'];
                    return $tables[$calls++];
                });

            $repository = new LocationRepository($this->wpdb);

            expect($repository->tablesExist())->toBeTrue();
        });

        it('returns false when a table is missing', function (): void {
            $this->wpdb->shouldReceive('prepare')
                ->andReturnUsing(fn($sql, $table) => "SHOW TABLES LIKE '$table'");

            $this->wpdb->shouldReceive('get_var')
                ->andReturn(null); // Table doesn't exist

            $repository = new LocationRepository($this->wpdb);

            expect($repository->tablesExist())->toBeFalse();
        });

    });

});
