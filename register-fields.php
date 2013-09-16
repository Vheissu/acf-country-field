<?php

class acf_field_country extends acf_field
{
    // vars
    var $settings, // will hold info such as dir / path
        $defaults; // will hold default field options


    /*
    *  __construct
    *
    *  Set name / label needed for actions / filters
    *
    *  @since   3.6
    *  @date    23/01/13
    */

    function __construct()
    {
        // vars
        $this->name = 'country';
        $this->label = __('Country');
        $this->category = __("Basic",'acf'); // Basic, Content, Choice, etc

        $this->defaults = array(
            "country_name" => 0,
            "country_city"     => 0,
            "country_state"  => "0",
        );


        // do not delete!
    parent::__construct();


    // settings
        $this->settings = array(
            'path' => apply_filters('acf/helpers/get_path', __FILE__),
            'dir' => apply_filters('acf/helpers/get_dir', __FILE__),
            'version' => '1.0.0'
        );

    }


    /*
    *  create_options()
    *
    *  Create extra options for your field. This is rendered when editing a field.
    *  The value of $field['name'] can be used (like bellow) to save extra data to the $field
    *
    *  @type    action
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $field  - an array holding all the field's data
    */

    function create_options($field)
    {
        $field = array_merge($this->defaults, $field);
        ?>

        <?php

    }


    /*
    *  create_field()
    *
    *  Create the HTML interface for your field
    *
    *  @param   $field - an array holding all the field's data
    *
    *  @type    action
    *  @since   3.6
    *  @date    23/01/13
    */

    function create_field( $field )
    {
        $field = array_merge($this->defaults, $field);

        $key             = $field['name'];
        $country_id = $field['value']['country_name'];
        $city_id        = $field['value']['country_city'];
        $state_id      = (isset($field['value']['country_state'])) ? $field['value']['country_state'] : $field['country_state'];

        global $wpdb;

        $countries_db = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."countries ORDER BY country ASC");
        $cities_db        = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."cities WHERE  country='".$country_id."' ORDER BY city ASC");

        $countries        = array();
        $cities               = array();

        // Only applies when United States is selected as a country
        $states             = array();

        $cities[0] = "";

        foreach ($countries_db AS $country)
        {
            if (trim($country->country) == '') continue;
            $countries[$country->id] = $country->country;
        }

        foreach ($cities_db AS $city)
        {
            if (trim($city->city) == '') continue;
            $cities[$city->id] = $city->city;
        }

        // If we have selected USA
        if ($country_id == 446)
        {
            $states_db = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."states  ORDER BY state ASC");

            foreach ($states_db AS $state)
            {
                if (trim($state->state) == '') continue;
                $states[$state->id] = $state->state;
            }
        }
        ?>

            <ul class="country-selector-list">
                <li id="field-<?php echo $key; ?>[country_name]">
                    <strong><?php _e("Select your country", 'acf'); ?></strong><br />

                    <?php

                    $country_field = $field['name'] . '[country_name]';
                    do_action('acf/create_field', array(
                        'type'      =>  'select',
                        'name'    =>  $country_field,
                        'value'     =>  $country_id,
                        'choices' =>  $countries,
                    ));

                    ?>
                </li>
                <li id="field-<?php echo $key; ?>[country_city]">
                    <strong><?php _e("Select your city", 'acf'); ?></strong><br />

                    <?php

                    $city_field = $field['name'] . '[country_city]';
                    do_action('acf/create_field', array(
                        'type'      =>  'select',
                        'name'    =>  $city_field,
                        'value'     =>  $city_id,
                        'choices' =>  $cities,
                    ));

                    ?>
                </li>
                <li id="field-<?php echo $key; ?>[country_state]" <?php if (empty($states)): ?>style="display:none;"<?php endif; ?>>
                    <strong><?php _e("Select your state", 'acf'); ?></strong><br />

                    <?php

                    $state_field = $field['name'] . '[country_state]';
                    do_action('acf/create_field', array(
                        'type'      =>  'select',
                        'name'    =>  $state_field,
                        'value'     =>  $state_id,
                        'choices' =>  $states,
                    ));

                    ?>
                </li>
            </ul>

        <?php
    }


    /*
    *  input_admin_enqueue_scripts()
    *
    *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
    *  Use this action to add css + javascript to assist your create_field() action.
    *
    *  $info    http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
    *  @type    action
    *  @since   3.6
    *  @date    23/01/13
    */

    function input_admin_enqueue_scripts()
    {
        // Note: This function can be removed if not used

        // register acf scripts
        wp_register_script('acf-input-country', $this->settings['dir'] . 'js/input.js', array('acf-input'), $this->settings['version']);
        wp_register_style('acf-input-country', $this->settings['dir'] . 'css/input.css', array('acf-input'), $this->settings['version']);

        wp_localize_script( 'acf-input-country', "acfCountry", array(
            "ajaxurl" => admin_url("admin-ajax.php"),
        ) );


        // scripts
        wp_enqueue_script(array(
            'acf-input-country',
        ));

        // styles
        wp_enqueue_style(array(
            'acf-input-country',
        ));

    }


    /*
    *  load_value()
    *
    *  This filter is appied to the $value after it is loaded from the db
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value - the value found in the database
    *  @param   $post_id - the $post_id from which the value was loaded from
    *  @param   $field - the field array holding all the field options
    *
    *  @return  $value - the value to be saved in te database
    */

    function load_value($value, $post_id, $field)
    {
        // Note: This function can be removed if not used
        return $value;
    }


    /*
    *  update_value()
    *
    *  This filter is appied to the $value before it is updated in the db
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value - the value which will be saved in the database
    *  @param   $post_id - the $post_id of which the value will be saved
    *  @param   $field - the field array holding all the field options
    *
    *  @return  $value - the modified value
    */

    function update_value($value, $post_id, $field)
    {
        // Note: This function can be removed if not used
        return $value;
    }


    /*
    *  format_value()
    *
    *  This filter is appied to the $value after it is loaded from the db and before it is passed to the create_field action
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value  - the value which was loaded from the database
    *  @param   $post_id - the $post_id from which the value was loaded
    *  @param   $field  - the field array holding all the field options
    *
    *  @return  $value  - the modified value
    */

    function format_value($value, $post_id, $field)
    {
        $field = array_merge($this->defaults, $field);

        // perhaps use $field['preview_size'] to alter the $value?


        // Note: This function can be removed if not used
        return $value;
    }


    /*
    *  format_value_for_api()
    *
    *  This filter is appied to the $value after it is loaded from the db and before it is passed back to the api functions such as the_field
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value  - the value which was loaded from the database
    *  @param   $post_id - the $post_id from which the value was loaded
    *  @param   $field  - the field array holding all the field options
    *
    *  @return  $value  - the modified value
    */

    function format_value_for_api($value, $post_id, $field)
    {
        $field = array_merge($this->defaults, $field);

        // perhaps use $field['preview_size'] to alter the $value?


        // Note: This function can be removed if not used
        return $value;
    }


    /*
    *  load_field()
    *
    *  This filter is appied to the $field after it is loaded from the database
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $field - the field array holding all the field options
    *
    *  @return  $field - the field array holding all the field options
    */

    function load_field($field)
    {
        // Note: This function can be removed if not used
        return $field;
    }


    /*
    *  update_field()
    *
    *  This filter is appied to the $field before it is saved to the database
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $field - the field array holding all the field options
    *  @param   $post_id - the field group ID (post_type = acf)
    *
    *  @return  $field - the modified field
    */

    function update_field($field, $post_id)
    {
        // Note: This function can be removed if not used
        return $field;
    }


}

add_action("wp_ajax_get_country_cities", "get_country_cities");
function get_country_cities()
{
    global $wpdb;

    $country_id = (int) trim($_POST['countryId']);

    $cities_db        = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."cities WHERE  country='".$country_id."' ORDER BY city ASC");
    $cities               = array();

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

    $states_db        = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."states ORDER BY state ASC");
    $states               = array();

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


// create field
new acf_field_country();

?>
