<?php

namespace App\Platform\Install;

use RuntimeException;

/**
 * Lee y cambia el .env (ADR 0030). Solo lo usa el instalador web: cambia las líneas que ya
 * existen y añade las que faltan, con comillas cuando el valor lo necesita.
 */
class EnvFile
{
    public function __construct(private readonly string $path) {}

    /**
     * @return array<string, string>
     */
    public function read(): array
    {
        $values = [];
        foreach (preg_split('/\R/', (string) @file_get_contents($this->path)) ?: [] as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
                $value = trim($m[2]);
                if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
                    $value = stripcslashes(substr($value, 1, -1));
                }
                $values[$m[1]] = $value;
            }
        }

        return $values;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->read()[$key] ?? $default;
    }

    /**
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $content = (string) @file_get_contents($this->path);
        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? (string) preg_replace_callback($pattern, fn (): string => $line, $content)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        if (@file_put_contents($this->path, $content) === false) {
            throw new RuntimeException('No se puede escribir el archivo .env: revisa los permisos de la carpeta del proyecto.');
        }
    }

    public function writable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-,=]*$/', $value)) {
            return $value;
        }

        return '"'.addcslashes($value, '"\\$').'"';
    }
}
