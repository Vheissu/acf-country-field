<?php

declare(strict_types=1);

use AcfCountryField\Fields\CountryField;
use AcfCountryField\Fields\LegacyCountryField;
use AcfCountryField\Repositories\LocationRepository;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->repository = Mockery::mock(LocationRepository::class);
    $this->repository->shouldReceive('isUnitedStates')->andReturnUsing(static fn(int $id): bool => $id === 446);
    $this->repository->shouldReceive('getCountries')->andReturn([248 => 'Australia', 446 => 'United States of America'])->byDefault();
    $this->repository->shouldReceive('getCountry')->andReturnUsing(static fn(int $id) => [248 => 'Australia', 446 => 'United States of America'][$id] ?? null)->byDefault();
    $this->repository->shouldReceive('getStates')->andReturn([49 => 'Texas', 51 => 'Vermont'])->byDefault();
    $this->repository->shouldReceive('getState')->andReturnUsing(static fn(int $id) => [49 => 'Texas', 51 => 'Vermont'][$id] ?? null)->byDefault();
    $this->repository->shouldReceive('getCity')->andReturnUsing(static fn(int $id) => [
        1460 => ['id' => 1460, 'name' => 'Sydney', 'state_id' => 0, 'country_id' => 248],
        35988 => ['id' => 35988, 'name' => 'Austin', 'state_id' => 49, 'country_id' => 446],
    ][$id] ?? null)->byDefault();

    $this->field = new CountryField('/plugins/acf-country-field/acf-country-field.php', $this->repository);
    $this->config = [
        'id' => 'acf-field_1',
        'name' => 'acf[field_1]',
        'required' => 0,
        'default_country' => 446,
        'show_state' => 1,
        'show_city' => 1,
    ];
});

/**
 * @param array<string, mixed> $field
 */
function renderField(CountryField $instance, array $field): string
{
    ob_start();
    $instance->render_field($field);

    return (string) ob_get_clean();
}

describe('setup', function (): void {
    it('registers as a choice field', function (): void {
        expect($this->field->name)->toBe('country_field')
            ->and($this->field->label)->toBe('Country')
            ->and($this->field->category)->toBe('choice')
            ->and($this->field->public)->toBeTrue()
            ->and($this->field->defaults)->toBe(['default_country' => 446, 'show_state' => 1, 'show_city' => 1]);
    });

    it('keeps the version 1 type name as a hidden type', function (): void {
        $legacy = new LegacyCountryField('/plugins/acf-country-field/acf-country-field.php', $this->repository);

        expect($legacy->name)->toBe('COUNTRY_FIELD')
            ->and($legacy->public)->toBeFalse();
    });
});

describe('update_value', function (): void {
    it('stores the IDs with their names', function (): void {
        $value = $this->field->update_value(['country_id' => '248', 'city_id' => '1460'], 1, $this->config);

        expect($value)->toBe([
            'country_id' => 248,
            'country_name' => 'Australia',
            'state_id' => 0,
            'state_name' => '',
            'city_id' => 1460,
            'city_name' => 'Sydney',
        ]);
    });

    it('takes a US state from the city', function (): void {
        $value = $this->field->update_value(['country_id' => '446', 'state_id' => '51', 'city_id' => '35988'], 1, $this->config);

        expect($value['state_id'])->toBe(49)
            ->and($value['state_name'])->toBe('Texas')
            ->and($value['city_name'])->toBe('Austin');
    });

    it('keeps a US state without a city', function (): void {
        $value = $this->field->update_value(['country_id' => '446', 'state_id' => '51', 'city_id' => ''], 1, $this->config);

        expect($value['state_id'])->toBe(51)
            ->and($value['state_name'])->toBe('Vermont')
            ->and($value['city_id'])->toBe(0);
    });

    it('drops a state outside the United States', function (): void {
        $value = $this->field->update_value(['country_id' => '248', 'state_id' => '49'], 1, $this->config);

        expect($value['state_id'])->toBe(0)
            ->and($value['state_name'])->toBe('');
    });

    it('drops a city from another country', function (): void {
        $value = $this->field->update_value(['country_id' => '248', 'city_id' => '35988'], 1, $this->config);

        expect($value['city_id'])->toBe(0)
            ->and($value['city_name'])->toBe('');
    });

    it('drops an unknown state', function (): void {
        $value = $this->field->update_value(['country_id' => '446', 'state_id' => '99'], 1, $this->config);

        expect($value['state_id'])->toBe(0);
    });

    it('stores an empty location for an unknown country or bad input', function ($input): void {
        expect($this->field->update_value($input, 1, $this->config))->toBe([
            'country_id' => 0,
            'country_name' => '',
            'state_id' => 0,
            'state_name' => '',
            'city_id' => 0,
            'city_name' => '',
        ]);
    })->with([
        'unknown country' => [['country_id' => '999', 'city_id' => '1460']],
        'empty' => [['country_id' => '']],
        'string' => ['Australia'],
        'null' => [null],
    ]);
});

describe('load_value', function (): void {
    it('fixes country IDs saved with version 1.0.1 numbering', function (): void {
        $this->repository->shouldReceive('findCountryId')->with('Australia')->andReturn(248);

        $value = $this->field->load_value(['country_id' => '12', 'country_name' => 'Australia'], 1, $this->config);

        expect($value['country_id'])->toBe(248);
    });

    it('fixes state IDs for states version 1 listed twice', function (): void {
        $this->repository->shouldReceive('findStateId')->with('Vermont')->andReturn(51);

        $value = $this->field->load_value(['country_id' => '446', 'country_name' => 'United States of America', 'state_id' => '3', 'state_name' => 'Vermont'], 1, $this->config);

        expect($value['state_id'])->toBe(51)
            ->and($value['country_id'])->toBe('446');
    });

    it('leaves matching values alone', function (): void {
        $this->repository->shouldNotReceive('findCountryId');
        $this->repository->shouldNotReceive('findStateId');
        $value = ['country_id' => '446', 'country_name' => 'United States of America', 'state_id' => '49', 'state_name' => 'Texas'];

        expect($this->field->load_value($value, 1, $this->config))->toBe($value)
            ->and($this->field->load_value(['country_id' => '12', 'country_name' => false], 1, $this->config))->toBe(['country_id' => '12', 'country_name' => false])
            ->and($this->field->load_value(null, 1, $this->config))->toBeNull();
    });
});

describe('format_value', function (): void {
    it('returns null for a field that was never saved', function (): void {
        expect($this->field->format_value(null, 1, $this->config))->toBeNull()
            ->and($this->field->format_value('', 1, $this->config))->toBeNull();
    });

    it('returns the stored location with integer IDs', function (): void {
        $value = $this->field->format_value([
            'country_id' => '446',
            'country_name' => 'United States of America',
            'state_id' => '49',
            'state_name' => 'Texas',
            'city_id' => '35988',
            'city_name' => 'Austin',
        ], 1, $this->config);

        expect($value)->toBe([
            'country_id' => 446,
            'country_name' => 'United States of America',
            'state_id' => 49,
            'state_name' => 'Texas',
            'city_id' => 35988,
            'city_name' => 'Austin',
        ]);
    });

    it('looks up names missing from version 1 values', function (): void {
        $value = $this->field->format_value([
            'country_id' => '446',
            'country_name' => false,
            'state_id' => '49',
            'city_id' => '35988',
            'city_name' => false,
        ], 1, $this->config);

        expect($value['country_name'])->toBe('United States of America')
            ->and($value['state_name'])->toBe('Texas')
            ->and($value['city_name'])->toBe('Austin');
    });
});

describe('validate_value', function (): void {
    it('requires a country when the field is required', function (): void {
        $required = ['required' => 1] + $this->config;

        expect($this->field->validate_value(true, ['country_id' => ''], $required, 'acf[field_1]'))->toBe('Select a country.')
            ->and($this->field->validate_value(true, '', $required, 'acf[field_1]'))->toBe('Select a country.')
            ->and($this->field->validate_value(true, ['country_id' => '248'], $required, 'acf[field_1]'))->toBeTrue()
            ->and($this->field->validate_value(true, ['country_id' => ''], $this->config, 'acf[field_1]'))->toBeTrue();
    });

    it('keeps earlier errors', function (): void {
        expect($this->field->validate_value('Other error', [], $this->config, 'acf[field_1]'))->toBe('Other error');
    });
});

describe('render_field', function (): void {
    it('selects the default country for a new value', function (): void {
        $this->repository->shouldNotReceive('getCities');

        $html = renderField($this->field, ['value' => null] + $this->config);

        expect($html)->toContain('<option value="446" selected>United States of America</option>')
            ->and($html)->toContain('<label for="acf-field_1-country">Country</label>')
            ->and($html)->toContain('name="acf[field_1][country_id]"')
            ->and($html)->toContain('<option value="49">Texas</option>')
            ->and($html)->toContain('<option value="">Select a state first</option>')
            ->and($html)->toMatch('/<select id="acf-field_1-city" name="acf\[field_1\]\[city_id\]" disabled>/');
    });

    it('starts empty without a default country', function (): void {
        $html = renderField($this->field, ['value' => null, 'default_country' => ''] + $this->config);

        expect($html)->not->toContain(' selected>')
            ->and($html)->toContain('data-part="state" hidden');
    });

    it('lists the cities in the saved state', function (): void {
        $this->repository->shouldReceive('getCities')->once()->with(446, 49)->andReturn([35988 => 'Austin']);

        $html = renderField($this->field, ['value' => ['country_id' => 446, 'state_id' => 49, 'city_id' => 35988]] + $this->config);

        expect($html)->toContain('<option value="49" selected>Texas</option>')
            ->and($html)->toContain('<option value="35988" selected>Austin</option>');
    });

    it('takes the state from the city for version 1 values', function (): void {
        $this->repository->shouldReceive('getCities')->once()->with(446, 49)->andReturn([35988 => 'Austin']);

        $html = renderField($this->field, ['value' => ['country_id' => 446, 'state_id' => 0, 'city_id' => 35988]] + $this->config);

        expect($html)->toContain('<option value="49" selected>Texas</option>');
    });

    it('lists every city in the country when the state is hidden', function (): void {
        $this->repository->shouldReceive('getCities')->once()->with(446, 0)->andReturn([35988 => 'Austin']);

        $html = renderField($this->field, ['value' => ['country_id' => 446, 'city_id' => 35988], 'show_state' => 0] + $this->config);

        expect($html)->not->toContain('data-part="state"')
            ->and($html)->toContain('<option value="35988" selected>Austin</option>');
    });

    it('hides the state outside the United States', function (): void {
        $this->repository->shouldReceive('getCities')->once()->with(248, 0)->andReturn([1460 => 'Sydney']);

        $html = renderField($this->field, ['value' => ['country_id' => 248, 'city_id' => 1460]] + $this->config);

        expect($html)->toContain('data-part="state" hidden')
            ->and($html)->toContain('<option value="1460" selected>Sydney</option>');
    });

    it('can leave out the city', function (): void {
        $this->repository->shouldNotReceive('getCities');

        $html = renderField($this->field, ['value' => ['country_id' => 248], 'show_city' => 0] + $this->config);

        expect($html)->not->toContain('data-part="city"');
    });

    it('escapes names', function (): void {
        $this->repository->shouldReceive('getCountries')->andReturn([1 => '<b>Bad</b>']);

        $html = renderField($this->field, ['value' => null, 'default_country' => ''] + $this->config);

        expect($html)->toContain('&lt;b&gt;Bad&lt;/b&gt;')
            ->and($html)->not->toContain('<b>Bad</b>');
    });
});

describe('input_admin_enqueue_scripts', function (): void {
    it('enqueues the assets once with the script config', function (): void {
        $inline = [];
        $enqueued = false;

        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/acf-country-field/');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin-ajax.php');
        Functions\when('wp_create_nonce')->justReturn('nonce');
        Functions\when('wp_script_is')->alias(function () use (&$enqueued): bool {
            return $enqueued;
        });
        Functions\expect('wp_enqueue_style')->once()->with('acf-country-field', 'https://example.com/wp-content/plugins/acf-country-field/css/input.css', ['acf-input'], '2.1.0');
        Functions\expect('wp_enqueue_script')->once()->with('acf-country-field', 'https://example.com/wp-content/plugins/acf-country-field/js/input.js', ['acf-input', 'jquery'], '2.1.0', true)
            ->andReturnUsing(function () use (&$enqueued): void {
                $enqueued = true;
            });
        Functions\when('wp_add_inline_script')->alias(function (string $handle, string $script) use (&$inline): void {
            $inline[] = $script;
        });

        $this->field->input_admin_enqueue_scripts();
        $this->field->input_admin_enqueue_scripts();

        expect($inline)->toHaveCount(1)
            ->and($inline[0])->toStartWith('window.acfCountryField = {')
            ->and($inline[0])->toContain('"action":"acf_country_field_cities"')
            ->and($inline[0])->toContain('"usaId":446')
            ->and($inline[0])->toContain('"states":[{"id":49,"name":"Texas"},{"id":51,"name":"Vermont"}]');
    });
});
