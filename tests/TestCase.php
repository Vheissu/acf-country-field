<?php

declare(strict_types=1);

namespace AcfCountryField\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case with WordPress mocking support.
 */
class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Mock common WordPress functions
        $this->mockWordPressFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Mock common WordPress functions used throughout the plugin.
     */
    protected function mockWordPressFunctions(): void
    {
        // Translation functions (pass through)
        Monkey\Functions\stubs([
            '__' => static fn(string $text, string $domain = 'default'): string => $text,
            '_e' => static fn(string $text, string $domain = 'default'): void => print $text,
            'esc_html__' => static fn(string $text, string $domain = 'default'): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
            'esc_html' => static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
            'esc_attr' => static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
            'selected' => static function(mixed $selected, mixed $current = true, bool $echo = true): string {
                $result = $selected == $current ? ' selected="selected"' : '';
                if ($echo) {
                    echo $result;
                }
                return $result;
            },
        ]);

        // WordPress nonce functions
        Monkey\Functions\stubs([
            'wp_create_nonce' => static fn(string $action): string => 'test_nonce_' . $action,
            'wp_verify_nonce' => static fn(string $nonce, string $action): int|false => str_starts_with($nonce, 'test_nonce_') ? 1 : false,
            'check_ajax_referer' => static fn(string $action, string $query_arg = false, bool $die = true): int|false => 1,
            'wp_nonce_field' => static function(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $echo = true): string {
                $field = '<input type="hidden" name="' . $name . '" value="test_nonce_' . $action . '" />';
                if ($echo) {
                    echo $field;
                }
                return $field;
            },
        ]);

        // AJAX response functions
        Monkey\Functions\stubs([
            'wp_send_json' => static function(mixed $data): void {
                throw new \Exception('wp_send_json: ' . json_encode($data));
            },
            'wp_send_json_success' => static function(mixed $data = null): void {
                throw new \Exception('wp_send_json_success: ' . json_encode(['success' => true, 'data' => $data]));
            },
            'wp_send_json_error' => static function(mixed $data = null, int $code = null): void {
                throw new \Exception('wp_send_json_error: ' . json_encode(['success' => false, 'data' => $data]));
            },
        ]);

        // User capability functions
        Monkey\Functions\stubs([
            'current_user_can' => static fn(string $capability): bool => true,
        ]);

        // Sanitization functions
        Monkey\Functions\stubs([
            'absint' => static fn(mixed $value): int => abs((int) $value),
            'sanitize_text_field' => static fn(string $text): string => trim(strip_tags($text)),
        ]);

        // Plugin functions
        Monkey\Functions\stubs([
            'plugin_dir_path' => static fn(string $file): string => dirname($file) . '/',
            'plugin_dir_url' => static fn(string $file): string => 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/',
            'plugin_basename' => static fn(string $file): string => 'acf-country-field/' . basename($file),
            'admin_url' => static fn(string $path = ''): string => 'https://example.com/wp-admin/' . $path,
        ]);

        // Options functions
        Monkey\Functions\stubs([
            'get_option' => static fn(string $option, mixed $default = false): mixed => $default,
            'update_option' => static fn(string $option, mixed $value): bool => true,
            'delete_option' => static fn(string $option): bool => true,
        ]);

        // Cache functions
        Monkey\Functions\stubs([
            'wp_cache_flush' => static fn(): bool => true,
        ]);

        // Hook functions
        Monkey\Functions\stubs([
            'add_action' => static fn(): bool => true,
            'add_filter' => static fn(): bool => true,
        ]);
    }

    /**
     * Create a mock wpdb object.
     */
    protected function createMockWpdb(array $data = []): \wpdb
    {
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        return $wpdb;
    }
}
