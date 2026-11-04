<?php

namespace App\Platform\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Volcado SQL de la base de datos en PHP puro (sin mysqldump ni exec, que en Hostinger
 * pueden no estar permitidos). Funciona con MariaDB/MySQL y con SQLite. El archivo
 * resultante se importa tal cual (phpMyAdmin, mysql < copia.sql o sqlite3).
 */
class DatabaseDumper
{
    private const CHUNK = 500;

    /**
     * @return array{tables: int, rows: int}
     */
    public function dump(string $path, ?string $connection = null): array
    {
        $db = DB::connection($connection);
        $driver = $db->getDriverName();
        $out = fopen($path, 'w') ?: throw new RuntimeException("No se puede escribir {$path}.");

        $mysql = in_array($driver, ['mysql', 'mariadb'], true);
        if (! $mysql && $driver !== 'sqlite') {
            throw new RuntimeException("Base de datos «{$driver}» no admitida en las copias.");
        }

        fwrite($out, "-- Copia de seguridad de Clave de Alma\n-- ".now()->toIso8601String()." · {$driver}\n\n");
        fwrite($out, $mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n" : "PRAGMA foreign_keys = OFF;\nBEGIN TRANSACTION;\n\n");

        $rows = 0;
        $tables = $this->tables($db, $mysql);
        foreach ($tables as $table) {
            $quoted = $mysql ? "`{$table}`" : "\"{$table}\"";
            fwrite($out, "-- Tabla {$table}\nDROP TABLE IF EXISTS {$quoted};\n".$this->createStatement($db, $mysql, $table).";\n");

            $columns = null;
            $offset = 0;
            do {
                $chunk = $db->table($table)->offset($offset)->limit(self::CHUNK)->get()->map(fn ($r): array => (array) $r)->all();
                if ($chunk !== []) {
                    $columns ??= implode(', ', array_map(fn (string $c): string => $mysql ? "`{$c}`" : "\"{$c}\"", array_keys($chunk[0])));
                    $values = array_map(fn (array $row): string => '('.implode(', ', array_map(fn ($v): string => $this->literal($db, $v), $row)).')', $chunk);
                    fwrite($out, "INSERT INTO {$quoted} ({$columns}) VALUES\n".implode(",\n", $values).";\n");
                    $rows += count($chunk);
                }
                $offset += self::CHUNK;
            } while (count($chunk) === self::CHUNK);
            fwrite($out, "\n");
        }

        if (! $mysql) { // en SQLite los índices van aparte de la tabla
            foreach ($db->select("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL ORDER BY name") as $index) {
                fwrite($out, $index->sql.";\n");
            }
        }

        fwrite($out, $mysql ? "SET FOREIGN_KEY_CHECKS = 1;\n" : "COMMIT;\nPRAGMA foreign_keys = ON;\n");
        fclose($out);

        return ['tables' => count($tables), 'rows' => $rows];
    }

    /**
     * @return list<string>
     */
    private function tables(Connection $db, bool $mysql): array
    {
        if ($mysql) {
            return array_values(array_map(fn ($r): string => (string) array_values((array) $r)[0], $db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")));
        }

        return array_values(array_map(fn ($r): string => (string) $r->name, $db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")));
    }

    private function createStatement(Connection $db, bool $mysql, string $table): string
    {
        if ($mysql) {
            $row = (array) $db->selectOne("SHOW CREATE TABLE `{$table}`");

            return (string) ($row['Create Table'] ?? array_values($row)[1]);
        }

        return (string) $db->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table])->sql;
    }

    private function literal(Connection $db, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value) || is_float($value) => (string) $value,
            default => $db->getPdo()->quote((string) $value),
        };
    }
}
