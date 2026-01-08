<?php

declare(strict_types=1);

namespace AcfCountryField\Fields;

use AcfCountryField\Ajax\LocationAjaxHandler;
use AcfCountryField\Repositories\LocationRepository;

/**
 * ACF Country Field implementation.
 *
 * Provides a compound field for selecting country, city, and state (US only).
 */
class CountryField extends \acf_field
{
    /**
     * Field settings.
     *
     * @var array<string, mixed>
     */
    public array $settings;

    /**
     * Default field values.
     *
     * @var array<string, mixed>
     */
    public array $defaults;

    private LocationRepository $repository;

    public function __construct(?LocationRepository $repository = null)
    {
        $this->repository = $repository ?? new LocationRepository();

        $this->name = 'country_field';
        $this->label = __('Country', 'acf-country-field');
        $this->category = 'basic';

        $this->defaults = [
            'country_id' => 0,
            'country_name' => '',
            'city_id' => 0,
            'city_name' => '',
            'state_id' => 0,
            'state_name' => '',
            'default_country' => LocationRepository::USA_COUNTRY_ID,
            'show_city' => true,
            'show_state' => true,
        ];

        $this->settings = [
            'path' => plugin_dir_path(__DIR__ . '/../'),
            'dir' => plugin_dir_url(__DIR__ . '/../'),
            'version' => '2.0.0',
        ];

        parent::__construct();
    }

    /**
     * Render field settings in the ACF field group editor.
     *
     * @param array<string, mixed> $field The field configuration
     */
    public function render_field_settings(array $field): void
    {
        // Default country setting
        acf_render_field_setting($field, [
            'label' => __('Default Country', 'acf-country-field'),
            'instructions' => __('Select the default country for new fields', 'acf-country-field'),
            'type' => 'select',
            'name' => 'default_country',
            'choices' => $this->repository->getCountries(),
            'default_value' => LocationRepository::USA_COUNTRY_ID,
        ]);

        // Show city setting
        acf_render_field_setting($field, [
            'label' => __('Show City Field', 'acf-country-field'),
            'instructions' => __('Display the city selection field', 'acf-country-field'),
            'type' => 'true_false',
            'name' => 'show_city',
            'ui' => 1,
            'default_value' => 1,
        ]);

        // Show state setting
        acf_render_field_setting($field, [
            'label' => __('Show State Field', 'acf-country-field'),
            'instructions' => __('Display the state field (US only)', 'acf-country-field'),
            'type' => 'true_false',
            'name' => 'show_state',
            'ui' => 1,
            'default_value' => 1,
        ]);
    }

    /**
     * Render the field input.
     *
     * @param array<string, mixed> $field The field configuration and value
     */
    public function render_field(array $field): void
    {
        $value = $field['value'] ?? [];

        $country_id = (int) ($value['country_id'] ?? $field['default_country'] ?? LocationRepository::USA_COUNTRY_ID);
        $city_id = (int) ($value['city_id'] ?? 0);
        $state_id = (int) ($value['state_id'] ?? 0);

        $show_city = $field['show_city'] ?? true;
        $show_state = $field['show_state'] ?? true;

        $countries = $this->repository->getCountries();
        $cities = $show_city ? $this->repository->getCitiesByCountry($country_id) : [];
        $states = ($show_state && $this->repository->isUnitedStates($country_id))
            ? $this->repository->getStates()
            : [];

        $field_name = esc_attr($field['name']);
        ?>
        <div class="acf-country-field-wrapper" data-usa-id="<?php echo esc_attr((string) LocationRepository::USA_COUNTRY_ID); ?>">
            <ul class="acf-country-selector-list">
                <?php $this->renderCountrySelect($field_name, $countries, $country_id); ?>

                <?php if ($show_city): ?>
                    <?php $this->renderCitySelect($field_name, $cities, $city_id); ?>
                <?php endif; ?>

                <?php if ($show_state): ?>
                    <?php $this->renderStateSelect($field_name, $states, $state_id); ?>
                <?php endif; ?>
            </ul>
            <?php wp_nonce_field(LocationAjaxHandler::NONCE_ACTION, LocationAjaxHandler::NONCE_KEY); ?>
        </div>
        <?php
    }

    /**
     * Render the country select field.
     */
    private function renderCountrySelect(string $field_name, array $countries, int $selected_id): void
    {
        ?>
        <li class="acf-country-field-item acf-country-field-country">
            <div class="acf-field-inner">
                <label>
                    <strong><?php echo esc_html__('Select your country', 'acf-country-field'); ?></strong>
                </label>
                <select name="<?php echo esc_attr($field_name); ?>[country_id]" class="acf-country-select">
                    <option value=""><?php echo esc_html__('Choose a country...', 'acf-country-field'); ?></option>
                    <?php foreach ($countries as $id => $name): ?>
                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected($selected_id, $id); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </li>
        <?php
    }

    /**
     * Render the city select field.
     */
    private function renderCitySelect(string $field_name, array $cities, int $selected_id): void
    {
        ?>
        <li class="acf-country-field-item acf-country-field-city">
            <div class="acf-loading-indicator" style="display: none;">
                <span class="acf-spinner"></span>
            </div>
            <div class="acf-field-inner">
                <label>
                    <strong><?php echo esc_html__('Select your city', 'acf-country-field'); ?></strong>
                </label>
                <select name="<?php echo esc_attr($field_name); ?>[city_id]" class="acf-city-select">
                    <option value=""><?php echo esc_html__('Select your city...', 'acf-country-field'); ?></option>
                    <?php foreach ($cities as $id => $name): ?>
                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected($selected_id, $id); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </li>
        <?php
    }

    /**
     * Render the state select field (US only).
     */
    private function renderStateSelect(string $field_name, array $states, int $selected_id): void
    {
        $hidden = empty($states) ? 'style="display: none;"' : '';
        ?>
        <li class="acf-country-field-item acf-country-field-state" <?php echo $hidden; ?>>
            <div class="acf-loading-indicator" style="display: none;">
                <span class="acf-spinner"></span>
            </div>
            <div class="acf-field-inner">
                <label>
                    <strong><?php echo esc_html__('Select your state', 'acf-country-field'); ?></strong>
                </label>
                <select name="<?php echo esc_attr($field_name); ?>[state_id]" class="acf-state-select">
                    <option value=""><?php echo esc_html__('Select your state...', 'acf-country-field'); ?></option>
                    <?php foreach ($states as $id => $name): ?>
                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected($selected_id, $id); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </li>
        <?php
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function input_admin_enqueue_scripts(): void
    {
        $version = $this->settings['version'];
        $dir = plugin_dir_url(dirname(__DIR__) . '/acf-country-field.php');

        // Register scripts
        wp_register_script(
            'acf-country-field-input',
            $dir . 'js/input.js',
            ['acf-input', 'jquery'],
            $version,
            true
        );

        wp_register_script(
            'acf-chosen',
            $dir . 'js/chosen.jquery.min.js',
            ['jquery'],
            $version,
            true
        );

        // Register styles
        wp_register_style(
            'acf-country-field-input',
            $dir . 'css/input.css',
            ['acf-input'],
            $version
        );

        wp_register_style(
            'acf-chosen',
            $dir . 'css/chosen.min.css',
            [],
            $version
        );

        // Localize script with AJAX data and nonce
        wp_localize_script('acf-country-field-input', 'acfCountryField', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => LocationAjaxHandler::createNonce(),
            'nonceKey' => LocationAjaxHandler::NONCE_KEY,
            'usaCountryId' => LocationRepository::USA_COUNTRY_ID,
            'i18n' => [
                'chooseCountry' => __('Choose a country...', 'acf-country-field'),
                'selectCity' => __('Select your city...', 'acf-country-field'),
                'selectState' => __('Select your state...', 'acf-country-field'),
            ],
        ]);

        // Enqueue
        wp_enqueue_script('acf-country-field-input');
        wp_enqueue_script('acf-chosen');
        wp_enqueue_style('acf-country-field-input');
        wp_enqueue_style('acf-chosen');
    }

    /**
     * Prepare value for saving to database.
     *
     * @param mixed $value The field value
     * @param int|string $post_id The post ID
     * @param array<string, mixed> $field The field configuration
     * @return array<string, mixed> The prepared value
     */
    public function update_value($value, $post_id, array $field): array
    {
        if (!is_array($value)) {
            $value = [];
        }

        $country_id = (int) ($value['country_id'] ?? 0);
        $city_id = (int) ($value['city_id'] ?? 0);
        $state_id = (int) ($value['state_id'] ?? 0);

        return [
            'country_id' => $country_id,
            'country_name' => $this->repository->getCountry($country_id) ?? '',
            'city_id' => $city_id,
            'city_name' => $this->repository->getCity($city_id) ?? '',
            'state_id' => $state_id,
            'state_name' => $state_id > 0 ? ($this->repository->getState($state_id) ?? '') : '',
        ];
    }

    /**
     * Format value for frontend display.
     *
     * @param mixed $value The field value
     * @param int|string $post_id The post ID
     * @param array<string, mixed> $field The field configuration
     * @return array<string, mixed>|null The formatted value
     */
    public function format_value($value, $post_id, array $field): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        // Ensure names are populated (for backwards compatibility with old data)
        $country_id = (int) ($value['country_id'] ?? 0);
        $city_id = (int) ($value['city_id'] ?? 0);
        $state_id = (int) ($value['state_id'] ?? 0);

        return [
            'country_id' => $country_id,
            'country_name' => $value['country_name'] ?? $this->repository->getCountry($country_id) ?? '',
            'city_id' => $city_id,
            'city_name' => $value['city_name'] ?? $this->repository->getCity($city_id) ?? '',
            'state_id' => $state_id,
            'state_name' => $value['state_name'] ?? ($state_id > 0 ? $this->repository->getState($state_id) : '') ?? '',
        ];
    }

    /**
     * Validate the field value.
     *
     * @param bool|string $valid Whether the field is valid
     * @param mixed $value The field value
     * @param array<string, mixed> $field The field configuration
     * @param string $input The input name
     * @return bool|string True if valid, error message otherwise
     */
    public function validate_value($valid, $value, array $field, string $input)
    {
        if (!$valid) {
            return $valid;
        }

        // If required, ensure country is selected
        if ($field['required'] && empty($value['country_id'])) {
            return __('Please select a country', 'acf-country-field');
        }

        return $valid;
    }
}
