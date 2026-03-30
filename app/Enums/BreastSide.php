<?php

namespace App\Enums;

enum BreastSide: string
{
    case Left = 'left';
    case Right = 'right';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left',
            self::Right => 'Right',
        };
    }
}
