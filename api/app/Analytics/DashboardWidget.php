<?php

namespace App\Analytics;

/**
 * A figure, chart or table the owner can put on the analytics screen of a
 * section (orders, sales). Each needs some columns to be computed (a price
 * for revenue, a unit cost for profit...): a widget whose columns the
 * business doesn't have is offered with what's missing, never shown empty.
 * Labels live in the admin; here are what a widget needs and which ones a
 * business starts with.
 */
enum DashboardWidget: string
{
    // Figures
    case Revenue = 'revenue';
    case Profit = 'profit';
    case Margin = 'margin';
    case Count = 'count';
    case PerDay = 'perDay';
    case PerMonth = 'perMonth';
    case AverageCheck = 'averageCheck';
    case Units = 'units';
    /** Discounts, commissions, delivery fees and tax summed. */
    case Deductions = 'deductions';
    case Duration = 'duration';

    // Charts
    case RevenueChart = 'revenueChart';
    case CountChart = 'countChart';
    case ProfitChart = 'profitChart';
    case Weekdays = 'weekdays';
    case Hours = 'hours';

    // Tables
    case Peaks = 'peaks';
    case Months = 'months';
    case ByItem = 'byItem';
    case ByPayment = 'byPayment';
    case ByChannel = 'byChannel';
    case ByStatus = 'byStatus';
    /** By model, brand, supplier, country, intermediary, city... (ColumnRole::details). */
    case ByDetails = 'byDetails';
    case ByOwnColumns = 'byOwnColumns';
    case OwnNumbers = 'ownNumbers';
    case Tables = 'tables';

    /**
     * What the section's tables must have, all of it (see SectionData::has).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return match ($this) {
            self::Revenue, self::AverageCheck, self::RevenueChart => ['date', 'money'],
            self::Profit, self::Margin, self::ProfitChart => ['date', 'money', 'cost'],
            self::Count, self::PerDay, self::PerMonth, self::CountChart, self::Weekdays, self::Peaks, self::Months => ['date'],
            self::Units => ['date', 'quantity'],
            self::Deductions => ['date', 'deductions'],
            self::Duration => ['date', 'duration'],
            self::Hours => ['datetime'],
            self::ByItem => ['date', 'item'],
            self::ByPayment => ['date', 'payment'],
            self::ByChannel => ['date', 'channel'],
            self::ByStatus => ['date', 'status'],
            self::ByDetails => ['date', 'details'],
            self::ByOwnColumns => ['date', 'ownColumns'],
            self::OwnNumbers => ['date', 'ownNumbers'],
            self::Tables => ['manyTables'],
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Revenue, self::Profit, self::Margin, self::Count, self::PerDay, self::PerMonth, self::AverageCheck, self::Units, self::Deductions, self::Duration => 'figures',
            self::RevenueChart, self::CountChart, self::ProfitChart, self::Weekdays, self::Hours => 'charts',
            default => 'tables',
        };
    }

    /**
     * What's missing for this widget, given what the tables have.
     *
     * @param  array<string, bool>  $has
     * @return list<string>
     */
    public function missing(array $has): array
    {
        return array_values(array_filter($this->requires(), fn (string $need) => ! ($has[$need] ?? false)));
    }

    /**
     * A business that hasn't chosen yet starts with a few that fit its data:
     * money and profit if it has them, how many and how often, its units,
     * one chart, its best and weakest months and what sells most.
     *
     * @param  array<string, bool>  $has
     * @return list<self>
     */
    public static function defaults(array $has): array
    {
        $available = fn (self $w) => $w->missing($has) === [];
        $chart = $available(self::RevenueChart) ? self::RevenueChart : self::CountChart;

        return array_values(array_filter(
            [self::Revenue, self::Profit, self::Count, self::PerDay, self::AverageCheck, self::Units, self::Deductions, $chart, self::Peaks, self::ByItem, self::ByDetails],
            $available,
        ));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
