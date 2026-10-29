<?php

// phpcs:ignoreFile -- mirrors the shape of WordPress's own class.

/**
 * Stand-in for WordPress's database class. Records every query, and answers them with $respond.
 */
class wpdb
{
    public $prefix = 'wp_';

    /** @var list<string> */
    public $queries = [];

    /** @var callable(string $method, string $sql): mixed */
    public $respond;

    public function __construct()
    {
        $this->respond = static fn() => null;
    }

    public function prepare($query, ...$args)
    {
        return preg_replace_callback('/%[dsi]/', static function (array $match) use (&$args): string {
            $arg = array_shift($args);

            return match ($match[0]) {
                '%d' => (string) (int) $arg,
                '%i' => (string) $arg,
                default => "'" . addslashes((string) $arg) . "'",
            };
        }, $query);
    }

    public function get_results($sql)
    {
        return $this->run('get_results', $sql);
    }

    public function get_row($sql)
    {
        return $this->run('get_row', $sql);
    }

    public function get_col($sql)
    {
        return $this->run('get_col', $sql);
    }

    public function esc_like($text)
    {
        return addcslashes($text, '_%\\');
    }

    public function get_var($sql)
    {
        return $this->run('get_var', $sql);
    }

    public function query($sql)
    {
        return $this->run('query', $sql);
    }

    public function get_charset_collate()
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    }

    private function run(string $method, string $sql)
    {
        $this->queries[] = $sql;

        return ($this->respond)($method, $sql);
    }
}
