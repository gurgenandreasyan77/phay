<?php

namespace App\DataTables\Import;

use App\Assistant\AttachmentKind;
use App\Assistant\Attachments;
use App\Media\LinkReader;
use App\Models\AssistantAttachment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * A table to copy, read from where the owner has it:
 *
 * - a Google Sheets link (its sheet as CSV: the sheet must be shared as
 *   «anyone with the link», a private one answers with Google's login page);
 * - a link to a CSV or XLSX file;
 * - a web page with a table on it (its biggest <table>);
 * - a CSV or XLSX file attached in the chat.
 *
 * The first non-empty row is the header row (Sheet::fromRows). Links are
 * opened like read_link does (public addresses only, size capped).
 */
final class TableSource
{
    /** Bytes read from a link at most. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Rows read at most (a copy takes fewer, see TableCopier). */
    public const MAX_ROWS = 5000;

    public function __construct(
        private readonly LinkReader $links,
        private readonly FileReader $files,
    ) {}

    /**
     * @return array{sheet: Sheet, name: ?string, from: string}
     *
     * @throws InvalidArgumentException with what to tell the owner
     */
    public function fromUrl(string $url): array
    {
        $url = trim($url);
        $google = self::googleSheet($url);

        [$response, $final] = $this->links->download($google['export'] ?? $url, self::MAX_BYTES);
        $type = strtolower(trim(explode(';', (string) $response['type'])[0]));
        $name = self::fileName((string) $response['disposition']);

        if ($google !== null) {
            if ($type === 'text/html') {
                throw new InvalidArgumentException('This Google Sheet is private: ask the owner to open «Share» → «General access» → «Anyone with the link» (Viewer) and send the link again, or to download it as Excel (File → Download → .xlsx) and attach the file.');
            }

            // «Book - Sheet1.csv» → «Book · Sheet1».
            $name = $name !== null ? str_replace(' - ', ' · ', preg_replace('/\.csv$/i', '', $name)) : null;

            return ['sheet' => $this->parse($response['body'], 'csv'), 'name' => $name, 'from' => 'Google Sheets'];
        }

        $extension = strtolower(pathinfo((string) parse_url($final, PHP_URL_PATH), PATHINFO_EXTENSION));
        $format = match (true) {
            str_contains($type, 'spreadsheetml') || $extension === 'xlsx' || str_starts_with($response['body'], "PK\x03\x04") => 'xlsx',
            in_array($type, ['text/csv', 'application/csv', 'text/tab-separated-values'], true) || in_array($extension, ['csv', 'tsv'], true) => 'csv',
            in_array($type, ['text/html', 'application/xhtml+xml'], true) => 'html',
            $type === 'text/plain' => 'csv',
            default => throw new InvalidArgumentException("This link is not a table (it is {$type}). Send a Google Sheets link, a CSV or XLSX file link, a page with a table, or attach the file or a screenshot."),
        };

        if ($format === 'html') {
            return ['sheet' => $this->htmlTable($response['body']), 'name' => null, 'from' => (string) parse_url($final, PHP_URL_HOST)];
        }

        $name ??= pathinfo((string) parse_url($final, PHP_URL_PATH), PATHINFO_FILENAME) ?: null;

        return ['sheet' => $this->parse($response['body'], $format), 'name' => $name !== null ? urldecode($name) : null, 'from' => (string) parse_url($final, PHP_URL_HOST)];
    }

    /**
     * A CSV or XLSX file attached in the chat.
     *
     * @return array{sheet: Sheet, name: ?string, from: string}
     */
    public function fromAttachment(AssistantAttachment $file): array
    {
        $format = match (true) {
            $file->kind === AttachmentKind::Sheet => 'xlsx',
            $file->kind === AttachmentKind::Text && in_array(strtolower(pathinfo($file->name, PATHINFO_EXTENSION)), ['csv', 'txt', 'tsv'], true) => 'csv',
            default => throw new InvalidArgumentException("[file #{$file->id}] is not a CSV or XLSX table. For a picture of a table, read it yourself and use create_table."),
        };

        $body = Storage::disk(Attachments::DISK)->get($file->path) ?? '';

        return ['sheet' => $this->parse($body, $format), 'name' => pathinfo($file->name, PATHINFO_FILENAME) ?: null, 'from' => $file->name];
    }

    /**
     * The CSV export of a Google Sheets link (the sheet the link shows, by
     * its gid), or null for another link.
     *
     * @return array{export: string}|null
     */
    public static function googleSheet(string $url): ?array
    {
        $parts = parse_url($url);
        if (strtolower((string) ($parts['host'] ?? '')) !== 'docs.google.com') {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        preg_match('/(?:^|[?&#])gid=(\d+)/', ($parts['query'] ?? '').'#'.($parts['fragment'] ?? ''), $gid);
        $gid = isset($gid[1]) ? '&gid='.$gid[1] : '';

        // Published to the web: /spreadsheets/d/e/<key>/pubhtml.
        if (preg_match('#^/spreadsheets/d/e/([A-Za-z0-9_-]+)#', $path, $m)) {
            return ['export' => "https://docs.google.com/spreadsheets/d/e/{$m[1]}/pub?output=csv{$gid}"];
        }
        if (preg_match('#^/spreadsheets/d/([A-Za-z0-9_-]+)#', $path, $m)) {
            return ['export' => "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=csv{$gid}"];
        }

        return null;
    }

    private function parse(string $body, string $format): Sheet
    {
        $path = tempnam(sys_get_temp_dir(), 'table');
        file_put_contents($path, $body);

        try {
            return $this->files->read($path, $format, self::MAX_ROWS);
        } catch (UnreadableFile $e) {
            throw new InvalidArgumentException('The table could not be read: '.$e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    /** The biggest table of a web page. */
    private function htmlTable(string $html): Sheet
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        $best = [];
        foreach ($xpath->query('//table') as $table) {
            $rows = [];
            foreach ($xpath->query('.//tr', $table) as $tr) {
                $cells = [];
                foreach ($xpath->query('./th|./td', $tr) as $cell) {
                    /** @var DOMElement $cell */
                    $text = trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?? '');
                    $span = max(1, min(20, (int) $cell->getAttribute('colspan')));
                    array_push($cells, $text, ...array_fill(0, $span - 1, ''));
                }
                $rows[] = $cells;
            }
            if (count($rows) * max(1, count($rows[0] ?? [])) > count($best) * max(1, count($best[0] ?? []))) {
                $best = $rows;
            }
        }

        if (count($best) < 2) {
            throw new InvalidArgumentException('There is no table on this page (it may be drawn by scripts). Ask the owner for a screenshot or the file.');
        }

        return Sheet::fromRows($best, self::MAX_ROWS);
    }

    /** The file name a download says it is, if any. */
    private static function fileName(string $disposition): ?string
    {
        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $disposition, $m)) {
            return rawurldecode(trim($m[1], '"'));
        }
        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
