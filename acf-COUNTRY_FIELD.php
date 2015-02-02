<?php
/*
Plugin Name: Advanced Custom Fields: Country Field
Plugin URI: https://github.com/Vheissu/acf-country-field
Description: Adds a country as well as city/state field to your Wordpress sites.
Version: 1.0.1
Author: Dwayne Charrington
Author URI: http://ilikekillnerds.com
License: GPL
*/

load_plugin_textdomain( 'acf-COUNTRY_FIELD', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );

// ACF version 5
function include_field_types_COUNTRY_FIELD( $version ) {
    include_once('acf-COUNTRY_FIELD-v5.php');
}
add_action('acf/include_field_types', 'include_field_types_COUNTRY_FIELD');

// ACF version 4
function register_fields_COUNTRY_FIELD() {
    include_once('acf-COUNTRY_FIELD-v4.php');
}
add_action('acf/register_fields', 'register_fields_COUNTRY_FIELD');    
    
?>
