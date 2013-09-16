<?php
/*
Plugin Name: Advanced Custom Fields: Country Field
Plugin URI: https://github.com/Vheissu/acf-country-field
Description: Adds a country as well as city/state field to your Wordpress sites.
Version: 1.0.0
Author: Dwayne Charrington
Author URI: http://dwaynecharrington.com
License: GPL
*/

class acf_field_country_plugin
{

    public function __construct()
    {
        $domain = "acf-country-field";
        $mofile   = trailingslashit(dirname(__File__)) . 'lang/' . $domain . '-' . get_locale() . '.mo';
        load_textdomain( $domain, $mofile );

        add_action('acf/register_fields', array($this, 'register_fields'));

        register_activation_hook( __FILE__, array($this, 'populate_db') );
        register_deactivation_hook( __FILE__, array($this, 'depopulate_db') );
    }

    public function register_fields()
    {
        include_once 'register-fields.php';
    }

    public function populate_db()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        ob_start();
        require_once "lib/install-data.php";
        $sql = ob_get_clean();
        dbDelta( $sql );
    }

    public function depopulate_db()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        ob_start();
        require_once "lib/drop-tables.php";
        $sql = ob_get_clean();
        dbDelta( $sql );
    }

}

new acf_field_country_plugin();
