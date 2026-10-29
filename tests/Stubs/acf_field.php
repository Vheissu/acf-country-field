<?php

// phpcs:ignoreFile -- mirrors the shape of ACF's own class.

/**
 * Minimal stand-in for ACF's base field class.
 */
class acf_field
{
    public $name = '';
    public $label = '';
    public $category = 'basic';
    public $description = '';
    public $defaults = [];
    public $public = true;

    public function __construct()
    {
        $this->initialize();
    }

    public function initialize()
    {
    }
}
