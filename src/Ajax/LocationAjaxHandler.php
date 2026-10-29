<?php

declare(strict_types=1);

namespace AcfCountryField\Ajax;

use AcfCountryField\Repositories\LocationRepository;

/**
 * Serves city lists to the field's script.
 *
 * Location data is public, so the endpoint is open to logged-out visitors for front-end forms
 * made with acf_form(). The nonce stops other sites from using it.
 */
class LocationAjaxHandler
{
    public const ACTION = 'acf_country_field_cities';
    public const NONCE_ACTION = 'acf_country_field';

    private ?LocationRepository $repository;

    public function __construct(?LocationRepository $repository = null)
    {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handleGetCities']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handleGetCities']);
    }

    /**
     * Responds with the cities for `country_id`, limited to `state_id` when it's set, as a list of
     * `{id, name}` objects sorted by name. A list keeps the order, which a JSON object keyed by ID wouldn't.
     */
    public function handleGetCities(): void
    {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => __('Your session has expired. Reload the page and try again.', 'acf-country-field')], 403);
        }

        $country_id = isset($_POST['country_id']) ? absint(wp_unslash($_POST['country_id'])) : 0;
        $state_id = isset($_POST['state_id']) ? absint(wp_unslash($_POST['state_id'])) : 0;

        if ($country_id <= 0) {
            wp_send_json_error(['message' => __('Choose a country.', 'acf-country-field')], 400);
        }

        $cities = [];

        foreach ($this->repository()->getCities($country_id, $state_id) as $id => $name) {
            $cities[] = ['id' => $id, 'name' => $name];
        }

        wp_send_json_success($cities);
    }

    private function repository(): LocationRepository
    {
        return $this->repository ??= new LocationRepository();
    }
}
