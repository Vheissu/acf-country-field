<?php

declare(strict_types=1);

use AcfCountryField\Ajax\LocationAjaxHandler;
use AcfCountryField\Repositories\LocationRepository;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    $_POST = [];
    $this->repository = Mockery::mock(LocationRepository::class);
    $this->handler = new LocationAjaxHandler($this->repository);
    $this->response = null;

    Functions\when('check_ajax_referer')->justReturn(1);

    // The real functions end the request.
    Functions\when('wp_send_json_success')->alias(function ($data): void {
        $this->response = ['success' => true, 'data' => $data];

        throw new RuntimeException('exit');
    });
    Functions\when('wp_send_json_error')->alias(function ($data, $status): void {
        $this->response = ['success' => false, 'data' => $data, 'status' => $status];

        throw new RuntimeException('exit');
    });
});

afterEach(function (): void {
    $_POST = [];
});

function respond(LocationAjaxHandler $handler): void
{
    try {
        $handler->handleGetCities();
    } catch (RuntimeException $exit) {
        expect($exit->getMessage())->toBe('exit');
    }
}

it('registers the action for logged-in and logged-out users', function (): void {
    Actions\expectAdded('wp_ajax_acf_country_field_cities')->once()->with([$this->handler, 'handleGetCities']);
    Actions\expectAdded('wp_ajax_nopriv_acf_country_field_cities')->once()->with([$this->handler, 'handleGetCities']);

    $this->handler->register();
});

it('returns cities as an ordered list', function (): void {
    $_POST = ['country_id' => '248', 'state_id' => ''];
    $this->repository->shouldReceive('getCities')->once()->with(248, 0)->andReturn([1060 => 'Adelaide', 1460 => 'Sydney']);

    respond($this->handler);

    expect($this->response)->toBe([
        'success' => true,
        'data' => [['id' => 1060, 'name' => 'Adelaide'], ['id' => 1460, 'name' => 'Sydney']],
    ]);
});

it('passes the state through', function (): void {
    $_POST = ['country_id' => '446', 'state_id' => '44'];
    $this->repository->shouldReceive('getCities')->once()->with(446, 44)->andReturn([]);

    respond($this->handler);

    expect($this->response)->toBe(['success' => true, 'data' => []]);
});

it('rejects requests without a valid nonce', function (): void {
    Functions\when('check_ajax_referer')->justReturn(false);
    $_POST = ['country_id' => '248'];
    $this->repository->shouldNotReceive('getCities');

    respond($this->handler);

    expect($this->response['success'])->toBeFalse()
        ->and($this->response['status'])->toBe(403);
});

it('rejects requests without a country', function (): void {
    $_POST = ['country_id' => 'abc'];
    $this->repository->shouldNotReceive('getCities');

    respond($this->handler);

    expect($this->response['success'])->toBeFalse()
        ->and($this->response['status'])->toBe(400);
});
