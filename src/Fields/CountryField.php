<?php

declare(strict_types=1);

namespace AcfCountryField\Fields;

use AcfCountryField\Ajax\LocationAjaxHandler;
use AcfCountryField\Plugin;
use AcfCountryField\Repositories\LocationRepository;

/**
 * ACF field type for picking a country, then a city. US addresses also get a state, which
 * narrows the city list.
 *
 * The stored value keeps the names next to the IDs, so templates don't need the location tables.
 *
 * @phpstan-type Location array{
 *     country_id: int,
 *     country_name: string,
 *     state_id: int,
 *     state_name: string,
 *     city_id: int,
 *     city_name: string
 * }
 */
class CountryField extends \acf_field
{
    public const TYPE = 'country_field';

    private const TEXT_DOMAIN = 'acf-country-field';
    private const HANDLE = 'acf-country-field';

    private string $plugin_file;
    private LocationRepository $repository;

    public function __construct(string $plugin_file, ?LocationRepository $repository = null)
    {
        $this->plugin_file = $plugin_file;
        $this->repository = $repository ?? new LocationRepository();

        parent::__construct();
    }

    /**
     * Set up the field type. Called by the acf_field constructor.
     */
    public function initialize(): void
    {
        $this->name = static::TYPE;
        $this->label = __('Country', self::TEXT_DOMAIN);
        $this->category = 'choice';
        $this->description = __('Select a country and city. US addresses also get a state.', self::TEXT_DOMAIN);
        $this->defaults = [
            'default_country' => LocationRepository::USA_COUNTRY_ID,
            'show_state' => 1,
            'show_city' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $field
     */
    public function render_field_settings(array $field): void
    {
        acf_render_field_setting($field, [
            'label' => __('Default Country', self::TEXT_DOMAIN),
            'instructions' => __('Selected when the field has no value yet.', self::TEXT_DOMAIN),
            'type' => 'select',
            'name' => 'default_country',
            'choices' => $this->repository->getCountries(),
            'allow_null' => 1,
            'ui' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Show State', self::TEXT_DOMAIN),
            'instructions' => __('Shown for the United States. The state narrows the city list.', self::TEXT_DOMAIN),
            'type' => 'true_false',
            'name' => 'show_state',
            'ui' => 1,
        ]);

        acf_render_field_setting($field, [
            'label' => __('Show City', self::TEXT_DOMAIN),
            'type' => 'true_false',
            'name' => 'show_city',
            'ui' => 1,
        ]);
    }

    /**
     * @param array<string, mixed> $field
     */
    public function render_field(array $field): void
    {
        $show_state = !empty($field['show_state']);
        $show_city = !empty($field['show_city']);

        if (is_array($field['value'] ?? null)) {
            $location = $this->normalize($field['value']);
        } else {
            $location = $this->normalize(['country_id' => $field['default_country'] ?? 0]);
        }

        $country_id = $location['country_id'];
        $state_id = $location['state_id'];
        $is_usa = $this->repository->isUnitedStates($country_id);
        $filter_by_state = $show_state && $is_usa;

        // Version 1 saved US cities without linking them to the state, so take the state from the city.
        if ($filter_by_state && $show_city && $location['city_id'] > 0) {
            $city = $this->repository->getCity($location['city_id']);

            if ($city !== null && $city['country_id'] === $country_id && $city['state_id'] > 0) {
                $state_id = $city['state_id'];
            }
        }

        $cities = [];

        if ($show_city && $country_id > 0 && (!$filter_by_state || $state_id > 0)) {
            $cities = $this->repository->getCities($country_id, $filter_by_state ? $state_id : 0);
        }

        if ($filter_by_state && $state_id === 0) {
            $city_placeholder = __('Select a state first', self::TEXT_DOMAIN);
        } else {
            $city_placeholder = __('Select a city', self::TEXT_DOMAIN);
        }

        $id = (string) $field['id'];
        $name = (string) $field['name'];

        echo '<div class="acf-country-field">';

        $this->renderSelect(
            'country',
            __('Country', self::TEXT_DOMAIN),
            $id,
            $name,
            $this->repository->getCountries(),
            $country_id,
            __('Select a country', self::TEXT_DOMAIN)
        );

        if ($show_state) {
            $this->renderSelect(
                'state',
                __('State', self::TEXT_DOMAIN),
                $id,
                $name,
                $is_usa ? $this->repository->getStates() : [],
                $state_id,
                __('Select a state', self::TEXT_DOMAIN),
                !$is_usa
            );
        }

        if ($show_city) {
            $this->renderSelect(
                'city',
                __('City', self::TEXT_DOMAIN),
                $id,
                $name,
                $cities,
                $location['city_id'],
                $city_placeholder
            );
        }

        echo '</div>';
    }

    /**
     * @param array<int, string> $choices
     */
    private function renderSelect(
        string $part,
        string $label,
        string $field_id,
        string $field_name,
        array $choices,
        int $selected,
        string $placeholder,
        bool $hidden = false
    ): void {
        $id = $field_id . '-' . $part;

        printf(
            '<div class="acf-country-field__part" data-part="%s"%s><label for="%s">%s</label><select id="%s" name="%s"%s>',
            esc_attr($part),
            $hidden ? ' hidden' : '',
            esc_attr($id),
            esc_html($label),
            esc_attr($id),
            esc_attr($field_name . '[' . $part . '_id]'),
            $choices === [] && $part !== 'country' ? ' disabled' : ''
        );

        printf('<option value="">%s</option>', esc_html($placeholder));

        foreach ($choices as $value => $text) {
            printf(
                '<option value="%d"%s>%s</option>',
                $value,
                $value === $selected ? ' selected' : '',
                esc_html($text)
            );
        }

        echo '</select></div>';
    }

    public function input_admin_enqueue_scripts(): void
    {
        // The legacy field type shares these assets.
        if (wp_script_is(self::HANDLE, 'enqueued')) {
            return;
        }

        $url = plugin_dir_url($this->plugin_file);

        wp_enqueue_style(self::HANDLE, $url . 'css/input.css', ['acf-input'], Plugin::VERSION);
        wp_enqueue_script(self::HANDLE, $url . 'js/input.js', ['acf-input', 'jquery'], Plugin::VERSION, true);

        $states = [];

        foreach ($this->repository->getStates() as $id => $name) {
            $states[] = ['id' => $id, 'name' => $name];
        }

        $config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => LocationAjaxHandler::ACTION,
            'nonce' => wp_create_nonce(LocationAjaxHandler::NONCE_ACTION),
            'usaId' => LocationRepository::USA_COUNTRY_ID,
            'states' => $states,
            'i18n' => [
                'selectState' => __('Select a state', self::TEXT_DOMAIN),
                'selectStateFirst' => __('Select a state first', self::TEXT_DOMAIN),
                'selectCity' => __('Select a city', self::TEXT_DOMAIN),
                'loading' => __('Loading…', self::TEXT_DOMAIN),
                'loadFailed' => __('Couldn’t load cities', self::TEXT_DOMAIN),
            ],
        ];

        wp_add_inline_script(self::HANDLE, 'window.acfCountryField = ' . wp_json_encode($config) . ';', 'before');
    }

    /**
     * Fixes IDs in values saved by version 1, using the names stored with them. Version 1.0.1
     * numbered countries differently, and version 1 listed some states twice.
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return mixed
     */
    public function load_value($value, $post_id, array $field)
    {
        if (!is_array($value)) {
            return $value;
        }

        $country_id = $this->toId($value['country_id'] ?? 0);
        $country_name = $this->toName($value['country_name'] ?? '');

        if ($country_id > 0 && $country_name !== '' && $this->repository->getCountry($country_id) !== $country_name) {
            $value['country_id'] = $this->repository->findCountryId($country_name) ?? $country_id;
        }

        $state_id = $this->toId($value['state_id'] ?? 0);
        $state_name = $this->toName($value['state_name'] ?? '');

        if ($state_id > 0 && $state_name !== '' && $this->repository->getState($state_id) !== $state_name) {
            $value['state_id'] = $this->repository->findStateId($state_name) ?? $state_id;
        }

        return $value;
    }

    /**
     * Saves the IDs with their names, dropping a state or city that doesn't belong to the country.
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return Location
     */
    public function update_value($value, $post_id, array $field): array
    {
        $value = is_array($value) ? $value : [];

        $country_id = $this->toId($value['country_id'] ?? 0);
        $state_id = $this->toId($value['state_id'] ?? 0);
        $city_id = $this->toId($value['city_id'] ?? 0);

        $country_name = $this->repository->getCountry($country_id);

        if ($country_name === null) {
            return $this->normalize([]);
        }

        $city = $this->repository->getCity($city_id);

        if ($city === null || $city['country_id'] !== $country_id) {
            $city = null;
        }

        if (!$this->repository->isUnitedStates($country_id)) {
            $state_id = 0;
        } elseif ($city !== null && $city['state_id'] > 0) {
            // A US city belongs to one state.
            $state_id = $city['state_id'];
        }

        $state_name = $state_id > 0 ? $this->repository->getState($state_id) : null;

        return [
            'country_id' => $country_id,
            'country_name' => $country_name,
            'state_id' => $state_name === null ? 0 : $state_id,
            'state_name' => $state_name ?? '',
            'city_id' => $city === null ? 0 : $city['id'],
            'city_name' => $city === null ? '' : $city['name'],
        ];
    }

    /**
     * Returns the saved location, or null when the field has never been saved.
     *
     * @param mixed $value
     * @param int|string $post_id
     * @param array<string, mixed> $field
     * @return Location|null
     */
    public function format_value($value, $post_id, array $field): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $location = $this->normalize($value);

        // Values saved by version 1 can be missing names.
        if ($location['country_name'] === '' && $location['country_id'] > 0) {
            $location['country_name'] = $this->repository->getCountry($location['country_id']) ?? '';
        }

        if ($location['state_name'] === '' && $location['state_id'] > 0) {
            $location['state_name'] = $this->repository->getState($location['state_id']) ?? '';
        }

        if ($location['city_name'] === '' && $location['city_id'] > 0) {
            $location['city_name'] = $this->repository->getCity($location['city_id'])['name'] ?? '';
        }

        return $location;
    }

    /**
     * @param bool|string $valid
     * @param mixed $value
     * @param array<string, mixed> $field
     * @param string $input
     * @return bool|string
     */
    public function validate_value($valid, $value, array $field, $input)
    {
        if ($valid !== true) {
            return $valid;
        }

        if (!empty($field['required']) && (!is_array($value) || $this->toId($value['country_id'] ?? 0) === 0)) {
            return __('Select a country.', self::TEXT_DOMAIN);
        }

        return $valid;
    }

    /**
     * @param array<mixed> $value
     * @return Location
     */
    private function normalize(array $value): array
    {
        return [
            'country_id' => $this->toId($value['country_id'] ?? 0),
            'country_name' => $this->toName($value['country_name'] ?? ''),
            'state_id' => $this->toId($value['state_id'] ?? 0),
            'state_name' => $this->toName($value['state_name'] ?? ''),
            'city_id' => $this->toId($value['city_id'] ?? 0),
            'city_name' => $this->toName($value['city_name'] ?? ''),
        ];
    }

    /**
     * @param mixed $value
     */
    private function toId($value): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    /**
     * Version 1 stored false for names it couldn't find.
     *
     * @param mixed $value
     */
    private function toName($value): string
    {
        return is_string($value) ? $value : '';
    }
}
