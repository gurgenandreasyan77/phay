<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded files are stored as full URLs built from APP_URL. When the API
 * moves to another address, this rewrites them everywhere they were saved:
 * plain columns, JSON columns (where "/" may be stored as "\/") and HTML.
 */
class RehostMedia extends Command
{
    protected $signature = 'platform:media:rehost
        {from : Old base URL, e.g. https://servio.am}
        {to : New base URL, e.g. https://api.phay.am}
        {--dry-run : Only count what would change}';

    protected $description = 'Rewrite stored file URLs after the API moved to another address';

    /** Framework tables that never hold file URLs. */
    private const SKIP = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens', 'personal_access_tokens'];

    public function handle(): int
    {
        $from = rtrim($this->argument('from'), '/');
        $to = rtrim($this->argument('to'), '/');

        if (! preg_match('#^https?://#', $from) || ! preg_match('#^https?://#', $to) || $from === $to) {
            $this->error('Give two different base URLs starting with http:// or https://.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $pairs = [[$from, $to], [str_replace('/', '\/', $from), str_replace('/', '\/', $to)]];
        $rows = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, self::SKIP, true)) {
                continue;
            }

            foreach (Schema::getColumns($table) as $column) {
                if (! $this->holdsText($column['type_name'])) {
                    continue;
                }

                $count = $this->rewrite($table, $column['name'], $column['type_name'], $pairs, $dryRun);

                if ($count > 0) {
                    $rows[] = [$table, $column['name'], $count];
                }
            }
        }

        if ($rows === []) {
            $this->info("No stored URLs start with {$from}.");

            return self::SUCCESS;
        }

        $this->table(['Table', 'Column', 'Rows'], $rows);
        $this->info($dryRun ? 'Dry run: nothing was changed.' : "Rewrote {$from} to {$to}.");

        return self::SUCCESS;
    }

    private function holdsText(string $type): bool
    {
        return in_array(strtolower($type), ['varchar', 'character varying', 'text', 'json', 'jsonb', 'string', 'longtext', 'mediumtext'], true);
    }

    /** @param  list<array{string, string}>  $pairs */
    private function rewrite(string $table, string $column, string $type, array $pairs, bool $dryRun): int
    {
        $pgsql = DB::getDriverName() === 'pgsql';
        $isJson = in_array(strtolower($type), ['json', 'jsonb'], true);
        $text = $pgsql && $isJson ? "\"{$column}\"::text" : "\"{$column}\"";
        $matching = fn () => DB::table($table)->where(function ($q) use ($text, $pairs) {
            foreach ($pairs as [$old]) {
                // Postgres treats a backslash in LIKE as an escape by default,
                // which would eat the escaped slashes of JSON; "!" is used instead.
                $q->orWhereRaw("{$text} like ? escape '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $old).'%']);
            }
        });

        $count = $matching()->count();

        if ($count === 0 || $dryRun) {
            return $count;
        }

        $expression = $text;
        $bindings = [];
        foreach ($pairs as [$old, $new]) {
            $expression = "replace({$expression}, ?, ?)";
            array_push($bindings, $old, $new);
        }
        if ($pgsql && $isJson) {
            $expression = "({$expression})::{$type}";
        }

        $matching()->update([$column => DB::raw(DB::getQueryGrammar()->substituteBindingsIntoRawSql($expression, $bindings))]);

        return $count;
    }
}
