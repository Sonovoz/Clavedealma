<?php

namespace App\Platform\Install;

use PDO;
use PDOException;

/**
 * Prueba los datos de la base de datos que se escriben en el instalador (ADR 0030) y explica
 * el fallo en palabras normales.
 */
class DatabaseProbe
{
    /**
     * @return array{ok: bool, message: string, version: ?string}
     */
    public function test(string $host, int $port, string $database, string $username, string $password): array
    {
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $statement = $pdo->query('SELECT VERSION()');
            $version = $statement === false ? '' : (string) $statement->fetchColumn();

            if (preg_match('/^(\d+)\.(\d+)/', $version, $m) && str_contains(strtolower($version), 'mariadb') && version_compare($m[1].'.'.$m[2], '10.6', '<')) {
                return ['ok' => false, 'message' => "MariaDB {$m[1]}.{$m[2]} es demasiado antigua: hace falta la 10.6 o superior (por ejemplo la 10.11).", 'version' => $version];
            }

            return ['ok' => true, 'message' => 'Conexión correcta con '.$version.'.', 'version' => $version];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => self::explain($e, $host, $database, $username), 'version' => null];
        }
    }

    /** ¿Responde Redis en ese servidor? (sin contraseña, como el contenedor redis por defecto). */
    public function redis(string $host, int $port = 6379): bool
    {
        if (! extension_loaded('redis')) {
            return false;
        }
        $socket = @fsockopen($host, $port, $errno, $error, 1.5);
        if ($socket === false) {
            return false;
        }
        fwrite($socket, "PING\r\n");
        $reply = (string) fgets($socket);
        fclose($socket);

        return str_starts_with($reply, '+PONG');
    }

    private static function explain(PDOException $e, string $host, string $database, string $username): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        return match (true) {
            $code === 1045 => "El usuario «{$username}» o la contraseña no son correctos.",
            $code === 1049 => "No existe la base de datos «{$database}». Créala antes (por ejemplo en phpMyAdmin) o revisa el nombre.",
            $code === 1044 => "El usuario «{$username}» no tiene permiso sobre la base de datos «{$database}».",
            in_array($code, [2002, 2005, 2006], true) || str_contains($e->getMessage(), 'getaddrinfo') => "No se encuentra el servidor «{$host}». Si MariaDB está en otro contenedor de Docker, suele llamarse como el contenedor (por ejemplo «mariadb») y tiene que estar en la misma red.",
            default => 'No se puede conectar: '.$e->getMessage(),
        };
    }
}
