<?php

namespace App\Enums;

enum PropertyType: string
{
    case Detached = 'detached';
    case SemiDetached = 'semi_detached';
    case Terraced = 'terraced';
    case Flat = 'flat';
    case Bungalow = 'bungalow';

    /**
     * Value/label pairs for building a select. Keeps the option list in one
     * place rather than duplicated in the front end.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Detached => 'Detached',
            self::SemiDetached => 'Semi-detached',
            self::Terraced => 'Terraced',
            self::Flat => 'Flat',
            self::Bungalow => 'Bungalow',
        };
    }

    /**
     * The plural used in search summaries ("Flats in Manchester").
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Detached => 'Detached homes',
            self::SemiDetached => 'Semi-detached homes',
            self::Terraced => 'Terraced homes',
            self::Flat => 'Flats',
            self::Bungalow => 'Bungalows',
        };
    }
}
