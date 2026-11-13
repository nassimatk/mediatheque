<?php

namespace App\Enum;

/** Type de support d'une édition. */
enum EditionType: string
{
    case Book = 'book';
    case Comic = 'comic';
    case Dvd = 'dvd';

    public function label(): string
    {
        return match ($this) {
            self::Book => 'Livre',
            self::Comic => 'Bande dessinée',
            self::Dvd => 'DVD',
        };
    }
}
