<?php

declare(strict_types=1);

namespace AcfCountryField\Fields;

/**
 * The field type name used by version 1. Kept so fields saved with it keep working, but hidden
 * from the field type picker.
 */
final class LegacyCountryField extends CountryField
{
    public const TYPE = 'COUNTRY_FIELD';

    public function initialize(): void
    {
        parent::initialize();

        $this->public = false;
    }
}
