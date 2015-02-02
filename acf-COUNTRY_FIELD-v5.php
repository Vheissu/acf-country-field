<?php

class acf_field_COUNTRY_FIELD extends acf_field 
{
    var $settings, $defaults;

    public function __construct()
    {
        $this->name = 'COUNTRY_FIELD';
        $this->label = __('Country');
        $this->category = __("Basic",'acf'); // Basic, Content, Choice, etc

        $this->defaults = array(
            "country_name" => '',
            "city_name"    => '',
            "state_name"   => 0,
            "country_id"   => 0,
            "city_id"      => 0,
            "state_id"     => '',
        );

        parent::__construct();

        $this->settings = array(
            'path' => apply_filters('acf/helpers/get_path', __FILE__),
            'dir' => plugin_dir_url( __FILE__ ),
            'version' => '1.0.1'
        );
    }

    public function create_options($field)
    {
        $key = $field['name'];
    }

    public function render_field( $field )
    {
        $field['value'] = isset($field['value']) ? $field['value'] : '';

        $country_id = (isset($field['value']['country_id'])) ? $field['value']['country_id'] : 446;
        $city_id    = (isset($field['value']['city_id'])) ? $field['value']['city_id'] : 0;
        $state_id   = (isset($field['value']['state_id'])) ? $field['value']['state_id'] : 0;

        $key        = $field['name'];

        global $wpdb;

        $countries  = $this->_acf_get_countries();
        $cities     = $this->_acf_get_cities($country_id);

        // Only applies when United States is selected as a country
        $states = array();

        // If we have selected USA
        if ($country_id === 446)
        {
            $states_db = $wpdb->get_results("SELECT DISTINCT * FROM ".$wpdb->prefix."states ORDER BY state ASC");

            foreach ($states_db AS $state)
            {
                if (trim($state->state) === '') 
                {
                    continue;
                }

                $states[$state->id] = $state->state;
            }
        }
        ?>

            <?php $country_field = $field['name'] . '[country_id]'; ?>
            <ul class="country-selector-list">
                <li id="field-<?php echo $key; ?>[country_id]">
                    <div class="field-inner">
                        <strong><?php _e("Select your country", 'acf'); ?></strong><br />
                        <select name="<?= $country_field; ?>">
                            <option value="">Choose a country...</option>
                            <?php foreach ($countries AS $ID => $country): ?>
                                <option value="<?= $ID; ?>"<?php if ($country_id === $ID): ?>selected<?php endif; ?>><?= $country; ?></option>
                            <?php endforeach; ?>
                        </select>

                    </div>
                </li>
                <li id="field-<?php echo $key; ?>[city_id]">
                    <div class="css3-loader" style="display:none;"><div class="css3-spinner"></div></div>
                    <div class="field-inner">
                        <?php $city_field = $field['name'] . '[city_id]'; ?>
                        <strong><?php _e("Select your city", 'acf'); ?></strong><br />
                        <select name="<?= $city_field; ?>">
                            <option value="">Select your city...</option>
                            <?php foreach ($cities AS $ID => $city): ?>
                                <option value="<?= $ID; ?>"<?php if ($city_id === $ID): ?>selected<?php endif; ?>><?= $city; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </li>
                <li id="field-<?php echo $key; ?>[state_id]" <?php if (empty($states)): ?>style="display:none;"<?php endif; ?>>
                    <div class="css3-loader" style="display:none;"><div class="css3-spinner"></div></div>
                    <div class="field-inner">
                        <?php $state_field = $field['name'] . '[state_id]'; ?>
                        <strong><?php _e("Select your state", 'acf'); ?></strong><br />
                        <select name="<?= $state_field; ?>">
                            <option value="">Select your state...</option>
                            <?php foreach ($states AS $ID => $state): ?>
                                <option value="<?= $ID; ?>"<?php if ($state_id === $ID): ?>selected<?php endif; ?>><?= $state; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </li>
            </ul>

        <?php
    }

    public function input_admin_enqueue_scripts()
    {
        wp_register_script('acf-input-country', $this->settings['dir'] . 'js/input.js', array('acf-input'), $this->settings['version']);
        wp_register_script('acf-input-chosen', $this->settings['dir'] . 'js/chosen.jquery.min.js', array('jquery'), $this->settings['version']);
        wp_register_style('acf-input-country', $this->settings['dir'] . 'css/input.css', array('acf-input'), $this->settings['version']);
        wp_register_style('acf-input-chosen', $this->settings['dir'] . 'css/chosen.min.css', array(), $this->settings['version']);

        wp_localize_script( 'acf-input-country', "acfCountry", array(
            "ajaxurl" => admin_url("admin-ajax.php"),
        ) );


        // scripts
        wp_enqueue_script(array(
            'acf-input-country',
            'acf-input-chosen',
        ));

        // styles
        wp_enqueue_style(array(
            'acf-input-country',
            'acf-input-chosen',
        ));
    }

    public function update_value($value, $post_id, $field)
    {
        $value['country_name'] = $this->_acf_get_country($value['country_id']);
        $value['city_name']    = $this->_acf_get_city($value['city_id']);
        $value['state_name']   = (isset($value['state_id']) && $value['state_id'] !== 0) ? $this->_acf_get_state($value['state_id']) : '';

        return $value;
    }

    public function format_value($value, $post_id, $field)
    {
        $old_values = $value;
        $value      = array();

        $value['country_name'] = $this->_acf_get_country($old_values['country_id']);
        $value['city_name']    = $this->_acf_get_city($old_values['city_id']);
        $value['state_name']   = (isset($old_values['state_id']) && $old_values['state_id'] !== 0) ? $this->_acf_get_state($old_values['state_id']) : '';

        return $value;
    }

    /**
     * Get Countries
     *
     * Get all countries from the database
     *
     */
    public function _acf_get_countries()
    {
        global $wpdb;
        $countries_db = $wpdb->get_results("SELECT DISTINCT * FROM ".$wpdb->prefix."countries ORDER BY country ASC");

        $countries = array();

        foreach ($countries_db AS $country)
        {
            if (trim($country->country) == '') continue;
            $countries[$country->id] = $country->country;
        }

        return $countries;
    }

    /**
     * Get Country
     *
     * Get a particular country from the database
     *
     */
    public function _acf_get_country($country_id)
    {
        global $wpdb;
        $country = $wpdb->get_row("SELECT DISTINCT * FROM ".$wpdb->prefix."countries WHERE id = '".$country_id."'");

        if ($country)
        {
            return $country->country;
        }
        else
        {
            return false;
        }
    }

    /**
     * Get Cities
     *
     * Get all cities for a particular country
     *
     */
    public function _acf_get_cities($country_id)
    {
        global $wpdb;
        $cities_db = $wpdb->get_results("SELECT DISTINCT * FROM ".$wpdb->prefix."cities WHERE country='".$country_id."' ORDER BY city ASC");

        $cities = array(); 

        foreach ($cities_db AS $city)
        {
            if (trim($city->city) == '') continue;
            $cities[$city->id] = $city->city;
        }

        return $cities;
    }

    /**
     * Get City
     *
     * Get a particular city based on its ID
     *
     */
    public function _acf_get_city($city_id)
    {
        global $wpdb;
        $city = $wpdb->get_row("SELECT DISTINCT * FROM ".$wpdb->prefix."cities WHERE id = '".$city_id."'");

        if ($city)
        {
            return $city->city;
        }
        else
        {
            return false;
        }
    }

    /**
     * Get State
     *
     * Get a particular state based on its ID
     *
     */
    public function _acf_get_state($state_id)
    {
        global $wpdb;
        $state = $wpdb->get_row("SELECT DISTINCT * FROM ".$wpdb->prefix."states WHERE id = '".$state_id."'");

        if ($state) {
            return $state->state;
        } else {
            return false;
        }
    }
}

    add_action('wp_ajax_get_country_cities', 'get_country_cities');
    function get_country_cities()
    {
        global $wpdb;

        $country_id = (int) trim($_POST['countryId']);

        $cities_db = $wpdb->get_results("SELECT DISTINCT * FROM ".$wpdb->prefix."cities WHERE country='".$country_id."' ORDER BY city ASC");
        $cities = array();

        if ($cities_db)
        {
            foreach ($cities_db AS $city)
            {
                $cities[$city->id] = $city->city;
            }
        }

        echo json_encode($cities);

        die();
    }

    add_action("wp_ajax_get_us_states", "get_us_states");
    function get_us_states()
    {
        global $wpdb;

        $states_db = $wpdb->get_results("SELECT DISTINCT * FROM ".$wpdb->prefix."states ORDER BY state ASC");
        $states = array();

        if ($states_db)
        {
            foreach ($states_db AS $state)
            {
                $states[$state->id] = $state->state;
            }
        }

        echo json_encode($states);

        die();
    }

    // Create our v4 field
    new acf_field_COUNTRY_FIELD();
