<?php

declare(strict_types=1);

namespace AcfCountryField\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case with WordPress functions stubbed by Brain Monkey.
 */
class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Fake options store read by get_option() and written by update_option().
     *
     * @var array<string, mixed>
     */
    protected array $siteOptions = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->siteOptions = [];

        Monkey\Functions\stubTranslationFunctions();
        Monkey\Functions\stubEscapeFunctions();
        Monkey\Functions\stubs([
            'absint' => static fn($value): int => abs((int) $value),
            'wp_unslash' => static fn($value) => is_string($value) ? stripslashes($value) : $value,
            'wp_json_encode' => static fn($data) => json_encode($data),
            'get_option' => fn(string $name, $default = false) => $this->siteOptions[$name] ?? $default,
            'update_option' => function (string $name, $value): bool {
                $this->siteOptions[$name] = $value;

                return true;
            },
            'delete_option' => function (string $name): bool {
                unset($this->siteOptions[$name]);

                return true;
            },
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
