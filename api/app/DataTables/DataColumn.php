<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Enums\DataColumnType;

/**
 * One column of a business's own table, and how a raw value typed in the
 * admin or read from an imported file becomes the stored value. Parsing is
 * forgiving on purpose (spaces and ֏ in amounts, 17.10.2026 dates, "այո"),
 * since files come from many different places.
 */
final readonly class DataColumn
{
    /** @param list<string> $options */
    public function __construct(
        public string $key,
        public string $label,
        public DataColumnType $type,
        public bool $required = false,
        public array $options = [],
        /** What the column means (price, quantity...), whatever its label. */
        public ?ColumnRole $role = null,
    ) {}

    public static function fromArray(array $column): self
    {
        return new self(
            key: $column['key'],
            label: $column['label'],
            type: DataColumnType::from($column['type']),
            required: (bool) ($column['required'] ?? false),
            options: array_values($column['options'] ?? []),
            role: ColumnRole::tryFrom((string) ($column['role'] ?? '')),
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'options' => $this->type === DataColumnType::Select ? $this->options : [],
            'role' => $this->role?->value,
        ];
    }

    /**
     * @param  ?string  $dateOrder  Moment::DAY_FIRST or MONTH_FIRST, for dates like 10/09/2026
     * @return array{0: mixed, 1: ?string} the stored value and, when the raw
     *                                     value can't be used, why (Armenian, shown in the admin)
     */
    public function parse(mixed $raw, ?string $dateOrder = null): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
        }

        if ($raw === null || $raw === '') {
            return [null, $this->required ? 'Պարտադիր դաշտ է։' : null];
        }

        return match ($this->type) {
            DataColumnType::Text => [mb_substr(is_scalar($raw) ? (string) $raw : json_encode($raw), 0, 5000), null],
            DataColumnType::Number, DataColumnType::Money => $this->parseNumber($raw),
            DataColumnType::Date, DataColumnType::DateTime => $this->parseDate($raw, $dateOrder),
            DataColumnType::Boolean => $this->parseBoolean($raw),
            DataColumnType::Select => $this->parseOption($raw),
        };
    }

    private function parseNumber(mixed $raw): array
    {
        if (is_int($raw) || is_float($raw)) {
            return [$raw, null];
        }

        if (! is_string($raw)) {
            return [null, 'Պետք է լինի թիվ։'];
        }

        // "12 500 ֏", "12,500.50", "12500,5", "AMD 900", "10%"; but not "Item 1".
        $clean = preg_replace('/[\s\x{00A0}\x{202F}\']|֏|դր\.?|amd|драм|руб\.?|₽|\$|€|usd|eur|%/iu', '', $raw);

        if (! preg_match('/^[+-]?[\d.,]*\d[\d.,]*$/', $clean)) {
            return [null, 'Պետք է լինի թիվ։'];
        }
        $clean = ltrim($clean, '+');

        if (substr_count($clean, '.') > 1) {
            // "1.234.567" or "1.234.567,50": dots group thousands.
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace(',', '', $clean);
        } elseif (str_contains($clean, ',')) {
            // One comma followed by 1-2 digits is a decimal comma; otherwise thousands.
            $clean = preg_match('/^-?\d+,\d{1,2}$/', $clean) ? str_replace(',', '.', $clean) : str_replace(',', '', $clean);
        }

        if (! is_numeric($clean)) {
            return [null, 'Պետք է լինի թիվ։'];
        }

        $number = $clean + 0;

        return [is_float($number) && floor($number) == $number && abs($number) < PHP_INT_MAX ? (int) $number : $number, null];
    }

    private function parseDate(mixed $raw, ?string $order): array
    {
        $moment = Moment::parse($raw, $order);

        if ($moment === null) {
            return [null, $this->type === DataColumnType::DateTime
                ? 'Ամսաթիվը չի ճանաչվել (օր.՝ 17.10.2026 14:30)։'
                : 'Ամսաթիվը չի ճանաչվել (օր.՝ 2026-10-17 կամ 17.10.2026)։'];
        }

        return [$this->type === DataColumnType::DateTime ? $moment['date'].' '.($moment['time'] ?? '00:00') : $moment['date'], null];
    }

    private function parseBoolean(mixed $raw): array
    {
        if (is_bool($raw)) {
            return [$raw, null];
        }

        $value = mb_strtolower(trim((string) $raw));

        return match (true) {
            in_array($value, ['1', 'true', 'yes', 'y', 'այո', 'да', '+', 'x', '✓'], true) => [true, null],
            in_array($value, ['0', 'false', 'no', 'n', 'ոչ', 'нет', '-'], true) => [false, null],
            default => [null, 'Պետք է լինի այո/ոչ։'],
        };
    }

    private function parseOption(mixed $raw): array
    {
        $value = mb_strtolower(trim((string) $raw));

        foreach ($this->options as $option) {
            if (mb_strtolower($option) === $value) {
                return [$option, null];
            }
        }

        return [null, 'Արժեքը տարբերակների ցանկում չէ։'];
    }
}
