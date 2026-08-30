<?php
/**
 * Backend environment loader.
 *
 * Process-level environment variables take precedence over values from the
 * project root .env file. This keeps the same configuration usable locally,
 * in Docker, and in hosted environments that inject secrets at runtime.
 */

if (!function_exists('config_env_values')) {
    /**
     * @return array<string, string>
     */
    function config_env_values()
    {
        static $values;

        if ($values !== null) {
            return $values;
        }

        $values = [];
        $projectRoot = dirname(__DIR__, 2);
        $environmentFile = getenv('ENV_FILE');
        $environmentFile = $environmentFile !== false && $environmentFile !== ''
            ? $environmentFile
            : $projectRoot . DIRECTORY_SEPARATOR . '.env';

        if (is_file($environmentFile) && is_readable($environmentFile)) {
            $lines = file($environmentFile, FILE_IGNORE_NEW_LINES);

            foreach ($lines ?: [] as $lineNumber => $line) {
                if ($lineNumber === 0) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                }

                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }

                if (substr($line, 0, 7) === 'export ') {
                    $line = substr($line, 7);
                }

                $separator = strpos($line, '=');
                if ($separator === false) {
                    continue;
                }

                $key = trim(substr($line, 0, $separator));
                if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                    continue;
                }

                $value = trim(substr($line, $separator + 1));
                if (strlen($value) >= 2) {
                    $firstCharacter = $value[0];
                    $lastCharacter = $value[strlen($value) - 1];

                    if ($firstCharacter === $lastCharacter && ($firstCharacter === "'" || $firstCharacter === '"')) {
                        $value = substr($value, 1, -1);
                        if ($firstCharacter === '"') {
                            $value = stripcslashes($value);
                        }
                    }
                }

                // An explicitly injected environment variable always wins.
                $existingValue = getenv($key);
                if ($existingValue !== false) {
                    $values[$key] = $existingValue;
                    continue;
                }

                $values[$key] = $value;
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        $environment = strtolower(trim((string)($values['ENVIRONMENT'] ?? getenv('ENVIRONMENT') ?: 'dev')));
        if (!in_array($environment, ['dev', 'staging', 'production'], true)) {
            throw new RuntimeException('ENVIRONMENT must be one of: dev, staging, production.');
        }

        $values['ENVIRONMENT'] = $environment;
        if (getenv('ENVIRONMENT') === false) {
            putenv('ENVIRONMENT=' . $environment);
            $_ENV['ENVIRONMENT'] = $environment;
        }

        return $values;
    }
}

if (!function_exists('config_env')) {
    /**
     * Read a backend environment variable.
     *
     * @param mixed $default
     * @return mixed
     */
    function config_env($key, $default = null)
    {
        $values = config_env_values();
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        $existingValue = getenv($key);
        return $existingValue !== false ? $existingValue : $default;
    }
}

if (!function_exists('config_env_int')) {
    function config_env_int($key, $default = 0)
    {
        return (int)config_env($key, $default);
    }
}

if (!function_exists('config_env_bool')) {
    function config_env_bool($key, $default = false)
    {
        $value = config_env($key, null);
        if ($value === null) {
            return (bool)$default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$default;
    }
}

if (!function_exists('config_env_list')) {
    /**
     * Read a comma-separated environment variable as a trimmed string list.
     *
     * @param array<int, string> $default
     * @return array<int, string>
     */
    function config_env_list($key, array $default = [])
    {
        $value = config_env($key, null);
        if ($value === null) {
            return $default;
        }

        $items = array_map('trim', explode(',', (string)$value));
        return array_values(array_filter($items, static function ($item) {
            return $item !== '';
        }));
    }
}

if (!function_exists('config_swoole_host')) {
    function config_swoole_host()
    {
        $host = trim((string)config_env('SWOOLE_HOST', '127.0.0.1'));
        if ($host === '') {
            throw new RuntimeException('SWOOLE_HOST must not be empty.');
        }

        return $host;
    }
}

if (!function_exists('config_swoole_port')) {
    function config_swoole_port()
    {
        $port = config_env_int('SWOOLE_PORT', 9530);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('SWOOLE_PORT must be between 1 and 65535.');
        }

        return $port;
    }
}

if (!function_exists('config_swoole_address')) {
    function config_swoole_address()
    {
        return sprintf('tcp://%s:%d', config_swoole_host(), config_swoole_port());
    }
}

// Load and validate the environment as soon as any backend config is used.
config_env_values();
