# Advanced Custom Fields: Country Field

Adds a **Country** field type to Advanced Custom Fields (ACF). Editors pick a country, then a city. For the United States they also pick a state, which narrows the city list. The plugin ships with 211 countries, the 50 US states plus the District of Columbia, and about 37,700 cities.

## Features

- Country, state and city lists that update as you choose
- Searchable dropdowns using ACF's Select2
- The state narrows US cities to that state
- Optional state and city inputs, and a default country
- Saves names next to IDs, so templates don't need extra lookups
- Works in repeaters, flexible content and front-end forms made with `acf_form()`

## Requirements

- WordPress 6.2+
- PHP 8.0+
- Advanced Custom Fields 6.0+ (free or Pro)

## Installation

1. Download `acf-country-field.zip` from the [latest release](https://github.com/Vheissu/acf-country-field/releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Advanced Custom Fields: Country Field**.
4. Add a field to a field group and choose **Country** (under Choice).

Activation creates three tables, `{prefix}countries`, `{prefix}states` and `{prefix}cities`, and fills them. The plugin needs no Composer install to run. Composer is only used for development.

## Settings

| Setting | Default | Description |
| --- | --- | --- |
| Default Country | United States of America | Selected when the field has no value yet. Clear it to start empty. |
| Show State | On | Shows a state input when the United States is selected. The state narrows the city list. With it off, US addresses list every US city. |
| Show City | On | Shows the city input. |

## Usage

`get_field()` returns an array, or `null` when the field has never been saved. IDs are integers. Anything not selected is `0` or an empty string.

```php
$location = get_field('location');

if ($location && $location['country_id']) {
    echo esc_html($location['city_name'] . ', ' . $location['country_name']);
}
```

```php
[
    'country_id' => 446,
    'country_name' => 'United States of America',
    'state_id' => 49,
    'state_name' => 'Texas',
    'city_id' => 35988,
    'city_name' => 'Austin',
]
```

To save a value from code, pass the IDs. The plugin looks up the names, drops a city that isn't in the country, and takes a US state from the city:

```php
update_field('location', ['country_id' => 248, 'city_id' => 1460], $post_id); // Sydney, Australia
```

## Upgrading from 1.x

- Fields created with 1.x (type `COUNTRY_FIELD`) keep working. They show as **Country** in the field group editor, and saving the group stores the new type name.
- Version 1.0.1 filled the country table with the wrong IDs, so no cities showed up. The plugin detects those tables and fills them again. Saved values are matched to the corrected IDs by country name.
- Version 1 listed Alabama to California twice. The unused copies are removed, and saved values are matched by state name.
- Chosen is no longer bundled. The field uses ACF's Select2.

## Uninstalling

Deleting the plugin drops the three tables on every site. Saved field values stay in post meta and still include the names.

## Development

```sh
composer install
composer check   # PHPCS, PHPStan and Pest
```

Bugs and pull requests are welcome on [GitHub](https://github.com/Vheissu/acf-country-field/issues).
