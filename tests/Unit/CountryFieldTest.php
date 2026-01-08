<?php

declare(strict_types=1);

use AcfCountryField\Fields\CountryField;
use AcfCountryField\Repositories\LocationRepository;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->repository = Mockery::mock(LocationRepository::class);

    // Mock the acf_field parent class if it doesn't exist
    if (!class_exists('acf_field')) {
        eval('class acf_field {
            public $name;
            public $label;
            public $category;
            public $defaults = [];
            public function __construct() {}
        }');
    }
});

describe('CountryField', function (): void {

    describe('constructor', function (): void {

        it('sets correct field name', function (): void {
            $field = new CountryField($this->repository);

            expect($field->name)->toBe('country_field');
        });

        it('sets correct label', function (): void {
            $field = new CountryField($this->repository);

            expect($field->label)->toBe('Country');
        });

        it('uses basic category', function (): void {
            $field = new CountryField($this->repository);

            expect($field->category)->toBe('basic');
        });

        it('has correct default values', function (): void {
            $field = new CountryField($this->repository);

            expect($field->defaults)->toHaveKeys([
                'country_id',
                'country_name',
                'city_id',
                'city_name',
                'state_id',
                'state_name',
                'default_country',
                'show_city',
                'show_state',
            ]);

            expect($field->defaults['default_country'])->toBe(446);
            expect($field->defaults['show_city'])->toBeTrue();
            expect($field->defaults['show_state'])->toBeTrue();
        });

    });

    describe('update_value', function (): void {

        it('populates country name from ID', function (): void {
            $this->repository->shouldReceive('getCountry')
                ->with(446)
                ->andReturn('United States');

            $this->repository->shouldReceive('getCity')
                ->with(100)
                ->andReturn('New York');

            $this->repository->shouldReceive('getState')
                ->with(5)
                ->andReturn('California');

            $field = new CountryField($this->repository);

            $value = [
                'country_id' => 446,
                'city_id' => 100,
                'state_id' => 5,
            ];

            $result = $field->update_value($value, 1, []);

            expect($result['country_name'])->toBe('United States')
                ->and($result['city_name'])->toBe('New York')
                ->and($result['state_name'])->toBe('California');
        });

        it('handles missing values gracefully', function (): void {
            $this->repository->shouldReceive('getCountry')
                ->with(0)
                ->andReturn(null);

            $this->repository->shouldReceive('getCity')
                ->with(0)
                ->andReturn(null);

            $field = new CountryField($this->repository);

            $result = $field->update_value([], 1, []);

            expect($result['country_name'])->toBe('')
                ->and($result['city_name'])->toBe('')
                ->and($result['state_name'])->toBe('');
        });

        it('handles non-array input', function (): void {
            $this->repository->shouldReceive('getCountry')
                ->with(0)
                ->andReturn(null);

            $this->repository->shouldReceive('getCity')
                ->with(0)
                ->andReturn(null);

            $field = new CountryField($this->repository);

            $result = $field->update_value(null, 1, []);

            expect($result)->toBeArray()
                ->and($result['country_id'])->toBe(0);
        });

    });

    describe('format_value', function (): void {

        it('returns null for non-array input', function (): void {
            $field = new CountryField($this->repository);

            expect($field->format_value(null, 1, []))->toBeNull()
                ->and($field->format_value('string', 1, []))->toBeNull();
        });

        it('populates names from IDs when not set', function (): void {
            $this->repository->shouldReceive('getCountry')
                ->with(446)
                ->andReturn('United States');

            $this->repository->shouldReceive('getCity')
                ->with(100)
                ->andReturn('New York');

            $this->repository->shouldReceive('getState')
                ->with(5)
                ->andReturn('California');

            $field = new CountryField($this->repository);

            $value = [
                'country_id' => 446,
                'city_id' => 100,
                'state_id' => 5,
            ];

            $result = $field->format_value($value, 1, []);

            expect($result['country_name'])->toBe('United States')
                ->and($result['city_name'])->toBe('New York')
                ->and($result['state_name'])->toBe('California');
        });

        it('preserves existing names', function (): void {
            $field = new CountryField($this->repository);

            $value = [
                'country_id' => 446,
                'country_name' => 'Existing Country',
                'city_id' => 100,
                'city_name' => 'Existing City',
                'state_id' => 5,
                'state_name' => 'Existing State',
            ];

            $result = $field->format_value($value, 1, []);

            expect($result['country_name'])->toBe('Existing Country')
                ->and($result['city_name'])->toBe('Existing City')
                ->and($result['state_name'])->toBe('Existing State');
        });

    });

    describe('validate_value', function (): void {

        it('passes validation when not required', function (): void {
            $field = new CountryField($this->repository);

            $result = $field->validate_value(true, ['country_id' => 0], ['required' => false], 'test');

            expect($result)->toBeTrue();
        });

        it('fails validation when required and empty', function (): void {
            $field = new CountryField($this->repository);

            $result = $field->validate_value(true, ['country_id' => 0], ['required' => true], 'test');

            expect($result)->toBeString()
                ->and($result)->toContain('select a country');
        });

        it('passes validation when required and filled', function (): void {
            $field = new CountryField($this->repository);

            $result = $field->validate_value(true, ['country_id' => 446], ['required' => true], 'test');

            expect($result)->toBeTrue();
        });

        it('preserves existing validation errors', function (): void {
            $field = new CountryField($this->repository);

            $result = $field->validate_value('Previous error', ['country_id' => 446], ['required' => true], 'test');

            expect($result)->toBe('Previous error');
        });

    });

});
