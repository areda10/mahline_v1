<?php

declare(strict_types=1);

namespace App\Domains\Localization\Enums;

final class MoroccanRegion
{
    /** @var list<string> */
    public const VALUES = [
        'Tanger-Tétouan-Al Hoceïma',
        "L'Oriental",
        'Fès-Meknès',
        'Rabat-Salé-Kénitra',
        'Béni Mellal-Khénifra',
        'Casablanca-Settat',
        'Marrakech-Safi',
        'Drâa-Tafilalet',
        'Souss-Massa',
        'Guelmim-Oued Noun',
        'Laâyoune-Sakia El Hamra',
        'Dakhla-Oued Ed-Dahab',
    ];

    /** @return list<string> */
    public static function values(): array
    {
        return self::VALUES;
    }
}
