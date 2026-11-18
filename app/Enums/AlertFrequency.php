<?php

namespace App\Enums;

use Carbon\CarbonInterval;

enum AlertFrequency: string
{
    case Never = 'never';
    case Instantly = 'instantly';
    case Daily = 'daily';
    case Every3Days = 'every_3_days';
    case Every7Days = 'every_7_days';

    /**
     * Value/label pairs for building a select.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()],
            self::cases(),
        );
    }

    /**
     * The frequencies that produce alerts, most frequent first.
     *
     * Never is left out: a Never search is a bookmark, so it produces no alert
     * rows and never enters matching.
     *
     * @return list<self>
     */
    public static function alerting(): array
    {
        $cases = array_values(array_filter(
            self::cases(),
            fn (self $frequency) => $frequency->interval() !== null,
        ));

        usort($cases, fn (self $a, self $b) => $a->interval()?->totalSeconds <=> $b->interval()?->totalSeconds);

        return $cases;
    }

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::Instantly => 'Instantly',
            self::Daily => 'Daily',
            self::Every3Days => 'Every 3 days',
            self::Every7Days => 'Every 7 days',
        };
    }

    /**
     * The minimum gap between emails for this search. Null means it never emails.
     * Instantly is a zero interval, so it's always due.
     */
    public function interval(): ?CarbonInterval
    {
        return match ($this) {
            self::Never => null,
            self::Instantly => CarbonInterval::seconds(0),
            self::Daily => CarbonInterval::days(1),
            self::Every3Days => CarbonInterval::days(3),
            self::Every7Days => CarbonInterval::days(7),
        };
    }
}
