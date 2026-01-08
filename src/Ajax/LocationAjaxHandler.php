<?php

declare(strict_types=1);

namespace AcfCountryField\Ajax;

use AcfCountryField\Repositories\LocationRepository;

/**
 * Handles AJAX requests for location data.
 *
 * Includes proper security measures:
 * - Nonce verification for CSRF protection
 * - Capability checks for authorization
 * - Input sanitization
 */
class LocationAjaxHandler
{
    public const NONCE_ACTION = 'acf_country_field_nonce';
    public const NONCE_KEY = 'acf_country_nonce';

    private LocationRepository $repository;

    public function __construct(?LocationRepository $repository = null)
    {
        $this->repository = $repository ?? new LocationRepository();
    }

    /**
     * Register AJAX action hooks.
     */
    public function register(): void
    {
        // Logged-in users only (admin area)
        add_action('wp_ajax_acf_get_country_cities', [$this, 'handleGetCities']);
        add_action('wp_ajax_acf_get_us_states', [$this, 'handleGetStates']);

        // Legacy action names for backwards compatibility
        add_action('wp_ajax_get_country_cities', [$this, 'handleGetCitiesLegacy']);
        add_action('wp_ajax_get_us_states', [$this, 'handleGetStatesLegacy']);
    }

    /**
     * Handle AJAX request to get cities for a country.
     */
    public function handleGetCities(): void
    {
        $this->verifyRequest();

        $country_id = $this->getIntParam('country_id');

        if ($country_id <= 0) {
            wp_send_json_error(['message' => 'Invalid country ID'], 400);
        }

        $cities = $this->repository->getCitiesByCountry($country_id);
        wp_send_json_success($cities);
    }

    /**
     * Handle AJAX request to get US states.
     */
    public function handleGetStates(): void
    {
        $this->verifyRequest();

        $states = $this->repository->getStates();
        wp_send_json_success($states);
    }

    /**
     * Legacy handler for backwards compatibility.
     * Uses less strict validation for older JS code.
     */
    public function handleGetCitiesLegacy(): void
    {
        // Check for legacy 'countryId' parameter name
        $country_id = isset($_POST['countryId'])
            ? absint($_POST['countryId'])
            : $this->getIntParam('country_id');

        if ($country_id <= 0) {
            wp_send_json([]);
            return;
        }

        $cities = $this->repository->getCitiesByCountry($country_id);
        wp_send_json($cities);
    }

    /**
     * Legacy handler for backwards compatibility.
     */
    public function handleGetStatesLegacy(): void
    {
        $states = $this->repository->getStates();
        wp_send_json($states);
    }

    /**
     * Verify the AJAX request is valid and authorized.
     *
     * @throws \WP_Error If verification fails
     */
    private function verifyRequest(): void
    {
        // Verify nonce
        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_KEY, false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }

        // Verify user capability
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }
    }

    /**
     * Get an integer parameter from POST data.
     *
     * @param string $key The parameter key
     * @return int The sanitized integer value
     */
    private function getIntParam(string $key): int
    {
        if (!isset($_POST[$key])) {
            return 0;
        }

        return absint($_POST[$key]);
    }

    /**
     * Create a nonce for AJAX requests.
     *
     * @return string The nonce value
     */
    public static function createNonce(): string
    {
        return wp_create_nonce(self::NONCE_ACTION);
    }
}
