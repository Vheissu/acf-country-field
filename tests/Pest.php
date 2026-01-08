<?php

declare(strict_types=1);

/**
 * Pest configuration file.
 */

use AcfCountryField\Tests\TestCase;

// Set up Pest to use our TestCase for all tests
pest()->extend(TestCase::class)->in('Unit');
