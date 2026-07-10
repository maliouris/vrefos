<?php

namespace Tests\Unit;

use App\Enums\FeverLevel;
use PHPUnit\Framework\TestCase;

class FeverLevelTest extends TestCase
{
    public function test_from_temperature_boundaries(): void
    {
        // None: < 36.9
        $this->assertSame(FeverLevel::None, FeverLevel::fromTemperature(36.8));
        $this->assertSame(FeverLevel::None, FeverLevel::fromTemperature(0));

        // Low: 36.9 <= x < 37.5
        $this->assertSame(FeverLevel::Low, FeverLevel::fromTemperature(36.9));
        $this->assertSame(FeverLevel::Low, FeverLevel::fromTemperature(37.0));
        $this->assertSame(FeverLevel::Low, FeverLevel::fromTemperature(37.4));

        // Medium: 37.5 <= x < 38.5
        $this->assertSame(FeverLevel::Medium, FeverLevel::fromTemperature(37.5));
        $this->assertSame(FeverLevel::Medium, FeverLevel::fromTemperature(38.0));
        $this->assertSame(FeverLevel::Medium, FeverLevel::fromTemperature(38.4));

        // High: 38.5 <= x < 39.5
        $this->assertSame(FeverLevel::High, FeverLevel::fromTemperature(38.5));
        $this->assertSame(FeverLevel::High, FeverLevel::fromTemperature(39.0));
        $this->assertSame(FeverLevel::High, FeverLevel::fromTemperature(39.4));

        // TooHigh: >= 39.5
        $this->assertSame(FeverLevel::TooHigh, FeverLevel::fromTemperature(39.5));
        $this->assertSame(FeverLevel::TooHigh, FeverLevel::fromTemperature(41.0));
    }

    public function test_label(): void
    {
        $this->assertSame('No fever', FeverLevel::None->label());
        $this->assertSame('Low fever', FeverLevel::Low->label());
        $this->assertSame('Medium fever', FeverLevel::Medium->label());
        $this->assertSame('High fever', FeverLevel::High->label());
        $this->assertSame('Too high fever', FeverLevel::TooHigh->label());
    }

    public function test_range_label(): void
    {
        $this->assertSame('< 36.9°C', FeverLevel::None->rangeLabel());
        $this->assertSame('36.9–37.5°C', FeverLevel::Low->rangeLabel());
        $this->assertSame('37.5–38.5°C', FeverLevel::Medium->rangeLabel());
        $this->assertSame('38.5–39.5°C', FeverLevel::High->rangeLabel());
        $this->assertSame('≥ 39.5°C', FeverLevel::TooHigh->rangeLabel());
    }

    public function test_badge_class(): void
    {
        $this->assertSame('badge-success', FeverLevel::None->badgeClass());
        $this->assertSame('badge-info', FeverLevel::Low->badgeClass());
        $this->assertSame('badge-warning', FeverLevel::Medium->badgeClass());
        $this->assertSame('badge-error', FeverLevel::High->badgeClass());
        $this->assertSame('badge-error font-bold', FeverLevel::TooHigh->badgeClass());
    }
}
