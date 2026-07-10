<?php

namespace App\Enums;

enum FeverLevel: string
{
    case None = 'none';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case TooHigh = 'too_high';

    public static function fromTemperature(float $temperature): self
    {
        return match (true) {
            $temperature < 36.9 => self::None,
            $temperature < 37.5 => self::Low,
            $temperature < 38.5 => self::Medium,
            $temperature < 39.5 => self::High,
            default => self::TooHigh,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'No fever',
            self::Low => 'Low fever',
            self::Medium => 'Medium fever',
            self::High => 'High fever',
            self::TooHigh => 'Too high fever',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::None => 'badge-success',
            self::Low => 'badge-info',
            self::Medium => 'badge-warning',
            self::High => 'badge-error',
            self::TooHigh => 'badge-error font-bold',
        };
    }
}
