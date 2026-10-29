# Changelog

## 2.1.0

Version 2 is a rewrite. 2.0.0 was never released, so these notes cover the changes since 1.0.1.

### Changed

- For the United States, the state now comes before the city and narrows the city list. Saving a US city stores its state.
- Dropdowns use ACF's Select2 instead of the bundled Chosen library.
- The field type is named `country_field` and sits under Choice. Fields saved as `COUNTRY_FIELD` still work and are converted when their field group is saved.
- `get_field()` returns integer IDs, and `null` when the field has never been saved.
- New settings: Default Country, Show State and Show City.
- City lists are loaded from a new AJAX action, `acf_country_field_cities`, which needs a nonce. It also works for logged-out visitors, so front-end forms made with `acf_form()` can load cities. The `get_country_cities` and `get_us_states` actions are gone.
- Location tables are filled with about 40 batched queries instead of 38,000 single ones, and are indexed by country and state.
- Deactivating the plugin keeps the tables. Deleting it drops them, on every site in a network.
- Requires WordPress 6.2 and PHP 8.0.
- Removed the `Requires Plugins` header, which blocked activation on sites running ACF Pro.

### Fixed

- Fresh 1.0.1 installs showed no cities for any country. Countries were inserted without their IDs, so they no longer matched the cities. Affected tables are filled again on upgrade, and saved values are matched to the corrected IDs by country name.
- Cote d'Ivoire was missing, though its cities were listed.
- Alabama to California were listed twice, and the first copies had no cities. The copies are removed, and saved values are matched by state name. 14 duplicate cities are gone from new installs.
- Cities were listed in ID order instead of alphabetically after changing the country.
- Saving could store a city or state that didn't belong to the selected country.
- A slow response for one country could overwrite the cities for a country chosen after it.
- Country, state and city names are escaped. Queries use prepared statements.
- The legacy `acf-COUNTRY_FIELD.php` file showed up as a second plugin, and activating it caused a fatal error.

### Development

- Tests cover the field, repository, installer, AJAX handler and plugin setup, plus a check that every city points at a real country and state.
- Added PHPStan (level 8) and PHPCS (PSR-12) config, and a GitHub Actions workflow.

## 1.0.1

- Last release of the original plugin.
