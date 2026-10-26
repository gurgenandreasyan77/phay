<?php

namespace App\Exports;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * A file made on the server from the business's own data (a table, a
 * report), sent once as an attachment and deleted: never stored, never
 * under a public URL. XLSX holds every sheet, CSV only the first one.
 */
final class Download
{
    public const XLSX = 'xlsx';

    public const CSV = 'csv';

    public const FORMATS = [self::XLSX, self::CSV];

    /** @param  list<Sheet>  $sheets */
    public static function response(array $sheets, string $name, string $format): BinaryFileResponse
    {
        if ($sheets === []) {
            throw new InvalidArgumentException('Nothing to export.');
        }

        $path = tempnam(sys_get_temp_dir(), 'phay-export-');
        match ($format) {
            self::XLSX => (new XlsxWriter)->write($sheets, $path),
            self::CSV => (new CsvWriter)->write($sheets[0], $path),
            default => throw new InvalidArgumentException("Unknown format [{$format}]."),
        };

        $filename = self::filename($name).'.'.$format;
        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => $format === self::XLSX
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, self::ascii($filename));
        $response->setPrivate();

        return $response->deleteFileAfterSend();
    }

    /** A name a file system accepts: no slashes, quotes or control characters. */
    public static function filename(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $name));
        $name = preg_replace('/\s+/u', ' ', $name);

        return mb_substr($name !== '' ? $name : 'phay', 0, 120);
    }

    /** The name for browsers that don't read UTF-8 names. */
    private static function ascii(string $filename): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '', $filename);
        $ascii = trim(preg_replace('/\s+/', ' ', $ascii), ' .-');

        return preg_match('/[A-Za-z].*\./', $ascii) ? $ascii : 'phay-export.'.pathinfo($filename, PATHINFO_EXTENSION);
    }
}
