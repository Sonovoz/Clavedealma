<?php

namespace App\Platform\CatalogImport;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

/**
 * Conjunto de fotos de una importación: nombre de fichero => ruta local.
 *
 * Se crea desde una carpeta o desde un ZIP. Del ZIP solo se usa el nombre
 * de cada fichero (nunca su ruta), así que no puede escribir fuera de la carpeta temporal.
 */
class PhotoSet
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    private const MAX_ZIP_BYTES = 1024 * 1024 * 1024;

    /** Ficheros que añaden los sistemas operativos y se ignoran sin avisar. */
    private const SYSTEM_FILES = ['thumbs.db', 'desktop.ini'];

    /**
     * @param  array<string, string>  $files
     * @param  list<string>  $duplicates
     */
    private function __construct(
        public readonly array $files,
        public readonly array $duplicates = [],
        private readonly ?string $tempDir = null,
    ) {}

    /**
     * @param  array<string, string>  $files  nombre => ruta
     */
    public static function fromFiles(array $files): self
    {
        return new self($files);
    }

    public static function fromDirectory(string $dir): self
    {
        if (! File::isDirectory($dir)) {
            throw new InvalidArgumentException("No existe la carpeta de fotos [{$dir}].");
        }

        $files = [];
        $duplicates = [];
        foreach (File::allFiles($dir) as $file) {
            $name = $file->getFilename();
            if (self::isSystemFile($name)) {
                continue;
            }
            if (isset($files[$name])) {
                $duplicates[] = $name;
            }
            $files[$name] = $file->getPathname();
        }
        ksort($files);

        return new self($files, $duplicates);
    }

    public static function fromZip(string $zipPath, string $tempDir): self
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('El fichero de fotos no es un ZIP válido.');
        }

        File::ensureDirectoryExists($tempDir);
        $files = [];
        $duplicates = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false || str_ends_with($stat['name'], '/') || str_contains($stat['name'], '__MACOSX/')) {
                continue;
            }
            $name = basename(str_replace('\\', '/', $stat['name']));
            if ($name === '' || self::isSystemFile($name)) {
                continue;
            }
            if (isset($files[$name])) {
                $duplicates[] = $name;

                continue;
            }

            $total += $stat['size'];
            if ($total > self::MAX_ZIP_BYTES) {
                $zip->close();
                throw new InvalidArgumentException('El ZIP de fotos supera 1 GB descomprimido.');
            }
            if ($stat['size'] > self::MAX_BYTES) {
                $files[$name] = ''; // demasiado grande: se informa al validar sin extraerla

                continue;
            }

            $destination = $tempDir.'/'.Str::random(8).'-'.$name;
            $stream = $zip->getStream($stat['name']);
            if ($stream === false) {
                continue;
            }
            File::put($destination, (string) stream_get_contents($stream));
            fclose($stream);
            $files[$name] = $destination;
        }
        $zip->close();
        ksort($files);

        return new self($files, $duplicates, $tempDir);
    }

    public function size(string $name): int
    {
        $path = $this->files[$name] ?? '';

        return $path === '' ? self::MAX_BYTES + 1 : (int) filesize($path);
    }

    /** Borra la carpeta temporal del ZIP, si la hay. */
    public function cleanup(): void
    {
        if ($this->tempDir !== null) {
            File::deleteDirectory($this->tempDir);
        }
    }

    private static function isSystemFile(string $name): bool
    {
        return str_starts_with($name, '.') || in_array(strtolower($name), self::SYSTEM_FILES, true);
    }
}
