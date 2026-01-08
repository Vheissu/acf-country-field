<?php

declare(strict_types=1);

use AcfCountryField\Ajax\LocationAjaxHandler;
use AcfCountryField\Repositories\LocationRepository;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->repository = Mockery::mock(LocationRepository::class);
    $this->handler = new LocationAjaxHandler($this->repository);
});

describe('LocationAjaxHandler', function (): void {

    describe('createNonce', function (): void {

        it('creates a nonce using WordPress function', function (): void {
            $nonce = LocationAjaxHandler::createNonce();

            expect($nonce)->toBeString()
                ->and($nonce)->toBe('test_nonce_acf_country_field_nonce');
        });

    });

    describe('handleGetCitiesLegacy', function (): void {

        it('returns cities for valid country ID', function (): void {
            $_POST['countryId'] = '446';

            $expectedCities = [
                100 => 'New York',
                101 => 'Los Angeles',
            ];

            $this->repository->shouldReceive('getCitiesByCountry')
                ->once()
                ->with(446)
                ->andReturn($expectedCities);

            // Capture JSON output
            try {
                $this->handler->handleGetCitiesLegacy();
            } catch (\Exception $e) {
                $message = $e->getMessage();
                expect($message)->toContain('wp_send_json');
                expect($message)->toContain('New York');
                expect($message)->toContain('Los Angeles');
            }
        });

        it('returns empty array for invalid country ID', function (): void {
            $_POST['countryId'] = '0';

            $this->repository->shouldReceive('getCitiesByCountry')
                ->once()
                ->with(0)
                ->andReturn([]);

            try {
                $this->handler->handleGetCitiesLegacy();
            } catch (\Exception $e) {
                expect($e->getMessage())->toContain('wp_send_json: []');
            }
        });

        it('sanitizes country ID input', function (): void {
            $_POST['countryId'] = '446abc'; // Should be sanitized to 446

            $this->repository->shouldReceive('getCitiesByCountry')
                ->once()
                ->with(446)
                ->andReturn(['100' => 'Test City']);

            try {
                $this->handler->handleGetCitiesLegacy();
            } catch (\Exception $e) {
                expect($e->getMessage())->toContain('Test City');
            }
        });

    });

    describe('handleGetStatesLegacy', function (): void {

        it('returns all US states', function (): void {
            $expectedStates = [
                1 => 'Alabama',
                5 => 'California',
                33 => 'New York',
            ];

            $this->repository->shouldReceive('getStates')
                ->once()
                ->andReturn($expectedStates);

            try {
                $this->handler->handleGetStatesLegacy();
            } catch (\Exception $e) {
                $message = $e->getMessage();
                expect($message)->toContain('wp_send_json');
                expect($message)->toContain('Alabama');
                expect($message)->toContain('California');
                expect($message)->toContain('New York');
            }
        });

        it('returns empty array when no states exist', function (): void {
            $this->repository->shouldReceive('getStates')
                ->once()
                ->andReturn([]);

            try {
                $this->handler->handleGetStatesLegacy();
            } catch (\Exception $e) {
                expect($e->getMessage())->toContain('wp_send_json: []');
            }
        });

    });

    describe('constants', function (): void {

        it('has correct nonce action constant', function (): void {
            expect(LocationAjaxHandler::NONCE_ACTION)->toBe('acf_country_field_nonce');
        });

        it('has correct nonce key constant', function (): void {
            expect(LocationAjaxHandler::NONCE_KEY)->toBe('acf_country_nonce');
        });

    });

});

afterEach(function (): void {
    unset($_POST['countryId'], $_POST['country_id']);
});
